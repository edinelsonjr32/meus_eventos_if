<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->pasta = sys_get_temp_dir() . '/logs-teste-' . uniqid();
    mkdir($this->pasta);
});

afterEach(function () {
    foreach (glob($this->pasta . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($this->pasta);
});

it('um erro 500 vai ao log com código de referência e contexto, e a página mostra o mesmo código', function () {
    $arquivo = $this->pasta . '/laravel.log';
    config(['logging.default' => 'single', 'logging.channels.single.path' => $arquivo, 'app.debug' => false]);
    app('log')->forgetChannel('single');

    Route::get('/_erro-de-teste', fn () => throw new RuntimeException('falha de teste'));

    $user = mkUser('user');
    $resposta = $this->actingAs($user)->get('/_erro-de-teste?senha=segredo123');

    $resposta->assertStatus(500)->assertSee('Código do erro');
    $log = file_get_contents($arquivo);
    expect($log)->toContain('falha de teste')->and($log)->toContain('"ref":"')->and($log)->toContain('"metodo":"GET"')
        ->and($log)->toContain('/_erro-de-teste')->and($log)->toContain('"usuario":' . $user->id)
        ->and($log)->not->toContain('segredo123');   // a query string não vai ao log

    preg_match('/"ref":"([A-Z0-9]{8})"/', $log, $m);
    $resposta->assertSee($m[1]);
});

it('404 e 403 não geram registro de erro', function () {
    $arquivo = $this->pasta . '/laravel.log';
    config(['logging.default' => 'single', 'logging.channels.single.path' => $arquivo, 'app.debug' => false]);
    app('log')->forgetChannel('single');

    $this->get('/nao-existe-xyz')->assertNotFound();
    expect(is_file($arquivo) ? file_get_contents($arquivo) : '')->not->toContain('ERROR');
});

it('log:erros agrupa por mensagem, conta e mostra a última ocorrência', function () {
    $hoje = now()->format('Y-m-d');
    $ontemAntigo = now()->subDays(10)->format('Y-m-d');
    file_put_contents($this->pasta . '/laravel.log', implode("\n", [
        "[$hoje 09:44:00] production.ERROR: SQLSTATE[23000]: Duplicate entry 'evento-novo-d3-2026' for key 'x' {\"ref\":\"AAAA1111\",\"url\":\"https:\\/\\/s.com\\/admin\\/eventos\",\"usuario\":7}",
        '[stacktrace]',
        '#0 app/Http/Controllers/Admin/EventoController.php(60): store()',
        "[$hoje 09:45:00] production.ERROR: SQLSTATE[23000]: Duplicate entry 'evento-novo-d2-2026' for key 'x' {\"ref\":\"BBBB2222\",\"url\":\"https:\\/\\/s.com\\/admin\\/eventos\"}",
        "[$hoje 10:00:00] production.WARNING: aviso qualquer",
        "[$hoje 11:00:00] production.CRITICAL: outro problema {\"ref\":\"CCCC3333\"}",
        "[$ontemAntigo 08:00:00] production.ERROR: erro antigo {\"ref\":\"DDDD4444\"}",
    ]) . "\n");

    Artisan::call('log:erros', ['--pasta' => $this->pasta, '--dias' => 1]);
    $saida = Artisan::output();
    expect($saida)->toContain('SQLSTATE[23000]')->and($saida)->toContain('BBBB2222')->and($saida)->toContain('CRITICAL')
        ->and($saida)->not->toContain('erro antigo')->and($saida)->not->toContain('aviso qualquer');

    // o mesmo erro (com slugs diferentes) aparece agrupado, com contagem 2
    expect($saida)->toMatch('/\|\s*2\s*\|\s*ERROR/');
});

it('log:erros --ref mostra a ocorrência e o início do rastreio; código inexistente falha', function () {
    $hoje = now()->format('Y-m-d');
    file_put_contents($this->pasta . '/laravel.log',
        "[$hoje 09:44:00] production.ERROR: falha X {\"ref\":\"AAAA1111\"}\n#0 app/A.php(1): a()\n#1 app/B.php(2): b()\n[$hoje 09:45:00] production.ERROR: outra {\"ref\":\"ZZZZ9999\"}\n");

    expect(Artisan::call('log:erros', ['--pasta' => $this->pasta, '--ref' => 'aaaa1111']))->toBe(0);
    $saida = Artisan::output();
    expect($saida)->toContain('falha X')->and($saida)->toContain('app/A.php')->and($saida)->not->toContain('outra');

    expect(Artisan::call('log:erros', ['--pasta' => $this->pasta, '--ref' => 'NAOEXISTE']))->toBe(1);
});

it('log:erros sem arquivos ou sem erros responde com mensagem clara', function () {
    Artisan::call('log:erros', ['--pasta' => $this->pasta]);
    expect(Artisan::output())->toContain('Nenhum arquivo de log');

    file_put_contents($this->pasta . '/laravel.log', '[' . now()->format('Y-m-d H:i:s') . "] production.INFO: tudo bem\n");
    Artisan::call('log:erros', ['--pasta' => $this->pasta]);
    expect(Artisan::output())->toContain('Nenhum erro');
});
