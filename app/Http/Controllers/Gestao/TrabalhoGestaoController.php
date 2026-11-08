<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Support\ResultadoLote;
use App\Models\AreaTematica;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Exports\NotasExport;
use App\Exports\TrabalhosExport;
use App\Services\RelatorioNotas;
use App\Http\Requests\GestaoSalvarTrabalhoRequest;
use App\Models\CriterioAvaliacao;
use App\Models\User;
use App\Support\FormularioTrabalho;
use App\Models\TrabalhoAvaliacao;
use App\Services\AvaliacaoService;
use App\Services\CertificadoTrabalhoService;
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
    public function __construct(private TrabalhoWorkflow $workflow, private AvaliacaoService $avaliacao, private CertificadoTrabalhoService $certificados)
    {
    }

    /** Consulta de trabalhos submetidos com os filtros da fila (usada na lista e na exportação). */
    private function consulta(Request $request, Evento $evento)
    {
        $query = $evento->trabalhos()
            ->where('status', '!=', Trabalho::RASCUNHO) // rascunhos são privados do autor
            ->with(['autores', 'area', 'arquivoAtual', 'avaliacoes', 'evento.trabalhoConfig']);

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
            ->with('trabalhoConfig')
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

        $avaliadores = $this->workflow->avaliadoresDoEvento($evento);

        return view('gestao.trabalhos.index', [
            'evento' => $evento,
            'trabalhos' => $trabalhos,
            'restricoes' => $this->workflow->restricoesAvaliadores($trabalhos->getCollection(), $avaliadores),
            'contagem' => $contagem,
            'areas' => $evento->areasTematicas,
            'config' => $evento->trabalhoConfig,
            'avaliadores' => $avaliadores,
            'statusTriagem' => array_keys(TrabalhoWorkflow::TRANSICOES_GESTAO),
        ]);
    }

    public function show(Trabalho $trabalho)
    {
        // Somente a gestão: esta tela mostra avaliadores, notas e comentários confidenciais (o autor usa trabalhos.show)
        $this->authorize('gerirTrabalhos', $trabalho->evento);
        abort_if($trabalho->status === Trabalho::RASCUNHO, 404);   // rascunhos são privados do autor

        $trabalho->load(['evento.trabalhoConfig', 'autores', 'arquivos', 'historico.usuario', 'area', 'submetente', 'avaliacoes.avaliador']);

        // Todos os avaliadores do evento, cada um com seu impedimento (já atribuído, recusou ou conflito) para dar feedback na seleção
        $avaliadores = $this->workflow->avaliadoresDoEvento($trabalho->evento);
        $impedimentos = $this->workflow->restricoesAvaliadores(collect([$trabalho]), $avaliadores)[$trabalho->id] ?? [];
        $avaliadores->each(function ($u) use ($impedimentos) {
            $u->impedimento = $impedimentos[$u->id] ?? null;
            $u->conflito = ($u->impedimento['tipo'] ?? null) === 'conflito' ? $u->impedimento['motivo'] : null;
        });

        $acoesTriagem = collect(TrabalhoWorkflow::TRANSICOES_GESTAO[$trabalho->status] ?? []);
        $podeAtribuir = in_array($trabalho->status, TrabalhoWorkflow::STATUS_ATRIBUICAO, true);
        [$atribuidosNaRodada, $minimoAvaliadores] = $this->workflow->avaliadoresMinimos($trabalho);

        $trabalho->load(['avaliacoes.notas.criterio', 'certificados.autor', 'sessao']);
        $certificacao = [
            'atividades' => $trabalho->evento->atividades()->orderBy('data_inicio')->get(),
            'motivo' => $this->certificados->motivoInelegivel($trabalho),
            'exigeApresentacao' => $trabalho->evento->trabalhoConfig?->exige_apresentacao_para_certificado ?? true,
        ];
        $rodada = $trabalho->rodadaAtual();
        $resultado = [
            'rodada' => $rodada,
            'media' => $this->avaliacao->notaMedia($trabalho),
            'divergencia' => $this->avaliacao->divergencia($trabalho),
            'divergenciaAlta' => $this->avaliacao->divergenciaAlta($trabalho),
            'concluidas' => $this->avaliacao->concluidas($trabalho)->count(),
            'podeEncerrar' => $trabalho->status === 'em_avaliacao' && $this->avaliacao->concluidas($trabalho)->isNotEmpty(),
            'podeDecidir' => in_array($trabalho->status, ['avaliado', 'resubmetido'], true),
            'podeReavaliar' => $trabalho->status === 'resubmetido' && $trabalho->avaliacoes->where('status', '!=', 'recusada')->isNotEmpty(),
            'notaMinima' => $trabalho->evento->trabalhoConfig?->nota_minima_aprovacao ?? 7,
        ];

        return view('gestao.trabalhos.show', compact('trabalho', 'avaliadores', 'acoesTriagem', 'podeAtribuir', 'resultado', 'certificacao', 'atribuidosNaRodada', 'minimoAvaliadores'));
    }

    // ------------------------------------------------------------------
    // Cadastro e edição pela gestão (em nome dos autores)
    // ------------------------------------------------------------------

    /** Cadastrar um trabalho recebido por outro meio (e-mail, papel...). */
    public function novo(Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        return view('trabalhos.form', FormularioTrabalho::dados($evento, new Trabalho(['status' => Trabalho::RASCUNHO]), null) + ['modoGestao' => true]);
    }

    public function criar(GestaoSalvarTrabalhoRequest $request, Evento $evento)
    {
        $dados = $request->validated();
        $responsavel = null;

        if (filled($dados['responsavel_email'] ?? null)) {
            $responsavel = User::where('email', mb_strtolower($dados['responsavel_email']))->first();
            if (!$responsavel) {
                return back()->withInput()->with('error', 'Não há conta com o e-mail do responsável informado. Deixe o campo em branco para registrar em seu nome ou peça que a pessoa crie a conta.');
            }
        }

        // Sem conta do autor, o gestor figura como responsável pela submissão (o apresentador recebe os e-mails)
        $dono = $responsavel ?? $request->user();

        $trabalho = $this->workflow->salvar($evento, $dono, $dados, null, $request->file('arquivo'));
        $trabalho = $this->workflow->submeter($trabalho, $dono);
        $this->workflow->registrar($trabalho, $request->user(), $trabalho->status, $trabalho->status, 'Cadastrado pela gestão em nome dos autores');

        return redirect()->route('gestao.trabalhos.show', $trabalho)->with('success', "Trabalho cadastrado. Protocolo {$trabalho->codigo}.");
    }

    /** Corrigir dados (ex.: nome para o certificado) ou substituir o PDF, em qualquer fase. */
    public function editar(Trabalho $trabalho)
    {
        $this->authorize('gerirTrabalhos', $trabalho->evento);

        if (in_array($trabalho->status, [Trabalho::RASCUNHO, Trabalho::RETIRADO], true)) {
            return redirect()->route('gestao.trabalhos.index', $trabalho->evento)->with('error', 'Trabalhos em rascunho ou retirados não podem ser editados pela gestão.');
        }

        $trabalho->load(['autores', 'arquivoAtual']);

        return view('trabalhos.form', FormularioTrabalho::dados($trabalho->evento, $trabalho, null) + ['modoGestao' => true]);
    }

    public function atualizar(GestaoSalvarTrabalhoRequest $request, Trabalho $trabalho)
    {
        if (in_array($trabalho->status, [Trabalho::RASCUNHO, Trabalho::RETIRADO], true)) {
            abort(404);
        }

        $dados = $request->validated();

        // Não remover autor que já tem certificado válido: revogue o certificado antes
        $bloqueados = $this->workflow->autoresComCertificadoRemovidos($trabalho, $dados['autores'] ?? []);
        if ($bloqueados->isNotEmpty()) {
            return back()->withInput()->with('error', 'Não é possível remover autor(es) com certificado emitido: ' . $bloqueados->pluck('nome_completo')->implode(', ') . '. Revogue o certificado antes.');
        }

        $this->workflow->salvar($trabalho->evento, $request->user(), $dados, $trabalho, $request->file('arquivo'));
        $this->workflow->registrar(
            $trabalho, $request->user(), $trabalho->status, $trabalho->status,
            'Dados editados pela gestão' . ($request->hasFile('arquivo') ? ' (PDF substituído)' : '')
        );

        return redirect()->route('gestao.trabalhos.show', $trabalho)->with('success', 'Dados do trabalho atualizados. Certificados já emitidos passam a refletir as correções.');
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

        return $this->atribuirVarios($trabalho, $request, $dados['avaliadores'], $dados['prazo'] ?? null)->aplicar(back(), 'atribuição(ões)');
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

    // ------------------------------------------------------------------
    // Decisão
    // ------------------------------------------------------------------

    public function encerrarAvaliacao(Request $request, Trabalho $trabalho)
    {
        $this->authorize('gerirTrabalhos', $trabalho->evento);

        try {
            $this->avaliacao->encerrar($trabalho, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Avaliação encerrada. O trabalho está pronto para decisão.');
    }

    public function decisao(Request $request, Trabalho $trabalho)
    {
        $this->authorize('gerirTrabalhos', $trabalho->evento);

        $dados = $request->validate([
            'decisao' => ['required', Rule::in(array_keys(AvaliacaoService::DECISOES))],
            'justificativa' => ['nullable', 'string', 'max:5000'],
            'prazo_correcao' => ['nullable', 'date', 'after:today'],
        ]);

        try {
            $this->avaliacao->decidir(
                $trabalho, $request->user(), $dados['decisao'], $dados['justificativa'] ?? null,
                !empty($dados['prazo_correcao']) ? Carbon::parse($dados['prazo_correcao'])->endOfDay() : null
            );
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Decisão registrada e autores avisados por e-mail.');
    }

    public function prorrogarCorrecao(Request $request, Trabalho $trabalho)
    {
        $this->authorize('gerirTrabalhos', $trabalho->evento);

        $dados = $request->validate(['prazo_correcao' => ['required', 'date', 'after:today']]);

        try {
            $this->avaliacao->prorrogarCorrecao($trabalho, $request->user(), Carbon::parse($dados['prazo_correcao'])->endOfDay());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Prazo de correção prorrogado.');
    }

    public function reavaliar(Request $request, Trabalho $trabalho)
    {
        $this->authorize('gerirTrabalhos', $trabalho->evento);

        try {
            $this->avaliacao->reavaliar($trabalho, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Nova rodada iniciada: os avaliadores foram notificados.');
    }

    /** Ação em lote sobre trabalhos selecionados na fila: iniciar triagem ou atribuir avaliadores. */
    public function lote(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $dados = $request->validate([
            'acao' => ['required', Rule::in(['triagem', 'atribuir', 'avaliacao'])],
            'trabalhos' => ['required', 'array', 'min:1'],
            'trabalhos.*' => ['integer'],
            'avaliadores' => ['required_if:acao,atribuir', 'array'],
            'avaliadores.*' => ['integer', 'exists:users,id'],
            'prazo' => ['nullable', 'date', 'after:today'],
        ], ['trabalhos.required' => 'Selecione ao menos um trabalho.', 'avaliadores.required_if' => 'Selecione ao menos um avaliador.']);

        // Somente trabalhos deste evento (ignora ids de outros eventos)
        $trabalhos = $evento->trabalhos()->whereIn('id', $dados['trabalhos'])->with('evento.trabalhoConfig')->get();

        $resultado = new ResultadoLote();

        foreach ($trabalhos as $trabalho) {
            if ($dados['acao'] === 'triagem') {
                // "Iniciar triagem" em lote nunca cancela envios: só vale para submetidos/resubmetidos
                if (!in_array($trabalho->status, [Trabalho::SUBMETIDO, 'resubmetido'], true)) {
                    $resultado->ignorar($trabalho->codigo, "Não está aguardando triagem (status: {$trabalho->statusRotulo()})");
                    continue;
                }
                try {
                    $this->workflow->transicionar($trabalho, $request->user(), 'em_triagem');
                    $resultado->ok();
                } catch (\DomainException $e) {
                    $resultado->ignorar($trabalho->codigo, $e->getMessage());
                }
            } elseif ($dados['acao'] === 'avaliacao') {
                // Envio em massa para avaliação: só trabalhos em triagem que já têm o mínimo de avaliadores da chamada
                if ($trabalho->status !== 'em_triagem') {
                    $resultado->ignorar($trabalho->codigo, "Não está em triagem (status: {$trabalho->statusRotulo()})");
                    continue;
                }
                [$atribuidos, $minimo] = $this->workflow->avaliadoresMinimos($trabalho);
                if ($atribuidos < $minimo) {
                    $resultado->ignorar("{$trabalho->codigo} ({$atribuidos}/{$minimo})", "Avaliadores insuficientes: a chamada exige no mínimo {$minimo}");
                    continue;
                }
                try {
                    $this->workflow->transicionar($trabalho, $request->user(), 'em_avaliacao');
                    $resultado->ok();
                } catch (\DomainException $e) {
                    $resultado->ignorar($trabalho->codigo, $e->getMessage());
                }
            } else {
                $resultado->mesclar($this->atribuirVarios($trabalho, $request, $dados['avaliadores'], $dados['prazo'] ?? null));
            }
        }

        // Ids que não pertencem ao evento (ou não existem) são reportados, nunca ignorados em silêncio
        $faltando = count($dados['trabalhos']) - $trabalhos->count();
        if ($faltando > 0) {
            $resultado->ignorar("{$faltando} item(ns)", 'Trabalho não encontrado neste evento');
        }

        return $resultado->aplicar(back(), match ($dados['acao']) {
            'triagem' => 'triagem(ns) iniciada(s)',
            'avaliacao' => 'trabalho(s) encaminhado(s) para avaliação',
            default => 'atribuição(ões)',
        });
    }

    /** Atribui os avaliadores ao trabalho; cada recusa (conflito, duplicado, status) vira um item ignorado com motivo. */
    private function atribuirVarios(Trabalho $trabalho, Request $request, array $ids, ?string $prazo): ResultadoLote
    {
        $resultado = new ResultadoLote();
        $prazo = $prazo ? Carbon::parse($prazo)->endOfDay() : null;

        foreach (\App\Models\User::whereIn('id', $ids)->get() as $avaliador) {
            try {
                $this->workflow->atribuirAvaliador($trabalho, $avaliador, $request->user(), $prazo);
                $resultado->ok();
            } catch (\DomainException $e) {
                $resultado->ignorar($trabalho->codigo, $e->getMessage());
            }
        }

        return $resultado;
    }

    /** Ranking de notas dos trabalhos (somente gestão). */
    public function ranking(Request $request, Evento $evento, RelatorioNotas $relatorio)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $filtros = RelatorioNotas::filtros($request);

        return view('gestao.trabalhos.ranking', [
            'evento' => $evento,
            'linhas' => $relatorio->resumo($evento, $filtros),
            'criterios' => $relatorio->criterios($evento),
            'areas' => $evento->areasTematicas,
            'limiteDivergencia' => $evento->trabalhoConfig?->divergencia_maxima ?? 3,
            'notaMinima' => $evento->trabalhoConfig?->nota_minima_aprovacao ?? 7,
        ]);
    }

    /** Exporta notas: relatorio=resumo (ranking) ou detalhado (por avaliação, com pareceres); formato=xlsx|csv. ("tipo" é o filtro de categoria.) */
    public function exportarNotas(Request $request, Evento $evento, RelatorioNotas $relatorio)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $tipo = $request->input('relatorio') === 'detalhado' ? 'detalhado' : 'resumo';
        $formato = $request->input('formato') === 'csv' ? 'csv' : 'xlsx';
        $filtros = RelatorioNotas::filtros($request);

        $linhas = $tipo === 'detalhado' ? $relatorio->detalhado($evento, $filtros) : $relatorio->resumo($evento, $filtros);
        $nome = "notas-{$tipo}-" . \Illuminate\Support\Str::slug($evento->titulo) . '-' . now()->format('Ymd_His') . ".{$formato}";

        return Excel::download(
            new NotasExport($linhas, $relatorio->criterios($evento), $tipo),
            $nome,
            $formato === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
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
        $criterios = $evento->criterios()->where('ativo', true)->get();
        // Critérios com notas lançadas não mudam de nome/peso/máximo, mas podem ser removidos (ficam desativados, preservando as notas)
        $criteriosUsados = \App\Models\TrabalhoAvaliacaoNota::whereIn('criterio_id', $criterios->pluck('id'))->pluck('criterio_id')->unique()->values()->all();

        return view('gestao.trabalhos.config', compact('evento', 'config', 'areas', 'criterios', 'criteriosUsados'));
    }

    public function updateConfig(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $dados = $request->validate([
            'acao' => ['nullable', Rule::in(['salvar', 'publicar'])],
            'ativo' => ['nullable', 'boolean'],
            'submissao_inicio' => ['nullable', 'date'],
            'submissao_fim' => ['nullable', 'date', 'after_or_equal:submissao_inicio'],
            'avaliacao_fim' => ['nullable', 'date'],
            'avaliadores_por_trabalho' => ['required', 'integer', 'min:1', 'max:10'],
            'nota_minima_aprovacao' => ['required', 'numeric', 'min:0', 'max:10'],
            'divergencia_maxima' => ['required', 'numeric', 'min:0', 'max:10'],
            'autor_ve_notas' => ['nullable', 'boolean'],
            'autor_ve_pareceres' => ['nullable', 'boolean'],
            'exige_apresentacao_para_certificado' => ['nullable', 'boolean'],
            'cert_trab_cabecalho' => ['nullable', 'string', 'max:1000'],
            'cert_trab_corpo' => ['nullable', 'string', 'max:5000'],
            'cert_trab_rodape' => ['nullable', 'string', 'max:1000'],
            'criterios' => ['nullable', 'array', 'max:15'],
            'criterios.*.id' => ['nullable', 'integer'],
            'criterios.*.nome' => ['required', 'string', 'max:255'],
            'criterios.*.descricao' => ['nullable', 'string', 'max:1000'],
            'criterios.*.peso' => ['required', 'numeric', 'gt:0', 'max:100'],
            'criterios.*.nota_max' => ['required', 'numeric', 'gt:0', 'max:100'],
            'max_autores' => ['required', 'integer', 'min:1', 'max:30'],
            'max_arquivo_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'resumo_min_palavras' => ['required', 'integer', 'min:0', 'max:2000'],
            'resumo_max_palavras' => ['required', 'integer', 'min:1', 'max:5000', 'gte:resumo_min_palavras'],
            'max_trabalhos_por_usuario' => ['nullable', 'integer', 'min:1', 'max:50'],
            'avaliacao_cega' => ['nullable', 'boolean'],
            'tipos_permitidos' => ['required', 'array', 'min:1'],
            'tipos_permitidos.*' => [Rule::in(array_keys(TrabalhoConfig::TIPOS))],
            'regras_texto' => ['nullable', 'string', 'max:10000'],
            'chamada_tema' => ['nullable', 'string', 'max:255'],
            'chamada_intro' => ['nullable', 'string', 'max:5000'],
            'template_url' => ['nullable', 'url', 'max:500'],
            'exige_resumo' => ['nullable', 'boolean'],
            'exige_palavras_chave' => ['nullable', 'boolean'],
            'exige_cpf_apresentador' => ['nullable', 'boolean'],
            'areas' => ['nullable', 'string', 'max:5000'],
        ], [
            'tipos_permitidos.required' => 'Selecione ao menos uma categoria de trabalho.',
            'template_url.url' => 'O link do template deve ser uma URL completa (https://...).',
            'resumo_max_palavras.gte' => 'O máximo de palavras deve ser maior ou igual ao mínimo.',
            'submissao_fim.after_or_equal' => 'O fim da submissão deve ser posterior ao início.',
        ]);

        $eraNova = !$evento->trabalhoConfig()->exists();

        DB::transaction(function () use ($evento, $dados) {
            TrabalhoConfig::updateOrCreate(['evento_id' => $evento->id], [
                // "Salvar e publicar" abre a chamada; "Salvar" respeita a chave "Chamada publicada"
                'ativo' => ($dados['acao'] ?? 'salvar') === 'publicar' ? true : (bool) ($dados['ativo'] ?? false),
                'submissao_inicio' => $dados['submissao_inicio'] ?? null,
                'submissao_fim' => $dados['submissao_fim'] ?? null,
                'avaliacao_fim' => $dados['avaliacao_fim'] ?? null,
                'avaliadores_por_trabalho' => $dados['avaliadores_por_trabalho'],
                'nota_minima_aprovacao' => $dados['nota_minima_aprovacao'],
                'divergencia_maxima' => $dados['divergencia_maxima'],
                'autor_ve_notas' => (bool) ($dados['autor_ve_notas'] ?? false),
                'autor_ve_pareceres' => (bool) ($dados['autor_ve_pareceres'] ?? false),
                // Padrão seguro: sem o campo, mantém o valor atual (ou "exigir" numa chamada nova), nunca "não exigir"
                'exige_apresentacao_para_certificado' => array_key_exists('exige_apresentacao_para_certificado', $dados)
                    ? (bool) $dados['exige_apresentacao_para_certificado']
                    : (bool) ($evento->trabalhoConfig?->exige_apresentacao_para_certificado ?? true),
                'cert_trab_cabecalho' => $dados['cert_trab_cabecalho'] ?? null,
                'cert_trab_corpo' => $dados['cert_trab_corpo'] ?? null,
                'cert_trab_rodape' => $dados['cert_trab_rodape'] ?? null,
                'max_autores' => $dados['max_autores'],
                'max_arquivo_mb' => $dados['max_arquivo_mb'],
                'resumo_min_palavras' => $dados['resumo_min_palavras'],
                'resumo_max_palavras' => $dados['resumo_max_palavras'],
                'max_trabalhos_por_usuario' => $dados['max_trabalhos_por_usuario'] ?? null,
                'avaliacao_cega' => (bool) ($dados['avaliacao_cega'] ?? false),
                'tipos_permitidos' => array_values($dados['tipos_permitidos']),
                'regras_texto' => $dados['regras_texto'] ?? null,
                'chamada_tema' => $dados['chamada_tema'] ?? null,
                'chamada_intro' => $dados['chamada_intro'] ?? null,
                'template_url' => $dados['template_url'] ?? null,
                'exige_resumo' => (bool) ($dados['exige_resumo'] ?? false),
                'exige_palavras_chave' => (bool) ($dados['exige_palavras_chave'] ?? false),
                'exige_cpf_apresentador' => (bool) ($dados['exige_cpf_apresentador'] ?? false),
            ]);

            $this->sincronizarAreas($evento, $dados['areas'] ?? '');
            $avisoCriterios = $this->sincronizarCriterios($evento, $dados['criterios'] ?? null);
            if ($avisoCriterios) {
                session()->flash('warning', $avisoCriterios);
            }
        });

        $config = $evento->trabalhoConfig()->first();
        $situacao = TrabalhoConfig::situacaoDe($config);

        $mensagem = match (true) {
            $eraNova && $config->ativo => 'Chamada criada e publicada! ' . $situacao['descricao'],
            $eraNova => 'Chamada criada como rascunho. Quando estiver pronta, clique em "Salvar e publicar chamada".',
            ($dados['acao'] ?? '') === 'publicar' => 'Chamada publicada. ' . $situacao['descricao'],
            default => 'Configuração da chamada salva. Situação: ' . mb_strtolower($situacao['rotulo']) . '.',
        };

        return redirect()->route('gestao.trabalhos.config', $evento)->with('success', $mensagem);
    }

    /**
     * Critérios de avaliação do evento. Depois da primeira nota lançada ficam bloqueados.
     * `null` (campo ausente) mantém os critérios como estão.
     */
    private function sincronizarCriterios(Evento $evento, ?array $linhas): ?string
    {
        if ($linhas === null) {
            return null;
        }

        $existentes = $evento->criterios()->get()->keyBy('id');
        $usados = \App\Models\TrabalhoAvaliacaoNota::whereIn('criterio_id', $existentes->keys())->pluck('criterio_id')->unique()->flip();

        $mantidos = [];
        foreach (array_values($linhas) as $ordem => $linha) {
            $criterio = !empty($linha['id']) ? $existentes->get((int) $linha['id']) : null;

            if ($criterio && $usados->has($criterio->id)) {
                // Já tem notas: preserva nome, peso e máximo (as notas lançadas dependem deles)
                $criterio->update(['descricao' => $linha['descricao'] ?? null, 'ordem' => $ordem, 'ativo' => true]);
            } else {
                $campos = ['nome' => $linha['nome'], 'descricao' => $linha['descricao'] ?? null, 'peso' => $linha['peso'], 'nota_max' => $linha['nota_max'], 'ordem' => $ordem, 'ativo' => true];
                $criterio = $criterio ? tap($criterio)->update($campos) : CriterioAvaliacao::create($campos + ['evento_id' => $evento->id]);
            }
            $mantidos[] = $criterio->id;
        }

        // Removidos: sem notas são apagados; com notas ficam desativados (histórico preservado)
        $removidos = $existentes->reject(fn ($c) => in_array($c->id, $mantidos, true))->filter(fn ($c) => $c->ativo);
        $desativados = 0;
        foreach ($removidos as $c) {
            if ($usados->has($c->id)) {
                $c->update(['ativo' => false]);
                $desativados++;
            } else {
                $c->delete();
            }
        }
        $evento->criterios()->where('ativo', false)->whereNotIn('id', $usados->keys())->delete();

        return $desativados ? "{$desativados} critério(s) com notas lançadas foram retirados da avaliação. As notas já lançadas continuam valendo; apenas as próximas avaliações deixam de usá-los." : null;
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
