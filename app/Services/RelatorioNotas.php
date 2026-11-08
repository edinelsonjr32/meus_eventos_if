<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\TrabalhoAvaliacao;
use App\Models\TrabalhoConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Relatórios de notas para a comissão: ranking por trabalho (nota média da rodada vigente) e
 * detalhamento por avaliação (notas por critério e pareceres). Somente para a gestão.
 */
class RelatorioNotas
{
    /** Critérios do evento, na ordem de avaliação. */
    public function criterios(Evento $evento): Collection
    {
        return $evento->criterios()->where('ativo', true)->get();
    }

    /** Trabalhos submetidos (sem rascunhos) já com avaliações, notas e autores carregados. */
    private function trabalhos(Evento $evento, array $filtros): Collection
    {
        $query = $evento->trabalhos()
            ->where('status', '!=', Trabalho::RASCUNHO)
            ->with(['area', 'autores', 'avaliacoes.avaliador', 'avaliacoes.notas', 'evento.trabalhoConfig', 'certificados']);

        if (!empty($filtros['tipo'])) {
            $query->where('tipo_trabalho', $filtros['tipo']);
        }
        if (!empty($filtros['area'])) {
            $query->where('area_tematica_id', $filtros['area']);
        }
        if (!empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        return $query->get();
    }

    /** Filtros aceitos vindos da requisição. */
    public static function filtros(Request $request): array
    {
        return $request->only(['tipo', 'area', 'status']);
    }

    /**
     * Uma linha por trabalho, ordenada por nota (maior primeiro; sem nota por último), com posição no ranking
     * (empates dividem a posição: 1, 2, 2, 4...).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function resumo(Evento $evento, array $filtros = []): Collection
    {
        $criterios = $this->criterios($evento);

        $linhas = $this->trabalhos($evento, $filtros)->map(function (Trabalho $t) use ($criterios) {
            $rodada = max(1, (int) $t->avaliacoes->max('rodada'));
            $atuais = $t->avaliacoes->where('rodada', $rodada)->where('status', '!=', 'recusada');
            $concluidas = $atuais->where('status', 'concluida');

            $notas = $concluidas->pluck('nota_final')->map(fn ($n) => (float) $n);
            $mediaPorCriterio = $criterios->mapWithKeys(function ($c) use ($concluidas) {
                $valores = $concluidas->flatMap(fn ($a) => $a->notas->where('criterio_id', $c->id)->pluck('nota'));

                return [$c->nome => $valores->isEmpty() ? null : round($valores->avg(), 2)];
            });

            return [
                'trabalho' => $t,
                'posicao' => null,
                'nota' => $notas->isEmpty() ? null : round($notas->avg(), 2),
                'notas' => $notas->values()->all(),
                'concluidas' => $concluidas->count(),
                'atribuidas' => $atuais->count(),
                'divergencia' => $notas->count() > 1 ? round($notas->max() - $notas->min(), 2) : null,
                'por_criterio' => $mediaPorCriterio->all(),
                'recomendacoes' => $concluidas->countBy('recomendacao')->all(),
                'rodada' => $rodada,
                'nota_apresentacao' => $t->nota_apresentacao,
                'nota_geral' => $t->notaGeral(),
                // Pontuação do ranking por categoria/premiação: nota geral (texto + apresentação) ou, sem ela, a do texto
                'pontuacao' => $t->notaGeral() ?? ($notas->isEmpty() ? null : round($notas->avg(), 2)),
                // Só concorre à colocação/premiação quem está aprovado nas duas partes e tem a nota geral completa
                'elegivel_premio' => $t->aprovadoCompleto() && $t->nota_final !== null && (!($t->evento->trabalhoConfig?->avaliacao_apresentacao_ativa) || $t->nota_apresentacao !== null),
                'posicao_categoria' => null,
                'avaliacoes_detalhe' => $concluidas->map(fn ($a) => [
                    'avaliador' => $a->avaliador->name ?? '—', 'nota' => $a->nota_final, 'recomendacao' => $a->recomendacaoRotulo(), 'desempate' => (bool) $a->desempate,
                ])->values()->all(),
            ];
        })
            ->sortBy([
                fn ($a, $b) => ($b['nota'] ?? -1) <=> ($a['nota'] ?? -1),
                fn ($a, $b) => strcmp((string) $a['trabalho']->codigo, (string) $b['trabalho']->codigo),
            ])
            ->values();

        // Posição por "ranking de competição" (trabalha em array: Collection não permite alterar elementos)
        $lista = $linhas->all();
        $posicao = 0;
        $anterior = null;
        foreach ($lista as $i => $linha) {
            if ($linha['nota'] === null) {
                continue;
            }
            if ($linha['nota'] !== $anterior) {
                $posicao = $i + 1;
                $anterior = $linha['nota'];
            }
            $lista[$i]['posicao'] = $posicao;
        }

        // Posição dentro da categoria entre os ELEGÍVEIS, pela pontuação (empates dividem o lugar: 1, 2, 2, 4...)
        foreach (collect($lista)->groupBy(fn ($l) => $l['trabalho']->tipo_trabalho, true) as $grupo) {
            $ord = $grupo->filter(fn ($l) => $l['pontuacao'] !== null && $l['elegivel_premio'])->sortByDesc('pontuacao')->keys()->all();
            $pos = 0;
            $ant = null;
            foreach ($ord as $n => $chave) {
                if ($lista[$chave]['pontuacao'] !== $ant) {
                    $pos = $n + 1;
                    $ant = $lista[$chave]['pontuacao'];
                }
                $lista[$chave]['posicao_categoria'] = $pos;
            }
        }

        return collect($lista);
    }

    /**
     * Uma linha por avaliação (todas as rodadas), com a nota de cada critério e os pareceres.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function detalhado(Evento $evento, array $filtros = []): Collection
    {
        $criterios = $this->criterios($evento);

        return $this->trabalhos($evento, $filtros)->sortBy('codigo')->flatMap(function (Trabalho $t) use ($criterios) {
            return $t->avaliacoes->sortBy(['rodada', 'id'])->map(function (TrabalhoAvaliacao $a) use ($t, $criterios) {
                return [
                    'trabalho' => $t,
                    'avaliacao' => $a,
                    'por_criterio' => $criterios->mapWithKeys(fn ($c) => [$c->nome => $a->notas->firstWhere('criterio_id', $c->id)?->nota])->all(),
                ];
            });
        })->values();
    }

    /** Rótulo legível das recomendações ("Aprovar ×2; Reprovar ×1"). */
    public static function rotuloRecomendacoes(array $contagem): string
    {
        return collect($contagem)->map(fn ($n, $r) => (TrabalhoAvaliacao::RECOMENDACOES[$r] ?? $r) . ($n > 1 ? " ×{$n}" : ''))->implode('; ');
    }

    public static function categoria(?string $tipo): string
    {
        return TrabalhoConfig::TIPOS[$tipo] ?? (string) $tipo;
    }
}
