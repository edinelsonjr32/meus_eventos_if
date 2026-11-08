<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\ApresentacaoAvaliacao;
use App\Models\ApresentacaoAvaliacaoNota;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\User;
use App\Services\AvaliacaoApresentacaoService;
use App\Services\TrabalhoWorkflow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Gestão da avaliação das apresentações: critérios, peso, atribuição de avaliadores (individual ou em lote) e acompanhamento. */
class ApresentacaoAvaliacaoGestaoController extends Controller
{
    public function __construct(private AvaliacaoApresentacaoService $servico, private TrabalhoWorkflow $workflow)
    {
    }

    public function index(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $config = $evento->trabalhoConfig;
        $atividadeId = $request->integer('atividade') ?: null;

        $trabalhos = $evento->trabalhos()->where('status', 'aprovado')
            ->when($atividadeId, fn ($q) => $q->where('atividade_id', $atividadeId))
            ->with(['sessao', 'avaliacoesApresentacao.avaliador', 'autores'])
            ->orderBy('atividade_id')->orderBy('codigo')->get();

        $criterios = $evento->criteriosApresentacao()->where('ativo', true)->get();

        return view('gestao.trabalhos.apresentacoes', [
            'evento' => $evento,
            'config' => $config,
            'trabalhos' => $trabalhos,
            'atividades' => $evento->atividades()->orderBy('data_inicio')->get(),
            'atividadeId' => $atividadeId,
            'criterios' => $criterios,
            'criteriosUsados' => ApresentacaoAvaliacaoNota::whereIn('criterio_id', $criterios->pluck('id'))->pluck('criterio_id')->unique()->values()->all(),
            'avaliadores' => $this->workflow->avaliadoresDoEvento($evento),
        ]);
    }

    public function salvarConfig(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);
        abort_unless($evento->trabalhoConfig, 404, 'Crie a chamada de trabalhos antes de configurar a avaliação das apresentações.');

        $dados = $request->validate([
            'avaliacao_apresentacao_ativa' => ['nullable', 'boolean'],
            'peso_apresentacao' => ['required', 'integer', 'min:0', 'max:100'],
            'autor_ve_nota_apresentacao' => ['nullable', 'boolean'],
            'criterios' => ['nullable', 'array', 'max:15'],
            'criterios.*.id' => ['nullable', 'integer'],
            'criterios.*.nome' => ['required', 'string', 'max:255'],
            'criterios.*.descricao' => ['nullable', 'string', 'max:1000'],
            'criterios.*.peso' => ['required', 'numeric', 'gt:0', 'max:100'],
            'criterios.*.nota_max' => ['required', 'numeric', 'gt:0', 'max:100'],
        ]);

        $ativa = (bool) ($dados['avaliacao_apresentacao_ativa'] ?? false);
        $linhas = $dados['criterios'] ?? [];
        if ($ativa && count($linhas) === 0 && $evento->criteriosApresentacao()->count() === 0) {
            return back()->withInput()->withErrors(['criterios' => 'Defina ao menos um critério para ativar a avaliação das apresentações.']);
        }

        $aviso = null;
        DB::transaction(function () use ($evento, $dados, $ativa, $linhas, &$aviso) {
            $evento->trabalhoConfig->update([
                'avaliacao_apresentacao_ativa' => $ativa,
                'peso_apresentacao' => $dados['peso_apresentacao'],
                'autor_ve_nota_apresentacao' => (bool) ($dados['autor_ve_nota_apresentacao'] ?? false),
            ]);

            if (request()->has('criterios') || $linhas) {
                $existentes = $evento->criteriosApresentacao()->get()->keyBy('id');
                $usados = ApresentacaoAvaliacaoNota::whereIn('criterio_id', $existentes->keys())->pluck('criterio_id')->unique()->flip();
                $mantidos = [];
                foreach (array_values($linhas) as $i => $l) {
                    $c = !empty($l['id']) ? $existentes->get((int) $l['id']) : null;
                    if ($c && $usados->has($c->id)) {
                        $c->update(['descricao' => $l['descricao'] ?? null, 'ordem' => $i, 'ativo' => true]);   // nome/peso/máx. preservados
                    } else {
                        $valores = ['nome' => $l['nome'], 'descricao' => $l['descricao'] ?? null, 'peso' => $l['peso'], 'nota_max' => $l['nota_max'], 'ordem' => $i, 'ativo' => true];
                        $c = $c ? tap($c)->update($valores) : $evento->criteriosApresentacao()->create($valores);
                    }
                    $mantidos[] = $c->id;
                }
                $desativados = 0;
                foreach ($existentes->filter(fn ($c) => $c->ativo && !in_array($c->id, $mantidos, true)) as $c) {
                    if ($usados->has($c->id)) { $c->update(['ativo' => false]); $desativados++; } else { $c->delete(); }
                }
                $evento->criteriosApresentacao()->where('ativo', false)->whereNotIn('id', $usados->keys())->delete();
                if ($desativados) {
                    $aviso = "{$desativados} critério(s) com notas lançadas foram retirados. As notas já lançadas continuam valendo; as próximas avaliações deixam de usá-los.";
                }
            }
        });

