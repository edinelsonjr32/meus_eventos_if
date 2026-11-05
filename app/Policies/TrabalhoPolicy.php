<?php

namespace App\Policies;

use App\Models\Trabalho;
use App\Models\User;

class TrabalhoPolicy
{
    /** Ver e baixar: o próprio autor (submetente) ou quem gere os trabalhos do evento. */
    public function view(User $user, Trabalho $trabalho): bool
    {
        return $trabalho->submetido_por === $user->id
            || $user->can('gerirTrabalhos', $trabalho->evento);
    }

    /** Editar conteúdo: somente o submetente e enquanto o status permitir. */
    public function update(User $user, Trabalho $trabalho): bool
    {
        return $trabalho->submetido_por === $user->id && $trabalho->editavel();
    }

    /** Retirar: o submetente, antes de qualquer decisão. */
    public function retirar(User $user, Trabalho $trabalho): bool
    {
        return $trabalho->submetido_por === $user->id
            && in_array($trabalho->status, [Trabalho::RASCUNHO, Trabalho::SUBMETIDO, Trabalho::DEVOLVIDO_TRIAGEM, Trabalho::AGUARDANDO_CORRECAO], true);
    }
}
