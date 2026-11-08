<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Frequencia;
use App\Models\Trabalho;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Controle de apresentação dos trabalhos: marcação manual pela gestão ou automática pela
 * presença de um autor apresentador na sessão (atividade) vinculada ao trabalho.
 */
class ApresentacaoService
{
    public function __construct(private TrabalhoWorkflow $workflow)
    {
    }

    /**
     * Vincula (ou remove) a sessão de apresentação. A atividade precisa ser do mesmo evento.
     *
     * @throws \DomainException
     */
    public function definirSessao(Trabalho $trabalho, ?Atividade $atividade): void
    {
        if ($atividade && $atividade->evento_id !== $trabalho->evento_id) {
            throw new \DomainException('A sessão deve ser uma atividade do mesmo evento.');
        }

        $trabalho->update(['atividade_id' => $atividade?->id]);

        // Presenças já registradas na sessão contam: confirma a apresentação na hora
        if ($atividade) {
            $this->sincronizarTrabalho($trabalho->fresh());
        }
    }

    /** Confirma a apresentação se um autor apresentador já tem presença na sessão do trabalho. */
    public function sincronizarTrabalho(Trabalho $trabalho): bool
    {
        if ($trabalho->status !== 'aprovado' || $trabalho->apresentado || !$trabalho->atividade_id) {
            return false;
        }

        $apresentadores = $trabalho->autores()->where('apresentador', true)->get(['cpf', 'email']);
        $cpfs = $apresentadores->pluck('cpf')->filter()->all();
        $emails = $apresentadores->pluck('email')->filter()->all();

        $presente = Frequencia::where('atividade_id', $trabalho->atividade_id)
            ->whereHas('participante', fn ($p) => $p->where(fn ($q) => $q->whereIn('cpf', $cpfs)->orWhereIn('email', $emails)))
            ->exists();

        if ($presente) {
            $this->marcar($trabalho, true, null, 'frequencia');
        }

        return $presente;
    }

    /**
     * Marca ou desmarca a apresentação. Somente trabalhos aprovados apresentam.
     *
     * @throws \DomainException
     */
    public function marcar(Trabalho $trabalho, bool $apresentado, ?User $gestor = null, string $origem = 'manual'): void
    {
        if ($trabalho->status !== 'aprovado') {
            throw new \DomainException("{$trabalho->codigo}: somente trabalhos aprovados podem ter a apresentação registrada.");
        }

        if ($trabalho->apresentado === $apresentado) {
            return;
        }

        DB::transaction(function () use ($trabalho, $apresentado, $gestor, $origem) {
            $trabalho->update([
                'apresentado' => $apresentado,
                'apresentado_em' => $apresentado ? now() : null,
                'apresentacao_origem' => $apresentado ? $origem : null,
            ]);

            // Registro interno (de = para): não aparece para o autor
            $this->workflow->registrar(
                $trabalho, $gestor, $trabalho->status, $trabalho->status,
                $apresentado ? match ($origem) {
                    'frequencia' => 'Apresentação confirmada pela presença do apresentador',
                    'avaliacao' => 'Apresentação confirmada ao ser avaliada' . ($gestor ? " ({$gestor->name})" : ''),
                    default => 'Apresentação registrada pela gestão',
                } : 'Apresentação desmarcada'
            );
        });
    }

    /** Confirma apresentações quando um autor apresentador registra presença na sessão do trabalho. */
    public function sincronizarPorFrequencia(Frequencia $frequencia): int
    {
        $cpf = $frequencia->participante?->cpf;
        $email = $frequencia->participante?->email;
        if (!$cpf && !$email) {
            return 0;
        }

        $trabalhos = Trabalho::where('status', 'aprovado')
            ->where('apresentado', false)
            ->where('atividade_id', $frequencia->atividade_id)
            ->whereHas('autores', fn ($a) => $a->where('apresentador', true)->where(function ($q) use ($cpf, $email) {
                // Apresentador identificado pelo CPF ou, na falta dele, pelo e-mail
                $q->when($cpf, fn ($w) => $w->orWhere('cpf', $cpf))->when($email, fn ($w) => $w->orWhere('email', $email));
            }))
            ->get();

        foreach ($trabalhos as $trabalho) {
            $this->marcar($trabalho, true, null, 'frequencia');
        }

        return $trabalhos->count();
    }

    /** Reavalia todos os trabalhos aprovados do evento que têm sessão definida. */
    public function sincronizarEvento(Evento $evento): int
    {
        $confirmados = 0;

        $evento->trabalhos()->where('status', 'aprovado')->where('apresentado', false)->whereNotNull('atividade_id')->get()
            ->each(function (Trabalho $trabalho) use (&$confirmados) {
                if ($this->sincronizarTrabalho($trabalho)) {
                    $confirmados++;
                }
            });

        return $confirmados;
    }
}
