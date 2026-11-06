<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lembretes diários de avaliações com prazo próximo ou vencido
Schedule::command('trabalhos:lembrar-avaliadores')->dailyAt('08:00');

// Batimento: permite confirmar (php artisan sistema:verificar) que o cron está rodando
Schedule::call(fn () => Cache::put('agendador:ultimo-batimento', now()->toIso8601String(), now()->addDay()))
    ->name('agendador-batimento')->everyMinute();

// Hospedagem compartilhada: não há processo permanente para a fila. Com QUEUE_VIA_CRON=true, o próprio
// agendador (um único cron por minuto) processa os jobs pendentes e encerra (e-mails, PDF dos Anais...).
if (env('QUEUE_VIA_CRON', false) && config('queue.default') !== 'sync') {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --timeout=300')
        ->name('fila-via-cron')->everyMinute()->withoutOverlapping(10);
}
