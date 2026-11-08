<?php

namespace App\Console\Commands;

use App\Services\Lgpd\TitularService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LgpdExportar extends Command
{
    protected $signature = 'lgpd:exportar {identificador : E-mail ou CPF do titular} {--saida= : Caminho do arquivo JSON (padrão: storage/app/private/lgpd)}';

    protected $description = 'LGPD: exporta todos os dados de um titular (direito de acesso/portabilidade) em JSON';

    public function handle(TitularService $servico): int
    {
        $identificador = $this->argument('identificador');

        ['email' => $email, 'cpf' => $cpf] = TitularService::normalizar($identificador);
        if (!$email && !$cpf) {
            $this->error('Informe um e-mail válido ou um CPF válido.');

            return self::FAILURE;
        }

        if (!$servico->encontrou($servico->localizar($identificador))) {
            $this->warn('Nenhum dado encontrado para este titular.');

            return self::FAILURE;
        }

        $json = json_encode($servico->exportar($identificador), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($saida = $this->option('saida')) {
            file_put_contents($saida, $json);
            $this->info("Arquivo gerado: {$saida}");
        } else {
            $nome = 'lgpd/titular-' . substr(hash('sha256', mb_strtolower(trim($identificador))), 0, 12) . '-' . now()->format('Ymd-His') . '.json';
            Storage::disk('local')->put($nome, $json);
            $this->info('Arquivo gerado: ' . Storage::disk('local')->path($nome));
        }

        $this->line('Contém dados pessoais: entregue somente ao titular, por canal seguro, e apague o arquivo depois.');

        return self::SUCCESS;
    }
}
