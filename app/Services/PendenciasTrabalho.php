<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\Participante;
use App\Models\Trabalho;
use Illuminate\Support\Collection;

/**
 * Pendências (tags) do trabalho no fluxo: o que está faltando para ele avançar. São calculadas, não gravadas.
 *
 * Chaves: vincular_apresentador, vincular_sessao, atribuir_avaliadores, aguardando_decisao, aguardando_reenvio_aprovacao,
 * aguardando_autor, atribuir_avaliadores_apresentacao, resultado_apresentacao.
 */
class PendenciasTrabalho
{
    public const ROTULOS = [
        'vincular_apresentador' => 'Aguardando vincular apresentador',
        'vincular_sessao' => 'Aguardando definir sessão',
        'atribuir_avaliadores' => 'Aguardando avaliadores',
        'encaminhar_avaliacao' => 'Aguardando encaminhar para avaliação',
        'aguardando_decisao' => 'Aguardando decisão da gestão',
        'aguardando_reenvio_aprovacao' => 'Aguardando aprovação do reenvio',
        'aguardando_autor' => 'Aguardando ajustes do autor',
        'atribuir_avaliadores_apresentacao' => 'Aguardando avaliadores da apresentação',
        'resultado_apresentacao' => 'Aguardando resultado da apresentação',
    ];

    /**
     * Pendências de vários trabalhos de uma vez (sem consulta por item).
     * Requer carregados: autores, avaliacoes, avaliacoesApresentacao, evento.trabalhoConfig e certificados.
     *
     * @param  Collection<int,Trabalho>  $trabalhos
     * @return array<int,array<int,string>> trabalho_id => lista de chaves
     */
    public function calcular(Collection $trabalhos): array
    {
        $ligados = $this->participantesComConta($trabalhos);
        $resultado = [];

        foreach ($trabalhos as $t) {
            $resultado[$t->id] = $this->doTrabalho($t, $ligados);
        }

        return $resultado;
    }

    /** @param array{cpfs:array<string,bool>,emails:array<string,bool>,ids:array<int,bool>} $ligados */
    private function doTrabalho(Trabalho $t, array $ligados): array
    {
        $cfg = $t->evento->trabalhoConfig;
        $pend = [];

        switch ($t->status) {
            case 'em_triagem':
                $minimo = max(1, (int) ($cfg?->avaliadores_por_trabalho ?? 2));
                // Com o mínimo de avaliadores atribuído, falta encaminhar: só então eles enxergam o trabalho na área deles
                $pend[] = $t->avaliacoes->where('status', '!=', 'recusada')->count() < $minimo ? 'atribuir_avaliadores' : 'encaminhar_avaliacao';
                break;
            case 'avaliado':
                $pend[] = 'aguardando_decisao';
                break;
            case 'resubmetido':
                $pend[] = 'aguardando_reenvio_aprovacao';
                break;
            case 'aguardando_correcao':
            case 'devolvido_triagem':
                $pend[] = 'aguardando_autor';
                break;
            case 'aprovado':
                if ($t->statusFinal() === 'concluido') {
                    break;
                }
                if (!$this->temApresentadorVinculado($t, $ligados)) {
                    $pend[] = 'vincular_apresentador';
                }
                if (!$t->atividade_id) {
                    $pend[] = 'vincular_sessao';
                }
                if ($cfg?->avaliacao_apresentacao_ativa) {
                    $ativas = $t->avaliacoesApresentacao->where('status', '!=', 'recusada');
                    if ($ativas->isEmpty()) {
                        $pend[] = 'atribuir_avaliadores_apresentacao';
                    } elseif ($t->resultado_apresentacao === null) {
                        $pend[] = 'resultado_apresentacao';
                    }
                }
                break;
        }

        return $pend;
    }

    /** O trabalho tem um autor apresentador cuja conta/participante já está ligada (presença pode confirmar a apresentação)? */
    private function temApresentadorVinculado(Trabalho $t, array $ligados): bool
    {
        foreach ($t->autores->where('apresentador', true) as $a) {
            if (($a->participante_id && isset($ligados['ids'][$a->participante_id]))
                || ($a->cpf && isset($ligados['cpfs'][$a->cpf]))
                || ($a->email && isset($ligados['emails'][mb_strtolower($a->email)]))) {
                return true;
            }
        }

        return false;
    }

    /** Participantes que têm conta, entre os apresentadores dos trabalhos (2 consultas no máximo). */
    private function participantesComConta(Collection $trabalhos): array
    {
        $apresentadores = $trabalhos->flatMap(fn ($t) => $t->autores->where('apresentador', true));
        $cpfs = $apresentadores->pluck('cpf')->filter()->unique()->values();
        $emails = $apresentadores->pluck('email')->filter()->map(fn ($e) => mb_strtolower($e))->unique()->values();
        $ids = $apresentadores->pluck('participante_id')->filter()->unique()->values();

        $linhas = ($cpfs->isEmpty() && $emails->isEmpty() && $ids->isEmpty()) ? collect() : Participante::query()->whereNotNull('user_id')
            ->where(fn ($q) => $q->whereIn('cpf', $cpfs)->orWhereIn('email', $emails)->orWhereIn('id', $ids))
            ->get(['id', 'cpf', 'email']);

        return [
            'cpfs' => $linhas->pluck('cpf')->filter()->mapWithKeys(fn ($v) => [$v => true])->all(),
            'emails' => $linhas->pluck('email')->filter()->mapWithKeys(fn ($v) => [mb_strtolower($v) => true])->all(),
            'ids' => $linhas->pluck('id')->mapWithKeys(fn ($v) => [$v => true])->all(),
        ];
    }
}
