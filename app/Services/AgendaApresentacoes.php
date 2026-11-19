<?php

namespace App\Services;

use App\Mail\ApresentacaoAgendadaMail;
use App\Models\ApresentacaoAvaliacao;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Agenda das apresentações orais: sala (link) e horário de cada trabalho, conflitos e avisos aos autores e avaliadores. */
class AgendaApresentacoes
{
    /** Trabalhos que apresentam: aprovados na parte escrita, por padrão só os da modalidade oral. */
    public function trabalhosDaAgenda(Evento $evento, bool $todasModalidades = false)
    {
        return $evento->trabalhos()->where('status', 'aprovado')
            ->when(!$todasModalidades, fn ($q) => $q->where('modalidade_apresentacao', 'oral'))
            ->with(['sala', 'autores', 'avaliacoesApresentacao.avaliador'])
            ->orderByRaw('apresentacao_inicio is null')->orderBy('apresentacao_inicio')->orderBy('codigo');
    }

    /**
     * Define sala e horários de vários trabalhos em sequência (um após o outro, com intervalo).
     * Retorna ['ok' => bool, 'conflitos' => string[], 'avisos' => string[], 'total' => int].
     *
     * @param  Collection<int,Trabalho>  $trabalhos já na ordem de apresentação
     */
    public function programar(Collection $trabalhos, int $salaId, Carbon $inicio, int $duracaoMin, int $intervaloMin, bool $ignorarConflito = false): array
    {
        $janelas = [];
        $cursor = $inicio->copy();
        foreach ($trabalhos as $t) {
            $janelas[$t->id] = [$cursor->copy(), $cursor->copy()->addMinutes($duracaoMin)];
            $cursor->addMinutes($duracaoMin + $intervaloMin);
        }

        $conflitos = $this->conflitosDeSala($trabalhos->first()->evento_id, $salaId, $janelas);
        if ($conflitos && !$ignorarConflito) {
            return ['ok' => false, 'conflitos' => $conflitos, 'avisos' => [], 'total' => 0];
        }

        DB::transaction(function () use ($trabalhos, $salaId, $janelas) {
            foreach ($trabalhos as $t) {
                $this->gravar($t, $salaId, $janelas[$t->id][0], $janelas[$t->id][1]);
            }
        });

        return ['ok' => true, 'conflitos' => $conflitos, 'avisos' => $this->avisosDeAvaliadores($trabalhos->fresh('avaliacoesApresentacao')), 'total' => $trabalhos->count()];
    }

    /** Limpa sala e horário. */
    public function limpar(Trabalho $trabalho): void
    {
        $trabalho->update(['sala_apresentacao_id' => null, 'apresentacao_inicio' => null, 'apresentacao_fim' => null, 'agenda_alterada_em' => $trabalho->agenda_notificada_em ? now() : null, 'lembrete_enviado_em' => null]);
    }

    private function gravar(Trabalho $t, int $salaId, Carbon $ini, Carbon $fim): void
    {
        $mudou = $t->sala_apresentacao_id !== $salaId || !$t->apresentacao_inicio?->equalTo($ini) || !$t->apresentacao_fim?->equalTo($fim);
        $t->update([
            'sala_apresentacao_id' => $salaId, 'apresentacao_inicio' => $ini, 'apresentacao_fim' => $fim,
            // Mudou depois de já ter sido avisado: o próximo aviso sai como "atualização" e o lembrete é refeito
            'agenda_alterada_em' => $mudou && $t->agenda_notificada_em ? now() : $t->agenda_alterada_em,
            'lembrete_enviado_em' => $mudou ? null : $t->lembrete_enviado_em,
        ]);
    }

    /**
     * Apresentações já agendadas na mesma sala que se sobrepõem às janelas propostas (ignora os próprios trabalhos).
     *
     * @param  array<int,array{0:Carbon,1:Carbon}>  $janelas  trabalho_id => [início, fim]
     * @return string[]
     */
    private function conflitosDeSala(int $eventoId, int $salaId, array $janelas): array
    {
        $existentes = Trabalho::where('evento_id', $eventoId)->where('sala_apresentacao_id', $salaId)
            ->whereNotNull('apresentacao_inicio')->whereNotIn('id', array_keys($janelas))->get();

        $msgs = [];
        foreach ($janelas as $id => [$ini, $fim]) {
            foreach ($existentes as $e) {
                $efim = $e->apresentacao_fim ?? $e->apresentacao_inicio->copy()->addMinutes(15);
                if ($ini < $efim && $fim > $e->apresentacao_inicio) {
                    $msgs[] = "{$e->codigo} já ocupa esta sala em {$e->apresentacao_inicio->format('d/m H:i')}–{$efim->format('H:i')} (choca com {$ini->format('d/m H:i')}–{$fim->format('H:i')}).";
                }
            }
        }

        return array_values(array_unique($msgs));
    }

