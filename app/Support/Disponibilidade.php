<?php

namespace App\Support;

use App\Models\Voluntario;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/** Disponibilidade por dia e período (manhã 08–12, tarde 14–18, noite 18–22): validação, resumo e conferência com um horário. */
class Disponibilidade
{
    /** Mantém só dias válidos e períodos conhecidos: ["2026-11-12" => ["manha", "tarde"]]. */
    public static function normalizar(mixed $entrada, ?array $diasValidos = null): array
    {
        $saida = [];
        foreach ((array) $entrada as $dia => $periodos) {
            $validos = array_values(array_intersect(array_keys(Voluntario::PERIODOS), (array) $periodos));
            if ($validos && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dia) && ($diasValidos === null || in_array($dia, $diasValidos, true))) {
                $saida[$dia] = $validos;
            }
        }
        ksort($saida);

        return $saida;
    }

    /** Dias/períodos exigidos pelo intervalo que a pessoa não marcou como disponíveis, e trechos fora do horário comercial. */
    public static function faltas(array $dias, CarbonInterface $inicio, CarbonInterface $fim): array
    {
        $faltas = [];

        for ($dia = Carbon::instance($inicio)->startOfDay(); $dia->lt($fim); $dia->addDay()) {
            $total = max(0, Carbon::instance($inicio)->max($dia)->diffInMinutes(Carbon::instance($fim)->min($dia->copy()->addDay()), false));
            $coberto = 0;
            foreach (Voluntario::PERIODOS as $chave => $p) {
                $de = $dia->copy()->addHours($p['de']);
                $ate = $dia->copy()->addHours($p['ate']);
                if ($inicio->lt($ate) && $fim->gt($de)) {
                    $coberto += Carbon::instance($inicio)->max($de)->diffInMinutes(Carbon::instance($fim)->min($ate));
                    if (!in_array($chave, $dias[$dia->format('Y-m-d')] ?? [], true)) {
                        $faltas[] = $dia->format('d/m') . ' (' . mb_strtolower($p['rotulo']) . ')';
                    }
                }
            }
            if ($total - $coberto > 0) {
                $faltas[] = $dia->format('d/m') . ' (fora do horário de disponibilidade: ' . collect(Voluntario::PERIODOS)->pluck('faixa')->implode(', ') . ')';
            }
        }

        return $faltas;
    }

    /** "12/11: manhã, tarde · 13/11: noite". */
    public static function resumo(array $dias): string
    {
        return collect($dias)->sortKeys()->map(fn ($ps, $dia) => Carbon::parse($dia)->format('d/m') . ': ' . collect($ps)->map(fn ($p) => mb_strtolower(Voluntario::PERIODOS[$p]['rotulo']))->implode(', '))->implode(' · ');
    }
}
