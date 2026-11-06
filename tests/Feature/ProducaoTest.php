<?php

use App\Models\Curso;
use App\Models\Turma;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\EstruturaAcademicaSeeder;
use Database\Seeders\EventoTesteSeeder;
use Database\Seeders\TrabalhosTesteSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

/** Itens de preparação para publicar em hospedagem compartilhada. */
function emProducao(): void
{
    app()->detectEnvironment(fn () => 'production');
}

// ---------- sistema:verificar ----------
it('o diagnóstico do ambiente roda e resume o estado', function () {
    $this->artisan('sistema:verificar')
        ->expectsOutputToContain('Verificação do ambiente')
        ->expectsOutputToContain('Migrations em dia')
        ->expectsOutputToContain('Pasta privada dos trabalhos')
        ->expectsOutputToContain('Ghostscript')
        ->expectsOutputToContain('Resultado:')
        ->assertExitCode(0);
});

it('o diagnóstico reprova APP_DEBUG ligado em produção', function () {
    emProducao();
    config(['app.debug' => true]);

    $this->artisan('sistema:verificar')->expectsOutputToContain('APP_DEBUG=true em produção')->assertExitCode(1);
});

it('o diagnóstico alerta e-mail em log e APP_URL sem https em produção', function () {
    emProducao();
    config(['app.debug' => false, 'app.url' => 'http://exemplo.com.br', 'mail.default' => 'log']);

    $this->artisan('sistema:verificar')
        ->expectsOutputToContain('os e-mails NÃO serão enviados')
        ->expectsOutputToContain('em produção use https://')
        ->assertExitCode(0);
});

it('o diagnóstico detecta migrations pendentes e job parado na fila', function () {
    config(['queue.default' => 'database']);   // nos testes a fila é 'sync'
    \Illuminate\Support\Facades\DB::table('migrations')->where('migration', 'like', '%create_anais_table')->delete();
    \Illuminate\Support\Facades\DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subHour()->timestamp, 'created_at' => now()->subHour()->timestamp]);

    $this->artisan('sistema:verificar')
        ->expectsOutputToContain('migration(s) pendente(s)')
        ->expectsOutputToContain('a fila não está sendo processada')
        ->assertExitCode(1);
});

it('o diagnóstico confirma o cron ativo e acusa cron parado', function () {
    \Illuminate\Support\Facades\Cache::put('agendador:ultimo-batimento', now()->subMinute()->toIso8601String());
    $this->artisan('sistema:verificar')->expectsOutputToContain('Cron ativo: último batimento há 1 min')->assertExitCode(0);

    \Illuminate\Support\Facades\Cache::put('agendador:ultimo-batimento', now()->subMinutes(30)->toIso8601String());
    $this->artisan('sistema:verificar')->expectsOutputToContain('Cron parado? Último batimento há 30 min')->assertExitCode(0);
});

it('o diagnóstico envia e-mail de teste quando pedido', function () {
    config(['mail.default' => 'array']);

    $this->artisan('sistema:verificar', ['--email' => 'teste@exemplo.com'])->expectsOutputToContain('E-mail de teste enviado para teste@exemplo.com')->assertExitCode(0);
});

it('o diagnóstico acusa Ghostscript indisponível sem derrubar o resto', function () {
    config(['trabalhos.ghostscript' => '/caminho/inexistente/gs']);

    $this->artisan('sistema:verificar')->expectsOutputToContain('indisponível')->assertExitCode(0);
});

// ---------- usuario:criar-admin ----------
it('cria o administrador com senha informada, verificada e criptografada', function () {
    $this->artisan('usuario:criar-admin', ['email' => 'Diretor@IFPA.edu.br', '--nome' => 'Diretor Geral', '--senha' => 'Senha-Forte-2026'])
        ->expectsOutputToContain('Administrador pronto: diretor@ifpa.edu.br')->assertExitCode(0);

    $u = User::where('email', 'diretor@ifpa.edu.br')->firstOrFail();
    expect($u->role)->toBe('admin')->and($u->name)->toBe('Diretor Geral')->and($u->email_verified_at)->not->toBeNull()
        ->and($u->password)->not->toBe('Senha-Forte-2026')->and(Hash::check('Senha-Forte-2026', $u->password))->toBeTrue();
});

it('recusa senhas fracas e e-mail inválido', function (string $senha) {
    $this->artisan('usuario:criar-admin', ['email' => 'a@b.com', '--senha' => $senha])->assertExitCode(1);
    expect(User::where('email', 'a@b.com')->exists())->toBeFalse();
})->with(['curta' => 'Abc123', 'só letras' => 'apenasletrasaqui', 'só números' => '123456789012']);

it('recusa e-mail inválido', function () {
    $this->artisan('usuario:criar-admin', ['email' => 'nao-e-email', '--senha' => 'Senha-Forte-2026'])->expectsOutputToContain('E-mail inválido')->assertExitCode(1);
});

