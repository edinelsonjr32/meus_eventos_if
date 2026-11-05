<?php

namespace App\Policies;

use App\Models\Evento;
use App\Models\User;

/**
 * Autorização por evento.
 *
 * - gerenciar:       administrador dono do evento ou co-administrador (pivô evento_user, role "editor")
 * - gerirTrabalhos:  quem gerencia o evento OU é gestor de trabalhos do evento
 * - avaliar:         avaliador designado ao evento
 */
class EventoPolicy
{
    public function gerenciar(User $user, Evento $evento): bool
    {
        if ($user->role !== 'admin') {
            return false;
        }

        return $evento->criado_por === $user->id
            || $user->papelNoEvento($evento, [Evento::PAPEL_EDITOR]);
    }

    public function gerirTrabalhos(User $user, Evento $evento): bool
    {
        return $this->gerenciar($user, $evento)
            || $user->papelNoEvento($evento, [Evento::PAPEL_GESTOR_TRABALHOS]);
    }

    public function avaliar(User $user, Evento $evento): bool
    {
        return $user->papelNoEvento($evento, [Evento::PAPEL_AVALIADOR]);
    }
}
