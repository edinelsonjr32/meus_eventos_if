<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Resumo dos erros do log de produção e busca pelo código de referência exibido na página 500.
 *
 *   php artisan log:erros                 erros do último dia, agrupados
 *   php artisan log:erros --dias=7        última semana
 *   php artisan log:erros --ref=AB12CD34  mostra a ocorrência com esse código (com o início do rastreio)
 */
class LogErros extends Command
{
    protected $signature = 'log:erros
        {--dias=1 : Quantos dias olhar para trás}
        {--ref= : Código de referência (exibido na página de erro 500)}
        {--pasta= : Pasta dos logs (padrão: storage/logs)}';

    protected $description = 'Resume os erros do log (agrupados) ou mostra uma ocorrência pelo código de referência.';

    private const NIVEIS = 'ERROR|CRITICAL|ALERT|EMERGENCY';

    public function handle(): int
    {
        $pasta = $this->option('pasta') ?: storage_path('logs');
        $arquivos = glob(rtrim($pasta, '/') . '/*.log') ?: [];
        sort($arquivos);

        if (!$arquivos) {
            $this->warn("Nenhum arquivo de log em {$pasta}.");

            return self::SUCCESS;
        }

        if ($ref = strtoupper(trim((string) $this->option('ref')))) {
            return $this->mostrarRef($arquivos, $ref);
        }

        return $this->resumir($arquivos, max(1, (int) $this->option('dias')));
    }

    private function resumir(array $arquivos, int $dias): int
    {
        $limite = now()->subDays($dias)->startOfDay();
        $grupos = [];

        foreach ($arquivos as $arquivo) {
            foreach ($this->entradas($arquivo) as $e) {
                if ($e['quando'] < $limite) {
                    continue;
                }
                $chave = $e['nivel'] . '|' . $e['chave'];
                $g = $grupos[$chave] ?? ['n' => 0, 'nivel' => $e['nivel'], 'msg' => $e['msg'], 'ultimo' => $e['quando'], 'ref' => null, 'url' => null];
                $g['n']++;
                if ($e['quando'] >= $g['ultimo']) {
                    $g['ultimo'] = $e['quando'];
                    $g['ref'] = $e['ref'];
                    $g['url'] = $e['url'];
                }
                $grupos[$chave] = $g;
            }
        }

        if (!$grupos) {
            $this->info("Nenhum erro nos últimos {$dias} dia(s).");

            return self::SUCCESS;
        }

        uasort($grupos, fn ($a, $b) => $b['n'] <=> $a['n']);
        $this->info('Erros dos últimos ' . $dias . ' dia(s), agrupados (mais frequentes primeiro):');
        $this->table(['Qtd', 'Nível', 'Última vez', 'Ref.', 'Endereço', 'Mensagem'], collect($grupos)->map(fn ($g) => [
            $g['n'], $g['nivel'], $g['ultimo']->format('d/m H:i:s'), $g['ref'] ?? '-', $this->curto($g['url'] ?? '-', 38), $this->curto($g['msg'], 70),
        ])->values()->all());
        $this->line('Detalhe de uma ocorrência: php artisan log:erros --ref=CÓDIGO');

        return self::SUCCESS;
    }

    private function mostrarRef(array $arquivos, string $ref): int
    {
        foreach (array_reverse($arquivos) as $arquivo) {
            $linhas = file($arquivo, FILE_IGNORE_NEW_LINES) ?: [];
            foreach ($linhas as $i => $linha) {
                if (str_contains($linha, '"ref":"' . $ref . '"')) {
                    $this->info("Ocorrência {$ref} em " . basename($arquivo) . ':');
                    $this->line($this->curto($linha, 1500));
                    $this->line('--- início do rastreio ---');
                    foreach (array_slice($linhas, $i + 1, 14) as $s) {
                        if (preg_match('/^\[\d{4}-\d{2}-\d{2} /', $s)) {
                            break;
                        }
                        $this->line($this->curto($s, 200));
                    }

                    return self::SUCCESS;
                }
            }
        }

        $this->warn("Código {$ref} não encontrado nos logs disponíveis.");

        return self::FAILURE;
    }

    /** @return \Generator<array{quando:\Carbon\Carbon,nivel:string,msg:string,chave:string,ref:?string,url:?string}> */
    private function entradas(string $arquivo): \Generator
    {
        $fh = @fopen($arquivo, 'r');
        if (!$fh) {
            return;
        }
        $re = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] [\w.-]+\.(' . self::NIVEIS . '): (.*)$/';
        while (($linha = fgets($fh)) !== false) {
            if (!preg_match($re, rtrim($linha), $m)) {
                continue;
            }
            $msg = preg_split('/ \{"/', $m[3], 2)[0];
            preg_match('/"ref":"([A-Z0-9]+)"/', $m[3], $r);
            preg_match('/"url":"([^"]*)"/', $m[3], $u);
            yield [
                'quando' => \Carbon\Carbon::parse($m[1]),
                'nivel' => $m[2],
                'msg' => trim($msg),
                'chave' => preg_replace('/\d+/', 'N', mb_substr(trim($msg), 0, 110)),
                'ref' => $r[1] ?? null,
                'url' => isset($u[1]) ? stripslashes($u[1]) : null,
            ];
        }
        fclose($fh);
    }

    private function curto(string $t, int $max): string
    {
        return mb_strlen($t) > $max ? mb_substr($t, 0, $max - 1) . '…' : $t;
    }
}
