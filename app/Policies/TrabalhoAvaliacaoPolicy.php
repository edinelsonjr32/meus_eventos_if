<?php

namespace App\Policies;

use App\Models\TrabalhoAvaliacao;
use App\Models\User;

class TrabalhoAvaliacaoPolicy
{
    /** O avaliador acessa apenas as próprias avaliações. */
    public function view(User $user, TrabalhoAvaliacao $avaliacao): bool
    {
        return $avaliacao->avaliador_id === $user->id;
    }

    /** Preencher: própria avaliação, ainda aberta, trabalho em avaliação e rodada vigente. */
    public function avaliar(User $user, TrabalhoAvaliacao $avaliacao): bool
    {
        return $avaliacao->avaliador_id === $user->id
            && in_array($avaliacao->status, ['pendente', 'em_andamento'], true)
            && $avaliacao->trabalho->status === 'em_avaliacao'
            && $avaliacao->rodada === $avaliacao->trabalho->rodadaAtual();
    }
}
