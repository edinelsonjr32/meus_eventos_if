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

    /**
     * Baixar o PDF: autor, gestão e avaliadores atribuídos (que não recusaram).
     * O avaliador baixa o arquivo sem acessar a página de detalhes, que expõe os autores.
     */
    public function baixarArquivo(User $user, Trabalho $trabalho): bool
    {
        return $this->view($user, $trabalho)
            || $trabalho->avaliacoes()->where('avaliador_id', $user->id)->where('status', '!=', 'recusada')->exists();
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
