<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lembretes diários de avaliações com prazo próximo ou vencido
Illuminate\Support\Facades\Schedule::command('trabalhos:lembrar-avaliadores')->dailyAt('08:00');
