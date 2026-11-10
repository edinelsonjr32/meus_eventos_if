<?php

namespace App\Console\Commands;

use App\Services\RodadaTeste;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RodadaTestePreparar extends Command
{
    protected $signature = 'rodada-teste:preparar
        {--spec= : JSON da especificação (padrão: docs/rodada-teste-2026-11-10/05_especificacao_dados.json)}
        {--manifesto= : Caminho do manifesto.json (padrão: storage/app/rodada-teste/manifesto.json)}
        {--verificar : Só confere colisões com dados existentes (somente leitura)}
        {--dry-run : Mostra o plano sem criar nada}
        {--confirmo-banco= : Nome do banco, para confirmar onde vai gravar}';

    protected $description = 'Cria a massa de dados da rodada de testes (idempotente, com manifesto). Nunca apaga nada.';

    public function handle(): int
    {
        $con = DB::connection();
        $this->line('Ambiente: ' . app()->environment() . ' | driver: ' . $con->getDriverName() . ' | host: ' . ($con->getConfig('host') ?? '-') . ' | banco: ' . $con->getDatabaseName());

        $spec = $this->option('spec') ?: base_path('docs/rodada-teste-2026-11-10/05_especificacao_dados.json');
        if (!is_file($spec)) {
            $this->error("Spec não encontrada: {$spec}");

            return self::FAILURE;
        }
        $svc = new RodadaTeste(json_decode(file_get_contents($spec), true), $this->option('manifesto') ?: storage_path('app/rodada-teste/manifesto.json'));

        $colisoes = $svc->colisoes();
        if ($this->option('verificar') || $this->option('dry-run')) {
            $colisoes ? $this->warn("Colisões:\n - " . implode("\n - ", $colisoes)) : $this->info('Sem colisões: pode preparar.');
            $this->line('Criaria: 12 contas, 5 eventos, 3 áreas e 4 critérios por evento, 10 trabalhos em 4 eventos, atribuições e notas.');

            return $colisoes ? self::FAILURE : self::SUCCESS;
        }
        if ($this->option('confirmo-banco') !== $con->getDatabaseName()) {
            $this->error('Informe --confirmo-banco=' . $con->getDatabaseName() . ' para confirmar o banco que será alterado.');

            return self::FAILURE;
        }

        try {
            $m = $svc->preparar();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Preparado. Manifesto salvo. Contagens: ' . count($m['users']) . ' contas, ' . count($m['eventos']) . ' eventos, ' . collect($m['eventos'])->sum(fn ($e) => count($e['trabalhos'])) . ' trabalhos.');
        $this->line(json_encode($m['urls'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
