<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\MinistranteEvento;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Quem ministra no evento: os nomes do campo "Responsável" das atividades, completados com o que a organização
 * cadastrou (foto, descrição, ordem, ocultar) e com quem foi acrescentado à mão.
 */
class MinistrantesDoEvento
{
    /** Todas as pessoas, inclusive as ocultas (tela de gestão). */
    public function todos(Evento $evento, ?Collection $atividades = null): Collection
    {
        $atividades ??= $evento->atividades()->orderBy('data_inicio')->get();

        $pessoas = [];
        foreach ($atividades as $atividade) {
            foreach ($this->nomes((string) $atividade->responsavel) as $nome) {
                $chave = MinistranteEvento::chaveDe($nome);
                $pessoas[$chave] ??= ['chave' => $chave, 'nome' => $this->capitalizar($nome), 'atividades' => []];
                $pessoas[$chave]['atividades'][$atividade->id] = $atividade->titulo;
            }
        }

        foreach (MinistranteEvento::where('evento_id', $evento->id)->get() as $registro) {
            $pessoas[$registro->chave] = ['chave' => $registro->chave, 'nome' => $registro->nome, 'registro' => $registro]
                + ($pessoas[$registro->chave] ?? ['atividades' => []]);
        }

        return collect($pessoas)->map(function (array $p) {
            $registro = $p['registro'] ?? null;

            return (object) [
                'chave' => $p['chave'],
                'nome' => $p['nome'],
                'iniciais' => $this->iniciais($p['nome']),
                'atividades' => array_values(array_unique($p['atividades'])),
                'registro' => $registro,
                'descricao' => $registro?->descricao,
                'foto' => $registro?->fotoUrl(),
                'ordem' => $registro?->ordem,
                'oculto' => (bool) $registro?->oculto,
            ];
        })->sortBy(fn ($p) => sprintf('%05d %s', $p->ordem ?? 99999, $p->chave))->values();
    }

    /** Somente quem aparece na página pública. */
    public function publicos(Evento $evento, ?Collection $atividades = null): Collection
    {
        return $this->todos($evento, $atividades)->reject->oculto->values();
    }

    private function nomes(string $responsavel): array
    {
        return collect(preg_split('/[,;\/\n]+/', $responsavel))
            ->map(fn ($n) => trim(Str::squish($n), " .-–"))
            // descarta restos como "..." de texto cortado ou abreviações de titulação soltas
            ->filter(fn ($n) => mb_strlen($n) >= 4 && preg_match('/\p{L}{2}/u', $n))
            ->values()->all();
    }

    /** Nomes digitados todos em maiúsculas ou minúsculas ganham a grafia usual. */
    private function capitalizar(string $nome): string
    {
        if ($nome !== mb_strtoupper($nome) && $nome !== mb_strtolower($nome)) {
            return $nome;
        }

        return collect(explode(' ', mb_strtolower($nome)))
            ->map(fn ($p, $i) => $i > 0 && in_array($p, ['de', 'da', 'do', 'das', 'dos', 'e'], true) ? $p : Str::ucfirst($p))
            ->implode(' ');
    }

    private function iniciais(string $nome): string
    {
        // títulos ("Prof.", "Dra.") não contam nas iniciais
        $partes = array_values(array_filter(explode(' ', $nome), fn ($p) => !str_ends_with($p, '.') && mb_strlen($p) > 2));
        $partes = $partes ?: explode(' ', $nome);
        $ultima = count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '';

        return mb_strtoupper(mb_substr($partes[0], 0, 1) . $ultima);
    }
}