    /** Avaliadores de apresentação com dois trabalhos ao mesmo tempo (aviso, não bloqueia). */
    private function avisosDeAvaliadores(Collection $trabalhos): array
    {
        $avisos = [];
        foreach ($trabalhos as $t) {
            foreach ($t->avaliacoesApresentacao->where('status', '!=', 'recusada') as $av) {
                $outros = ApresentacaoAvaliacao::with('trabalho')->where('avaliador_id', $av->avaliador_id)->where('status', '!=', 'recusada')
                    ->where('trabalho_id', '!=', $t->id)->get()
                    ->filter(fn ($o) => $o->trabalho->apresentacao_inicio && $t->apresentacao_inicio
                        && $t->apresentacao_inicio < ($o->trabalho->apresentacao_fim ?? $o->trabalho->apresentacao_inicio->copy()->addMinutes(15))
                        && ($t->apresentacao_fim ?? $t->apresentacao_inicio->copy()->addMinutes(15)) > $o->trabalho->apresentacao_inicio);
                foreach ($outros as $o) {
                    $avisos[] = ($av->avaliador?->name ?? 'Avaliador') . " está em {$t->codigo} e {$o->trabalho->codigo} ao mesmo tempo.";
                }
            }
        }

        return array_values(array_unique($avisos));
    }

    /** Trabalhos da agenda com sala ocupada ao mesmo tempo (para marcar na tela). IDs em conflito. */
    public function idsEmConflito(Collection $trabalhos): array
    {
        $ids = [];
        $agendados = $trabalhos->filter(fn ($t) => $t->agendado())->groupBy('sala_apresentacao_id');
        foreach ($agendados as $grupo) {
            foreach ($grupo as $a) {
                foreach ($grupo as $b) {
                    if ($a->id < $b->id) {
                        $afim = $a->apresentacao_fim ?? $a->apresentacao_inicio->copy()->addMinutes(15);
                        $bfim = $b->apresentacao_fim ?? $b->apresentacao_inicio->copy()->addMinutes(15);
                        if ($a->apresentacao_inicio < $bfim && $afim > $b->apresentacao_inicio) {
                            $ids[$a->id] = $ids[$b->id] = true;
                        }
                    }
                }
            }
        }

        return array_keys($ids);
    }

    // ------------------------------------------------------------------
    // Avisos
    // ------------------------------------------------------------------

    /** Autores (e quem submeteu) com e-mail. */
    public function emailsDosAutores(Trabalho $t): array
    {
        $t->loadMissing(['autores', 'submetente']);

        return $t->autores->pluck('email')->filter()->push($t->submetente?->email)->filter()->map(fn ($e) => mb_strtolower($e))->unique()->values()->all();
    }

    /** Avaliadores da apresentação que não recusaram. */
    public function avaliadoresDaApresentacao(Trabalho $t): Collection
    {
        return $t->avaliacoesApresentacao()->with('avaliador')->where('status', '!=', 'recusada')->get()->pluck('avaliador')->filter()->unique('id');
    }

    /**
     * Envia sala e horário aos autores e avaliadores. Retorna quantos e-mails foram enfileirados.
     * $tipo: 'convite' (primeiro aviso), 'atualizacao' (mudou depois do aviso) ou 'lembrete'.
     */
    public function notificar(Trabalho $t, string $tipo = 'convite'): int
    {
        if (!$t->agendado()) {
            return 0;
        }
        $t->loadMissing(['sala', 'evento']);
        $n = 0;

        foreach ($this->emailsDosAutores($t) as $email) {
            Mail::to($email)->queue(new ApresentacaoAgendadaMail($t, 'autor', $tipo));
            $n++;
        }
        foreach ($this->avaliadoresDaApresentacao($t) as $av) {
            Mail::to($av->email)->queue(new ApresentacaoAgendadaMail($t, 'avaliador', $tipo));
            $n++;
        }

        $t->update($tipo === 'lembrete'
            ? ['lembrete_enviado_em' => now()]
            : ['agenda_notificada_em' => now(), 'agenda_alterada_em' => null]);

        return $n;
    }

    /** Escolhe convite ou atualização conforme o histórico de avisos do trabalho. */
    public function notificarPendente(Trabalho $t): int
    {
        return $this->notificar($t, $t->agenda_notificada_em ? 'atualizacao' : 'convite');
    }

    /** Lembrete automático: apresentações que começam nos próximos minutos e ainda não receberam lembrete. */
    public function enviarLembretes(int $antecedenciaMin = 30): int
    {
        $total = 0;
        Trabalho::where('status', 'aprovado')->whereNotNull('sala_apresentacao_id')->whereNull('lembrete_enviado_em')
            ->whereBetween('apresentacao_inicio', [now(), now()->addMinutes($antecedenciaMin)])
            ->with(['sala', 'evento', 'autores', 'submetente'])->get()
            ->each(function (Trabalho $t) use (&$total) {
                $total += $this->notificar($t, 'lembrete');
            });

        return $total;
    }

    /** Pode abrir a sala: gestão do evento, autores do trabalho e avaliadores da apresentação. */
    public function podeEntrar(User $user, Trabalho $t): bool
    {
        return $user->can('gerirTrabalhos', $t->evento)
            || $t->ehAutor($user)
            || $t->avaliacoesApresentacao()->where('avaliador_id', $user->id)->where('status', '!=', 'recusada')->exists();
    }
}
