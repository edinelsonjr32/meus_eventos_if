<?php

/*
|--------------------------------------------------------------------------
| Ponto de entrada para hospedagem compartilhada (cPanel)
|--------------------------------------------------------------------------
| O aplicativo fica FORA da pasta pública (public_html), por segurança:
|   /home/CONTA/meuseventos/   <- código, .env, storage, vendor (inacessível pela web)
|   /home/CONTA/public_html/   <- somente este index.php, .htaccess e os arquivos públicos
|
| Se o seu domínio/subdomínio apontar para uma subpasta (ex.: public_html/eventos),
| ajuste APP_BASE para refletir o caminho até a pasta do aplicativo.
*/

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Caminho da pasta do aplicativo, relativo a este arquivo
$base = __DIR__ . '/../meuseventos';

if (file_exists($maintenance = $base . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base . '/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once $base . '/bootstrap/app.php';

// A pasta pública é esta (não "meuseventos/public"): assets e o link storage ficam aqui
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
