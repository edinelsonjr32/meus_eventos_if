<?php

/**
 * Em produção roda `artisan optimize` (config:cache): depois disso, env() fora de config/ devolve null.
 * Foi assim que a fila deixou de ser registrada no agendador. Este teste impede o erro de voltar.
 */
it('nenhum código fora de config/ lê variáveis com env() (quebra com config:cache)', function () {
    $achados = [];
    foreach (['app', 'routes', 'bootstrap', 'database', 'resources/views'] as $pasta) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($pasta), FilesystemIterator::SKIP_DOTS));
        foreach ($it as $arquivo) {
            if (!str_ends_with($arquivo->getFilename(), '.php')) {
                continue;
            }
            foreach (file($arquivo->getPathname()) as $n => $linha) {
                // bootstrap/app.php e testes podem usar; comentários não contam
                if (preg_match('/(?<![\w>:$])env\(/', $linha) && !str_starts_with(trim($linha), '//') && !str_starts_with(trim($linha), '*')) {
                    $achados[] = str_replace(base_path() . '/', '', $arquivo->getPathname()) . ':' . ($n + 1);
                }
            }
        }
    }

    expect($achados)->toBe([]);
});

it('a fila via cron é uma opção de configuração', function () {
    expect(config('queue.via_cron'))->toBeBool();
});
