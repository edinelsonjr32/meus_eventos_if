<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\AreaTematica;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Gestão de trabalhos do evento (gestor de trabalhos, dono e co-administradores).
 */
class TrabalhoGestaoController extends Controller
{
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

        $query = $evento->trabalhos()
            ->where('status', '!=', Trabalho::RASCUNHO) // rascunhos são privados do autor
            ->with(['autores', 'area', 'arquivoAtual']);

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

        $trabalhos = $query->latest('submetido_em')->paginate(20)->withQueryString();

        $contagem = $evento->trabalhos()->where('status', '!=', Trabalho::RASCUNHO)
            ->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        return view('gestao.trabalhos.index', [
            'evento' => $evento,
            'trabalhos' => $trabalhos,
            'contagem' => $contagem,
            'areas' => $evento->areasTematicas,
            'config' => $evento->trabalhoConfig,
        ]);
    }

    public function show(Trabalho $trabalho)
    {
        $this->authorize('view', $trabalho);
        abort_if($trabalho->status === Trabalho::RASCUNHO && $trabalho->submetido_por !== auth()->id(), 404);

        $trabalho->load(['evento.trabalhoConfig', 'autores', 'arquivos', 'historico.usuario', 'area', 'submetente']);

        return view('gestao.trabalhos.show', compact('trabalho'));
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
