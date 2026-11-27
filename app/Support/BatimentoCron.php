<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Registro da última execução do agendador (cron). Fica em arquivo, e não no cache, porque o deploy
 * (optimize:clear) esvazia o cache e fazia o diagnóstico acusar um cron parado que estava funcionando.
 */
class BatimentoCron
{
    /** Folga (min) antes de considerar o cron parado: a hospedagem compartilhada costuma rodar a cada 15 minutos. */
    public const TOLERANCIA_MINUTOS = 20;

    public static function registrar(): void
    {
        @file_put_contents(self::arquivo(), now()->toIso8601String(), LOCK_EX);
    }

    public static function ultimo(): ?Carbon
    {
        $texto = is_file(self::arquivo()) ? trim((string) @file_get_contents(self::arquivo())) : '';

        try {
            return $texto !== '' ? Carbon::parse($texto) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function arquivo(): string
    {
        return config('queue.arquivo_batimento') ?: storage_path('framework/agendador-batimento');
    }
}