        // Recalcula notas se o peso/critérios mudaram (a nota da apresentação independe do peso, mas a geral não)
        return redirect()->route('gestao.trabalhos.apresentacoes.index', $evento)
            ->with('success', 'Configuração da avaliação das apresentações salva.')
            ->with('warning', $aviso);
    }

    public function atribuir(Request $request, Trabalho $trabalho)
    {
        $this->authorize('gerirTrabalhos', $trabalho->evento);
        $dados = $request->validate(['avaliadores' => ['required', 'array', 'min:1'], 'avaliadores.*' => ['integer'], 'prazo' => ['nullable', 'date', 'after:today']],
            ['avaliadores.required' => 'Selecione ao menos um avaliador.']);

        $r = $this->servico->atribuirEmLote(collect([$trabalho]), User::whereIn('id', $dados['avaliadores'])->get(), $request->user(), $this->prazo($dados));

        return $this->resposta($r);
    }

    public function lote(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);
        $dados = $request->validate([
            'avaliadores' => ['required', 'array', 'min:1'], 'avaliadores.*' => ['integer'],
            'trabalhos' => ['nullable', 'array'], 'trabalhos.*' => ['integer'],
            'atividade' => ['nullable', 'integer'],
            'prazo' => ['nullable', 'date', 'after:today'],
        ], ['avaliadores.required' => 'Selecione ao menos um avaliador.']);

        $trabalhos = $evento->trabalhos()->where('status', 'aprovado')
            ->when(!empty($dados['trabalhos']), fn ($q) => $q->whereIn('id', $dados['trabalhos']))
            ->when(empty($dados['trabalhos']) && !empty($dados['atividade']), fn ($q) => $q->where('atividade_id', $dados['atividade']))
            ->with(['evento.trabalhoConfig', 'sessao', 'autores'])->get();

        if ($trabalhos->isEmpty()) {
            return back()->with('error', 'Nenhum trabalho aprovado selecionado (marque trabalhos na lista ou escolha a sessão).');
        }

        return $this->resposta($this->servico->atribuirEmLote($trabalhos, User::whereIn('id', $dados['avaliadores'])->get(), $request->user(), $this->prazo($dados)));
    }

    public function remover(ApresentacaoAvaliacao $avaliacao)
    {
        $this->authorize('gerirTrabalhos', $avaliacao->trabalho->evento);
        try {
            $this->servico->remover($avaliacao);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Avaliador removido.');
    }

    private function prazo(array $dados): ?Carbon
    {
        return !empty($dados['prazo']) ? Carbon::parse($dados['prazo'])->endOfDay() : null;
    }

    private function resposta(array $r)
    {
        $resp = back();
        if ($r['atribuidos'] > 0) {
            $resp = $resp->with('success', "{$r['atribuidos']} atribuição(ões) feita(s). Os avaliadores foram avisados por e-mail.");
        }
        if ($r['ignorados']) {
            $lista = collect($r['ignorados'])->unique()->take(6)->implode(' · ');
            $resp = $resp->with($r['atribuidos'] > 0 ? 'warning' : 'error', ($r['atribuidos'] > 0 ? 'Algumas atribuições foram ignoradas: ' : 'Nenhuma atribuição feita: ') . $lista);
        }

        return $resp;
    }
}
