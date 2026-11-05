<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\AreaTematica;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Exports\TrabalhosExport;
use App\Models\TrabalhoAvaliacao;
use App\Models\TrabalhoConfig;
use App\Services\TrabalhoWorkflow;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Gestão de trabalhos do evento (gestor de trabalhos, dono e co-administradores).
 */
class TrabalhoGestaoController extends Controller
{
    public function __construct(private TrabalhoWorkflow $workflow)
    {
    }

    /** Consulta de trabalhos submetidos com os filtros da fila (usada na lista e na exportação). */
    private function consulta(Request $request, Evento $evento)
    {
        $query = $evento->trabalhos()
            ->where('status', '!=', Trabalho::RASCUNHO) // rascunhos são privados do autor
            ->with(['autores', 'area', 'arquivoAtual', 'avaliacoes']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($tipo = $request->input('tipo')) {
            $query->where('tipo_trabalho', $tipo);
        }
        if ($area = $request->input('area')) {
            $query->where('area_tematica_id', $area);
        }
        if ($busca = trim((string) $request->input('q'))) {
            $like = '%' . addcslashes($busca, '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('titulo', 'like', $like)
                    ->orWhere('codigo', 'like', $like)
                    ->orWhereHas('autores', fn ($a) => $a->where('nome_completo', 'like', $like)->orWhere('cpf', 'like', $like));
            });
        }

        return $query;
    }

    /** Eventos em que o usuário gere trabalhos. */
    public function eventos(Request $request)
    {
        $eventos = Evento::gestaoTrabalhosPor($request->user())
            ->withCount(['trabalhos as total_trabalhos' => fn ($q) => $q->where('status', '!=', Trabalho::RASCUNHO)])
            ->orderByDesc('data_inicio')
            ->get();

        return view('gestao.trabalhos.eventos', compact('eventos'));
    }

    public function index(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $query = $this->consulta($request, $evento);

        $trabalhos = $query->latest('submetido_em')->paginate(20)->withQueryString();

        $contagem = $evento->trabalhos()->where('status', '!=', Trabalho::RASCUNHO)
            ->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        return view('gestao.trabalhos.index', [
            'evento' => $evento,
            'trabalhos' => $trabalhos,
            'contagem' => $contagem,
            'areas' => $evento->areasTematicas,
            'config' => $evento->trabalhoConfig,
            'avaliadores' => $this->workflow->avaliadoresDoEvento($evento),
            'statusTriagem' => array_keys(TrabalhoWorkflow::TRANSICOES_GESTAO),
        ]);
    }

    public function show(Trabalho $trabalho)
    {
        $this->authorize('view', $trabalho);
        abort_if($trabalho->status === Trabalho::RASCUNHO && $trabalho->submetido_por !== auth()->id(), 404);

        $trabalho->load(['evento.trabalhoConfig', 'autores', 'arquivos', 'historico.usuario', 'area', 'submetente', 'avaliacoes.avaliador']);

        $atribuidos = $trabalho->avaliacoes->pluck('avaliador_id');
        $avaliadores = $this->workflow->avaliadoresDoEvento($trabalho->evento)
            ->reject(fn ($u) => $atribuidos->contains($u->id))
            ->each(fn ($u) => $u->conflito = $this->workflow->conflitoDeInteresse($trabalho, $u));

        $acoesTriagem = collect(TrabalhoWorkflow::TRANSICOES_GESTAO[$trabalho->status] ?? []);
        $podeAtribuir = in_array($trabalho->status, TrabalhoWorkflow::STATUS_ATRIBUICAO, true);

        return view('gestao.trabalhos.show', compact('trabalho', 'avaliadores', 'acoesTriagem', 'podeAtribuir'));
    }

    // ------------------------------------------------------------------
    // Triagem e avaliadores
    // ------------------------------------------------------------------

