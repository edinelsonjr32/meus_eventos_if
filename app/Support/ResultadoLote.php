<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;

/**
 * Resultado padronizado das ações em lote (triagem, atribuição de avaliadores...): quantas operações foram
 * feitas e, agrupados por motivo, quais itens foram ignorados. As duas telas de lote usam o mesmo formato.
 */
class ResultadoLote
{
    public int $feitas = 0;

    /** @var array<string, array<int, string>> motivo => itens afetados */
    private array $ignorados = [];

    public function ok(int $n = 1): void
    {
        $this->feitas += $n;
    }

    public function ignorar(string $item, string $motivo): void
    {
        $this->ignorados[$this->normalizar($motivo)][] = $item;
    }

    public function mesclar(self $outro): void
    {
        $this->feitas += $outro->feitas;
        foreach ($outro->ignorados as $motivo => $itens) {
            $this->ignorados[$motivo] = array_merge($this->ignorados[$motivo] ?? [], $itens);
        }
    }

    public function totalIgnorados(): int
    {
        return array_sum(array_map('count', $this->ignorados));
    }

    /** @return array<int, string> linhas "Motivo (n): item, item" */
    public function linhas(): array
    {
        $linhas = [];
        foreach ($this->ignorados as $motivo => $itens) {
            $itens = array_values(array_unique($itens));
            $mostrar = array_slice($itens, 0, 8);
            $mais = count($itens) > 8 ? ' e mais ' . (count($itens) - 8) : '';
            $linhas[] = "{$motivo} (" . count($itens) . '): ' . implode(', ', $mostrar) . $mais;
        }

        return $linhas;
    }

    public function aplicar(RedirectResponse $resposta, string $unidade = 'operação(ões)'): RedirectResponse
    {
        $resposta = $this->feitas > 0
            ? $resposta->with('success', "{$this->feitas} {$unidade} realizada(s) com sucesso.")
            : $resposta->with('error', 'Nenhuma operação foi realizada.');

        return $this->ignorados ? $resposta->with('erros_lote', $this->linhas()) : $resposta;
    }

    private function normalizar(string $motivo): string
    {
        return rtrim(trim($motivo), '.');
    }
}
