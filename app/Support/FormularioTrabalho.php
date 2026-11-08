<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\User;

/** Dados que alimentam o formulário de trabalho (autor e gestão). */
class FormularioTrabalho
{
    private const CAMPOS_AUTOR = ['id', 'nome_completo', 'cpf', 'email', 'instituicao', 'categoria_autor', 'tipo_autor', 'apresentador', 'correspondente'];

    /**
     * @param User|null $prefill  usuário cujos dados pré-preenchem o apresentador de um trabalho novo
     *                            (null = linha em branco, usado quando a gestão cadastra em nome do autor)
     */
    public static function dados(Evento $evento, Trabalho $trabalho, ?User $prefill): array
    {
        // O autor apresentador (principal) é sempre o primeiro; ao editar, vem dos dados salvos
        $autores = old('autores');

        if ($autores === null && $trabalho->exists) {
            $autores = $trabalho->autores
                ->sortBy(fn ($a) => $a->tipo_autor === 'autor_principal' ? 0 : 1)
                ->map(fn ($a) => $a->only(self::CAMPOS_AUTOR))->values()->all();
        }

        if (empty($autores)) {
            $participante = $prefill?->participante;
            $autores = [[
                'nome_completo' => $prefill?->name, 'cpf' => $participante?->cpf, 'email' => $prefill?->email,
                'categoria_autor' => match ($participante?->tipo_vinculo) { 'aluno' => 'discente', 'servidor' => 'docente', default => null },
                'tipo_autor' => 'autor_principal', 'apresentador' => true, 'correspondente' => true,
            ]];
        }

        return [
            'evento' => $evento,
            'trabalho' => $trabalho,
            'config' => $evento->trabalhoConfig,
            'areas' => $evento->areasTematicas,
            'autores' => array_values($autores),
        ];
    }
}