it('promove um usuário existente mediante confirmação, mantendo o nome', function () {
    $u = User::factory()->create(['email' => 'ana@ifpa.edu.br', 'name' => 'Ana Original']);

    $this->artisan('usuario:criar-admin', ['email' => 'ana@ifpa.edu.br'])
        ->expectsConfirmation('O usuário ana@ifpa.edu.br já existe. Promover a administrador?', 'yes')
        ->expectsConfirmation('Definir uma nova senha para este usuário?', 'no')
        ->assertExitCode(0);

    expect($u->fresh()->role)->toBe('admin')->and($u->fresh()->name)->toBe('Ana Original');
});

it('não promove quando a confirmação é negada', function () {
    $u = User::factory()->create(['email' => 'bia@ifpa.edu.br']);

    $this->artisan('usuario:criar-admin', ['email' => 'bia@ifpa.edu.br'])
        ->expectsConfirmation('O usuário bia@ifpa.edu.br já existe. Promover a administrador?', 'no')->assertExitCode(1);

    expect($u->fresh()->role)->not->toBe('admin');
});

// ---------- Seeders ----------
it('os seeders de dados de teste não rodam em produção (senhas conhecidas)', function () {
    emProducao();

    // Em produção o db:seed exige confirmação; a trava está no próprio seeder, então ele é executado direto
    app(EventoTesteSeeder::class)->run();
    app(TrabalhosTesteSeeder::class)->run();

    expect(User::count())->toBe(0);
});

it('a estrutura acadêmica pode ser semeada várias vezes sem duplicar', function () {
    $this->seed(EstruturaAcademicaSeeder::class);
    $cursos = Curso::count();
    $turmas = Turma::count();
    $this->seed(EstruturaAcademicaSeeder::class);

    expect($cursos)->toBe(3)->and($turmas)->toBe(8)
        ->and(Curso::count())->toBe($cursos)->and(Turma::count())->toBe($turmas);
});

// ---------- Fuso horário, HTTPS e agendador ----------
it('usa o fuso horário de Belém por padrão', function () {
    expect(config('app.timezone'))->toBe(env('APP_TIMEZONE', 'America/Belem'))->and(now()->timezoneName)->toBe(config('app.timezone'));
});

it('em produção com APP_URL https, os links saem em https', function () {
    emProducao();
    config(['app.url' => 'https://eventos.exemplo.com.br']);

    (new AppServiceProvider($this->app))->boot();

    expect(route('home'))->toStartWith('https://');
    URL::forceScheme(null);
});

it('o agendador registra lembretes e o batimento do cron', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('trabalhos:lembrar-avaliadores')->expectsOutputToContain('agendador-batimento')->assertExitCode(0);
});

it('PUBLIC_PATH redefine a pasta pública também para o terminal (storage:link)', function () {
    $original = public_path();
    config(['app.public_path' => '/home/conta/public_html']);

    (new AppServiceProvider($this->app))->register();

    expect(public_path())->toBe('/home/conta/public_html')->and(public_path('storage'))->toBe('/home/conta/public_html/storage');
    app()->usePublicPath($original);
});

it('o link storage usa a pasta pública configurada (PUBLIC_PATH) já na leitura da configuração', function () {
    putenv('PUBLIC_PATH=/home/conta/public_html');
    $config = require config_path('filesystems.php');
    putenv('PUBLIC_PATH');

    expect(array_key_first($config['links']))->toBe('/home/conta/public_html/storage');
});

it('o .env de produção de exemplo traz os padrões seguros e nenhum segredo', function () {
    $env = collect(explode("\n", file_get_contents(base_path('.env.production.example'))))
        ->filter(fn ($l) => preg_match('/^[A-Z_]+=/', $l))
        ->mapWithKeys(function ($l) { [$k, $v] = explode('=', $l, 2); return [$k => trim($v, "\" ")]; });

    expect($env['APP_ENV'])->toBe('production')->and($env['APP_DEBUG'])->toBe('false')->and($env['SESSION_SECURE_COOKIE'])->toBe('true')
        ->and($env['QUEUE_VIA_CRON'])->toBe('true')->and($env['APP_TIMEZONE'])->toBe('America/Belem')->and($env['APP_URL'])->toStartWith('https://')
        ->and($env['APP_KEY'])->toBe('')->and($env['DB_PASSWORD'])->toBe('')->and($env['MAIL_PASSWORD'])->toBe('')
        ->and($env)->toHaveKeys(['PUBLIC_PATH', 'DB_DATABASE', 'DB_USERNAME', 'MAIL_HOST', 'GS_BINARY']);
});

it('o pacote de publicação nunca inclui segredos, testes nem seeders de teste', function () {
    $script = file_get_contents(base_path('deploy/build-release.sh'));

    foreach (["--exclude='.env'", "--exclude='tests'", "--exclude='database/seeders/EventoTesteSeeder.php'", "--exclude='database/seeders/TrabalhosTesteSeeder.php'", 'install --no-dev'] as $trecho) {
        expect($script)->toContain($trecho);
    }
    expect(file_get_contents(base_path('.gitignore')))->toContain('/deploy/dist')->toContain('.env');
});
