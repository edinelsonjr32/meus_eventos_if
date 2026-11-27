<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lembretes diários de avaliações com prazo próximo ou vencido
Schedule::command('trabalhos:lembrar-avaliadores')->dailyAt('08:00');

// Lembrete (sala e horário) das apresentações orais que começam nos próximos 30 minutos
Schedule::command('apresentacoes:lembrar')->everyFiveMinutes()->withoutOverlapping(4);

// Aviso por e-mail das notícias de evento agendadas, quando entram no ar
Schedule::command('noticias:notificar')->everyFiveMinutes()->withoutOverlapping(4);

// Batimento: permite confirmar (php artisan sistema:verificar) que o cron está rodando
Schedule::call(fn () => \App\Support\BatimentoCron::registrar())
    ->name('agendador-batimento')->everyMinute();

// Hospedagem compartilhada: não há processo permanente para a fila. Com QUEUE_VIA_CRON=true, o próprio
// agendador (um único cron) processa os jobs pendentes e encerra (e-mails, PDF dos Anais...).
if (config('queue.via_cron') && config('queue.default') !== 'sync') {
    Schedule::command('queue:work --stop-when-empty --max-time=' . (int) config('queue.cron_max_segundos') . ' --tries=3 --timeout=600')
        ->name('fila-via-cron')->everyMinute()->withoutOverlapping(15);
}

// Retenção (LGPD): rascunhos de trabalhos abandonados são apagados semanalmente
Schedule::command('lgpd:limpar-rascunhos')->weeklyOn(0, '03:30');
