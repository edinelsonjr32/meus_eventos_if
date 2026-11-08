<?php

namespace App\Console\Commands;

use App\Services\Lgpd\TitularService;
use Illuminate\Console\Command;

class LgpdLimparRascunhos extends Command
{
    protected $signature = 'lgpd:limpar-rascunhos {--dias= : Idade mínima do rascunho (padrão: config privacidade.dias_rascunho)}';

    protected $description = 'LGPD (retenção): apaga rascunhos de trabalhos abandonados e seus arquivos';

    public function handle(TitularService $servico): int
    {
        $dias = (int) ($this->option('dias') ?: config('privacidade.dias_rascunho'));
        $n = $servico->limparRascunhos(max(1, $dias));

        $this->info("{$n} rascunho(s) abandonado(s) há mais de {$dias} dias apagado(s).");

        return self::SUCCESS;
    }
}
