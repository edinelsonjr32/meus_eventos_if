<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnóstico do ambiente (hospedagem): confere versão do PHP, extensões, permissões, banco,
 * migrations, cron, fila, Ghostscript, limites de upload e configurações de produção.
 * Saída com ✔ (ok), ! (atenção) e ✘ (falha); o código de saída é 1 se houver falha.
 */
class VerificarSistema extends Command
{
    protected $signature = 'sistema:verificar {--email= : Envia um e-mail de teste (síncrono) para este endereço}';

    protected $description = 'Verifica se o ambiente está pronto para rodar o sistema (PHP, extensões, banco, cron, fila, PDF)';

    private int $falhas = 0;

    private int $avisos = 0;

    private const EXTENSOES = [
        'bcmath', 'ctype', 'dom', 'fileinfo', 'filter', 'gd', 'iconv', 'json', 'libxml', 'mbstring', 'openssl',
        'pdo_mysql', 'session', 'simplexml', 'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib',
    ];

    public function handle(): int
    {
        $this->line('');
        $this->info('Verificação do ambiente — ' . config('app.name'));

        $this->secao('PHP');
        $this->php();

        $this->secao('Configuração');
        $this->configuracao();

        $this->secao('Banco de dados');
        $this->banco();

        $this->secao('Pastas e permissões');
        $this->pastas();

        $this->secao('Fila e agendador (cron)');
        $this->filaECron();

        $this->secao('PDF dos Trabalhos Publicados (Ghostscript)');
        $this->ghostscript();

        if ($destino = $this->option('email')) {
            $this->secao('E-mail');
            $this->email($destino);
        }

        $this->line('');
        $this->line($this->falhas === 0
            ? "<fg=green>Resultado: pronto</> ({$this->avisos} aviso(s))"
            : "<fg=red>Resultado: {$this->falhas} falha(s)</> e {$this->avisos} aviso(s) — corrija as falhas antes de publicar.");

        return $this->falhas === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function secao(string $titulo): void
    {
        $this->line('');
        $this->line("<options=bold>{$titulo}</>");
    }

    private function ok(string $msg): void
    {
        $this->line("  <fg=green>✔</> {$msg}");
    }

    private function aviso(string $msg): void
    {
        $this->avisos++;
        $this->line("  <fg=yellow>!</> {$msg}");
    }

    private function falha(string $msg): void
    {
        $this->falhas++;
        $this->line("  <fg=red>✘</> {$msg}");
    }

    private function php(): void
    {
        PHP_VERSION_ID >= 80300
            ? $this->ok('PHP ' . PHP_VERSION)
            : (PHP_VERSION_ID >= 80200
                ? $this->aviso('PHP ' . PHP_VERSION . ' funciona, mas o recomendado é 8.3 ou superior')
                : $this->falha('PHP ' . PHP_VERSION . ': é necessário 8.2 ou superior'));

        $faltando = array_values(array_filter(self::EXTENSOES, fn ($e) => !extension_loaded($e)));
        $faltando === []
            ? $this->ok('Extensões PHP necessárias presentes')
            : $this->falha('Extensões PHP ausentes: ' . implode(', ', $faltando) . ' (ative no cPanel > Selecionar versão do PHP > Extensões)');

        $limite = $this->bytes(ini_get('upload_max_filesize'));
        $post = $this->bytes(ini_get('post_max_size'));
        $mb = fn ($b) => round($b / 1048576) . ' MB';
        $necessario = 25 * 1048576;
        ($limite >= $necessario && $post >= $necessario)
            ? $this->ok('Upload: upload_max_filesize ' . $mb($limite) . ', post_max_size ' . $mb($post))
            : $this->aviso('Upload de PDF limitado (upload_max_filesize ' . $mb($limite) . ', post_max_size ' . $mb($post) . '): aumente para 25M ou mais');

        $mem = $this->bytes(ini_get('memory_limit'));
        ($mem === -1 || $mem >= 256 * 1048576)
            ? $this->ok('memory_limit ' . ini_get('memory_limit'))
            : $this->aviso('memory_limit ' . ini_get('memory_limit') . ': recomendado 256M (geração de PDF e planilhas)');
    }

    private function configuracao(): void
    {
        $producao = app()->isProduction();

        $producao ? $this->ok('APP_ENV=production') : $this->aviso('APP_ENV=' . app()->environment() . ' (em produção deve ser "production")');
        config('app.debug') && $producao
            ? $this->falha('APP_DEBUG=true em produção: exibe erros e dados sensíveis. Use APP_DEBUG=false')
            : $this->ok('APP_DEBUG=' . (config('app.debug') ? 'true (ambiente de desenvolvimento)' : 'false'));

        config('app.key') ? $this->ok('APP_KEY definida') : $this->falha('APP_KEY vazia: rode php artisan key:generate --force');

        $url = (string) config('app.url');
        str_starts_with($url, 'https://')
            ? $this->ok("APP_URL {$url}")
            : ($producao ? $this->aviso("APP_URL {$url}: em produção use https://") : $this->ok("APP_URL {$url}"));

        $this->ok('Fuso horário: ' . config('app.timezone') . ' (agora: ' . now()->format('d/m/Y H:i') . ')');

        if ($producao) {
            config('session.secure')
                ? $this->ok('Cookies de sessão somente em HTTPS (SESSION_SECURE_COOKIE=true)')
                : $this->aviso('SESSION_SECURE_COOKIE não está true');

            in_array(config('mail.default'), ['log', 'array'], true)
                ? $this->aviso('MAIL_MAILER=' . config('mail.default') . ': os e-mails NÃO serão enviados (configure SMTP)')
                : $this->ok('E-mail via ' . config('mail.default') . ' (' . config('mail.mailers.smtp.host') . ')');
        }
    }

    private function banco(): void
    {
        try {
            DB::connection()->getPdo();
            $this->ok('Conexão com o banco (' . DB::connection()->getDatabaseName() . ')');
        } catch (\Throwable $e) {
            $this->falha('Sem conexão com o banco: ' . str($e->getMessage())->limit(120));

            return;
        }

        if (!Schema::hasTable('migrations')) {
            $this->falha('Banco vazio: rode php artisan migrate --force');

            return;
        }

        $migrator = app('migrator');
        $arquivos = $migrator->getMigrationFiles(database_path('migrations'));
        $pendentes = array_diff(array_keys($arquivos), $migrator->getRepository()->getRan());
        $pendentes === []
            ? $this->ok('Migrations em dia (' . count($arquivos) . ')')
            : $this->falha(count($pendentes) . ' migration(s) pendente(s): rode php artisan migrate --force');

        if (Schema::hasTable('cursos')) {
            DB::table('cursos')->exists()
                ? $this->ok('Cursos e turmas cadastrados')
                : $this->aviso('Nenhum curso cadastrado: rode php artisan db:seed --class=EstruturaAcademicaSeeder --force');
        }

        if (Schema::hasTable('users')) {
            DB::table('users')->where('role', 'admin')->exists()
                ? $this->ok('Há ao menos um administrador')
                : $this->aviso('Nenhum administrador: rode php artisan usuario:criar-admin seu@email.com');
        }
    }

    private function pastas(): void
    {
        foreach (['storage/app', 'storage/app/private', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $rel) {
            $dir = base_path($rel);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            is_dir($dir) && is_writable($dir)
                ? $this->ok("{$rel} gravável")
                : $this->falha("{$rel} sem permissão de escrita (ajuste para 755/775 e o dono correto)");
        }

        $privado = storage_path('app/private/trabalhos');
        @mkdir($privado, 0755, true);
        is_dir($privado) && is_writable($privado)
            ? $this->ok('Pasta privada dos trabalhos (PDFs) gravável e fora do acesso público')
            : $this->falha('storage/app/private/trabalhos não pode ser criada/gravada');

        $link = public_path('storage');
        if (is_link($link) && realpath($link) === realpath(storage_path('app/public'))) {
            $this->ok('Link public/storage correto (imagens dos certificados)');
        } elseif (file_exists($link)) {
            $this->aviso('public/storage existe, mas não aponta para storage/app/public');
        } else {
            $this->aviso('Link public/storage ausente: rode php artisan storage:link (imagens de certificados não aparecerão)');
        }
    }

    private function filaECron(): void
    {
        $conexao = config('queue.default');
        $this->ok("Fila: {$conexao}" . (config('queue.via_cron') ? ' (processada pelo agendador a cada minuto)' : ''));

        if ($conexao === 'sync') {
            $this->aviso('Fila "sync": e-mails e PDFs rodam dentro da requisição (lento). Prefira QUEUE_CONNECTION=database + QUEUE_VIA_CRON=true');
        } elseif ($conexao === 'database' && Schema::hasTable('jobs')) {
            $pendentes = DB::table('jobs')->count();
            $falhos = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $antigo = DB::table('jobs')->min('created_at');
            // Idade em minutos (positiva): do job mais antigo até agora
            $idade = $antigo ? (int) \Carbon\Carbon::createFromTimestamp($antigo)->diffInMinutes(now()) : 0;

            ($pendentes > 0 && $idade > 10)
                ? $this->aviso("{$pendentes} job(s) parado(s) há {$idade} min: a fila não está sendo processada (confira o cron)")
                : $this->ok("Jobs pendentes: {$pendentes}");
            $falhos > 0 ? $this->aviso("{$falhos} job(s) com falha (php artisan queue:failed)") : $this->ok('Nenhum job com falha');
        }

        try {
            $batida = Cache::get('agendador:ultimo-batimento');
        } catch (\Throwable) {
            $batida = null;
        }

        if ($batida) {
            $min = (int) \Carbon\Carbon::parse($batida)->diffInMinutes(now());
            $min <= 5
                ? $this->ok("Cron ativo: último batimento há {$min} min")
                : $this->aviso("Cron parado? Último batimento há {$min} min (esperado: a cada minuto)");
        } else {
            $this->aviso('Cron ainda não registrou batimento. Cadastre: * * * * * php artisan schedule:run (e aguarde 1–2 min)');
        }
    }

    private function ghostscript(): void
    {
        $gs = config('trabalhos.ghostscript');

        try {
            $r = Process::timeout(10)->run([$gs, '--version']);
        } catch (\Throwable) {
            $r = null;
        }

        $r && $r->successful()
            ? $this->ok("Ghostscript {$gs} " . trim($r->output()))
            : $this->aviso("Ghostscript ('{$gs}') indisponível: o PDF compilado dos Trabalhos Publicados não será gerado (o resto do sistema funciona). Defina GS_BINARY com o caminho do binário, se existir");
    }

    private function email(string $destino): void
    {
        try {
            Mail::raw('Teste de e-mail do sistema ' . config('app.name') . ' — ' . now()->format('d/m/Y H:i'), fn ($m) => $m->to($destino)->subject('Teste de e-mail — ' . config('app.name')));
            $this->ok("E-mail de teste enviado para {$destino} (confira a caixa de entrada e o spam)");
        } catch (\Throwable $e) {
            $this->falha('Falha ao enviar e-mail: ' . str($e->getMessage())->limit(140));
        }
    }

    private function bytes(string|false $valor): int
    {
        $valor = trim((string) $valor);
        if ($valor === '' || $valor === '-1') {
            return -1;
        }
        $n = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g' => $n * 1073741824,
            'm' => $n * 1048576,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
