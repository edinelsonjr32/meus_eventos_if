<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\Local;
use Carbon\CarbonInterface;

/** Regras de uso dos locais: conflito de agenda e capacidade. */
class LocalService
{
    /** Atividade do mesmo evento que já ocupa o local no intervalo informado (ou null). */
    public function conflito(int $eventoId, ?int $localId, CarbonInterface $inicio, CarbonInterface $fim, ?int $ignorarAtividadeId = null): ?Atividade
    {
        if (!$localId) {
            return null;
        }

        return Atividade::where('evento_id', $eventoId)->where('local_id', $localId)
            ->when($ignorarAtividadeId, fn ($q) => $q->where('id', '!=', $ignorarAtividadeId))
            ->where('data_inicio', '<', $fim)->where('data_fim', '>', $inicio)
            ->orderBy('data_inicio')->first();
    }

    /** @throws \DomainException quando o local já está reservado no horário */
    public function garantirLivre(int $eventoId, ?int $localId, CarbonInterface $inicio, CarbonInterface $fim, ?int $ignorarAtividadeId = null): void
    {
        if ($c = $this->conflito($eventoId, $localId, $inicio, $fim, $ignorarAtividadeId)) {
            $nome = Local::find($localId)?->nome;
            throw new \DomainException("Conflito de agenda: \"{$nome}\" já está reservado para \"{$c->titulo}\" ({$c->data_inicio->format('d/m H:i')} a {$c->data_fim->format('H:i')}). Mude o horário ou o local, ou marque para ignorar o conflito.");
        }
    }

    /** @throws \DomainException quando as vagas passam da capacidade cadastrada do local */
    public function garantirCapacidade(?int $localId, ?int $vagas): void
    {
        $local = $localId ? Local::find($localId) : null;
        if ($local && $local->capacidade && $vagas && $vagas > $local->capacidade) {
            throw new \DomainException("\"{$local->nome}\" comporta {$local->capacidade} pessoa(s): as vagas ({$vagas}) passam da capacidade do local.");
        }
    }
}
