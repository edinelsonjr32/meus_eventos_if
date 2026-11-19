<?php

namespace App\Console\Commands;

use App\Services\AgendaApresentacoes;
use Illuminate\Console\Command;

class ApresentacoesLembrar extends Command
{
    protected $signature = 'apresentacoes:lembrar {--minutos=30 : antecedência do lembrete}';

    protected $description = 'Envia o lembrete (sala e horário) das apresentações orais que começam em breve';

    public function handle(AgendaApresentacoes $agenda): int
    {
        $n = $agenda->enviarLembretes((int) $this->option('minutos'));
        $this->info("Lembretes enfileirados: {$n}");

        return self::SUCCESS;
    }
}
