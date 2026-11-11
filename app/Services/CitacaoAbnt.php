<?php

namespace App\Services;

use App\Models\Anais;
use App\Models\Trabalho;
use Illuminate\Support\Collection;

/**
 * Referência bibliográfica (ABNT NBR 6023) de um trabalho publicado nos Trabalhos Publicados.
 * Ex.: SILVA, A. B.; SOUZA, C. Título. In: EVENTO, 2026, Óbidos. Anais [...]. Óbidos: Editora, 2026.
 */
class CitacaoAbnt
{
    /** Partículas que acompanham o sobrenome mas não o iniciam. */
    private const SUFIXOS = ['filho', 'filha', 'junior', 'júnior', 'jr', 'neto', 'neta', 'sobrinho', 'sobrinha'];

    public function gerar(Trabalho $trabalho, Anais $anais): string
    {
        $autores = $this->autores($trabalho);
        $evento = mb_strtoupper(trim($trabalho->evento->titulo ?? $anais->evento->titulo));

        // Convenção ABNT para dados ausentes: "S. l." (sem local) e "s. n." (sem editora)
        $local = filled($anais->local_publicacao) ? $anais->local_publicacao : 'S. l.';
        $editora = filled($anais->editora) ? $anais->editora : 's. n.';

        return sprintf(
            '%s %s. In: %s, %d, %s. Anais [...]. %s: %s, %d.',
            $autores,
            rtrim(trim($trabalho->titulo), '.'),
            $evento,
            $anais->ano,
            $local,
            $local,
            $editora,
            $anais->ano
        );
    }

    /** "SOBRENOME, N.; OUTRO, M." (até 3 autores; acima disso, o primeiro seguido de "et al."). */
    public function autores(Trabalho $trabalho): string
    {
        /** @var Collection $autores */
        $autores = $trabalho->autores->whereIn('tipo_autor', ['autor_principal', 'coautor'])->values();
        if ($autores->isEmpty()) {
            $autores = $trabalho->autores->values();
        }

        $formatados = $autores->map(fn ($a) => $this->formatar($a->nome_completo));

        // Evita ponto duplicado quando o último nome termina em inicial ("MENDES, C.")
        return $formatados->count() > 3
            ? $formatados->first() . ' et al.'
            : rtrim($formatados->implode('; '), '.') . '.';
    }

    /** "Maria da Silva Souza" → "SOUZA, M. S." */
    public function formatar(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome), -1, PREG_SPLIT_NO_EMPTY);
        if (count($partes) === 1) {
            return mb_strtoupper($partes[0]);
        }

        $sobrenome = array_pop($partes);
        // "Silva Junior", "Costa Neto": o sufixo acompanha o sobrenome anterior
        if (in_array(mb_strtolower($sobrenome), self::SUFIXOS, true) && $partes) {
            $sobrenome = array_pop($partes) . ' ' . $sobrenome;
        }

        $iniciais = collect($partes)
            ->reject(fn ($p) => in_array(mb_strtolower($p), ['da', 'de', 'do', 'das', 'dos', 'e'], true))
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)) . '.')
            ->implode(' ');

        return mb_strtoupper($sobrenome) . ($iniciais !== '' ? ", {$iniciais}" : '');
    }
}
