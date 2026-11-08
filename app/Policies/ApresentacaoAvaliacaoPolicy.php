<?php

namespace App\Policies;

use App\Models\ApresentacaoAvaliacao;
use App\Models\User;

class ApresentacaoAvaliacaoPolicy
{
    /** O avaliador acessa apenas as próprias avaliações de apresentação. */
    public function view(User $user, ApresentacaoAvaliacao $avaliacao): bool
    {
        return $avaliacao->avaliador_id === $user->id;
    }

    public function avaliar(User $user, ApresentacaoAvaliacao $avaliacao): bool
    {
        return $avaliacao->avaliador_id === $user->id && app(\App\Services\AvaliacaoApresentacaoService::class)->editavel($avaliacao);
    }
}