    public function triagem(Request $request, Trabalho $trabalho)
    {
        $this->authorize('gerirTrabalhos', $trabalho->evento);

        $dados = $request->validate([
            'para' => ['required', Rule::in(['em_triagem', 'devolvido_triagem', 'rejeitado_triagem', 'em_avaliacao'])],
            'justificativa' => ['nullable', 'string', 'max:3000'],
        ]);

        try {
            $this->workflow->transicionar($trabalho, $request->user(), $dados['para'], $dados['justificativa'] ?? null);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Status atualizado: ' . (Trabalho::STATUS[$dados['para']] ?? $dados['para']) . '.');
    }

    public function atribuir(Request $request, Trabalho $trabalho)
    {
        $this->authorize('gerirTrabalhos', $trabalho->evento);

        $dados = $request->validate([
            'avaliadores' => ['required', 'array', 'min:1'],
            'avaliadores.*' => ['integer', 'exists:users,id'],
            'prazo' => ['nullable', 'date', 'after:today'],
        ], ['avaliadores.required' => 'Selecione ao menos um avaliador.']);

        [$ok, $erros] = $this->atribuirVarios($trabalho, $request, $dados['avaliadores'], $dados['prazo'] ?? null);

        return back()
            ->with($ok ? 'success' : 'error', $ok ? "{$ok} avaliador(es) atribuído(s)." : 'Nenhuma atribuição realizada.')
            ->with('erros_lote', $erros);
    }

    public function removerAvaliador(Request $request, Trabalho $trabalho, TrabalhoAvaliacao $avaliacao)
    {
        $this->authorize('gerirTrabalhos', $trabalho->evento);
        abort_unless($avaliacao->trabalho_id === $trabalho->id, 404);

        try {
            $this->workflow->removerAvaliacao($avaliacao, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Avaliador removido do trabalho.');
    }

    /** Ação em lote sobre trabalhos selecionados na fila: iniciar triagem ou atribuir avaliadores. */
    public function lote(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $dados = $request->validate([
            'acao' => ['required', Rule::in(['triagem', 'atribuir'])],
            'trabalhos' => ['required', 'array', 'min:1'],
            'trabalhos.*' => ['integer'],
            'avaliadores' => ['required_if:acao,atribuir', 'array'],
            'avaliadores.*' => ['integer', 'exists:users,id'],
            'prazo' => ['nullable', 'date', 'after:today'],
        ], ['trabalhos.required' => 'Selecione ao menos um trabalho.', 'avaliadores.required_if' => 'Selecione ao menos um avaliador.']);

        // Somente trabalhos deste evento (ignora ids de outros eventos)
        $trabalhos = $evento->trabalhos()->whereIn('id', $dados['trabalhos'])->with('evento.trabalhoConfig')->get();

        $ok = 0;
        $erros = [];

        foreach ($trabalhos as $trabalho) {
            if ($dados['acao'] === 'triagem') {
                try {
                    $this->workflow->transicionar($trabalho, $request->user(), 'em_triagem');
                    $ok++;
                } catch (\DomainException $e) {
                    $erros[] = "{$trabalho->codigo}: {$e->getMessage()}";
                }
            } else {
                [$feitos, $falhas] = $this->atribuirVarios($trabalho, $request, $dados['avaliadores'], $dados['prazo'] ?? null);
                $ok += $feitos;
                $erros = array_merge($erros, $falhas);
            }
        }

        return back()
            ->with($ok ? 'success' : 'error', $ok ? "{$ok} operação(ões) realizada(s)." : 'Nenhuma operação realizada.')
            ->with('erros_lote', $erros);
    }

    /** @return array{0:int,1:array<int,string>} [quantidade atribuída, mensagens de erro] */
    private function atribuirVarios(Trabalho $trabalho, Request $request, array $ids, ?string $prazo): array
    {
        $ok = 0;
        $erros = [];
        $prazo = $prazo ? Carbon::parse($prazo)->endOfDay() : null;

        foreach (\App\Models\User::whereIn('id', $ids)->get() as $avaliador) {
            try {
                $this->workflow->atribuirAvaliador($trabalho, $avaliador, $request->user(), $prazo);
                $ok++;
            } catch (\DomainException $e) {
                $erros[] = "{$trabalho->codigo}: {$e->getMessage()}";
            }
        }

        return [$ok, $erros];
    }

    public function exportar(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $formato = $request->input('formato') === 'csv' ? 'csv' : 'xlsx';
        $trabalhos = $this->consulta($request, $evento)->latest('submetido_em')->get();
        $nome = 'trabalhos-' . \Illuminate\Support\Str::slug($evento->titulo) . '-' . now()->format('Ymd_His') . '.' . $formato;

        return Excel::download(
            new TrabalhosExport($trabalhos),
            $nome,
            $formato === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function config(Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $config = $evento->trabalhoConfig ?? new TrabalhoConfig(['evento_id' => $evento->id]);
        $areas = $evento->areasTematicas->pluck('nome')->implode("\n");

        return view('gestao.trabalhos.config', compact('evento', 'config', 'areas'));
    }

    public function updateConfig(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $dados = $request->validate([
            'ativo' => ['nullable', 'boolean'],
            'submissao_inicio' => ['nullable', 'date'],
            'submissao_fim' => ['nullable', 'date', 'after_or_equal:submissao_inicio'],
            'avaliacao_fim' => ['nullable', 'date'],
            'avaliadores_por_trabalho' => ['required', 'integer', 'min:1', 'max:10'],
            'max_autores' => ['required', 'integer', 'min:1', 'max:30'],
            'max_arquivo_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'resumo_min_palavras' => ['required', 'integer', 'min:0', 'max:2000'],
            'resumo_max_palavras' => ['required', 'integer', 'min:1', 'max:5000', 'gte:resumo_min_palavras'],
            'max_trabalhos_por_usuario' => ['nullable', 'integer', 'min:1', 'max:50'],
            'avaliacao_cega' => ['nullable', 'boolean'],
            'tipos_permitidos' => ['required', 'array', 'min:1'],
            'tipos_permitidos.*' => [Rule::in(array_keys(TrabalhoConfig::TIPOS))],
            'regras_texto' => ['nullable', 'string', 'max:10000'],
            'areas' => ['nullable', 'string', 'max:5000'],
        ], [
            'tipos_permitidos.required' => 'Selecione ao menos um tipo de trabalho.',
            'resumo_max_palavras.gte' => 'O máximo de palavras deve ser maior ou igual ao mínimo.',
            'submissao_fim.after_or_equal' => 'O fim da submissão deve ser posterior ao início.',
        ]);

        DB::transaction(function () use ($evento, $dados) {
            TrabalhoConfig::updateOrCreate(['evento_id' => $evento->id], [
                'ativo' => (bool) ($dados['ativo'] ?? false),
                'submissao_inicio' => $dados['submissao_inicio'] ?? null,
                'submissao_fim' => $dados['submissao_fim'] ?? null,
                'avaliacao_fim' => $dados['avaliacao_fim'] ?? null,
                'avaliadores_por_trabalho' => $dados['avaliadores_por_trabalho'],
                'max_autores' => $dados['max_autores'],
                'max_arquivo_mb' => $dados['max_arquivo_mb'],
                'resumo_min_palavras' => $dados['resumo_min_palavras'],
                'resumo_max_palavras' => $dados['resumo_max_palavras'],
                'max_trabalhos_por_usuario' => $dados['max_trabalhos_por_usuario'] ?? null,
                'avaliacao_cega' => (bool) ($dados['avaliacao_cega'] ?? false),
                'tipos_permitidos' => array_values($dados['tipos_permitidos']),
                'regras_texto' => $dados['regras_texto'] ?? null,
            ]);

            $this->sincronizarAreas($evento, $dados['areas'] ?? '');
        });

        return redirect()->route('gestao.trabalhos.config', $evento)->with('success', 'Configuração da chamada salva.');
    }

    /** Uma área por linha; mantém as existentes (preserva vínculos), cria novas e remove as retiradas. */
    private function sincronizarAreas(Evento $evento, string $texto): void
    {
        $nomes = collect(preg_split('/\R/', $texto))->map(fn ($n) => trim($n))->filter()->unique()->values();

        $existentes = $evento->areasTematicas()->get()->keyBy('nome');

        foreach ($nomes as $ordem => $nome) {
            if ($area = $existentes->get($nome)) {
                $area->update(['ordem' => $ordem]);
            } else {
                AreaTematica::create(['evento_id' => $evento->id, 'nome' => $nome, 'ordem' => $ordem]);
            }
        }

        $evento->areasTematicas()->whereNotIn('nome', $nomes->all())->delete();
    }
}
