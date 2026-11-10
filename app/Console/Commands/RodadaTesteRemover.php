<?php

namespace App\Console\Commands;

use App\Services\RodadaTeste;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RodadaTesteRemover extends Command
{
    protected $signature = 'rodada-teste:remover
        {--spec= : JSON da especificação}
        {--manifesto= : Caminho do manifesto.json}
        {--dry-run : Lista o que seria apagado, sem apagar}
        {--confirmo-banco= : Nome do banco, obrigatório para apagar de verdade}';

    protected $description = 'Remove só o que está no manifesto da rodada de testes e os cadastros dos alunos (p01..p30@teste.com).';

    public function handle(): int
    {
        $con = DB::connection();
        $this->line('Ambiente: ' . app()->environment() . ' | host: ' . ($con->getConfig('host') ?? '-') . ' | banco: ' . $con->getDatabaseName());

        $spec = $this->option('spec') ?: base_path('docs/rodada-teste-2026-11-10/05_especificacao_dados.json');
        $svc = new RodadaTeste(is_file($spec) ? json_decode(file_get_contents($spec), true) : [], $this->option('manifesto') ?: storage_path('app/rodada-teste/manifesto.json'));

        $dry = (bool) $this->option('dry-run');
        if (!$dry && $this->option('confirmo-banco') !== $con->getDatabaseName()) {
            $this->error('Para apagar de verdade, rode antes com --dry-run e depois informe --confirmo-banco=' . $con->getDatabaseName());

            return self::FAILURE;
        }

        try {
            $r = $svc->remover(!$dry);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($dry ? 'DRY-RUN (nada foi apagado):' : 'Removido:');
        foreach ($r as $k => $v) {
            $this->line(sprintf('  %-22s %s', $k, is_array($v) ? ($v ? implode(', ', $v) : '-') : $v));
        }

        return empty($r['vinculos_externos']) ? self::SUCCESS : self::FAILURE;
    }
}
