<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Atrás de proxy/túnel HTTPS (ngrok, nginx, etc.): garante esquema e cookies corretos
        $middleware->trustProxies(at: '*');
        $middleware->appendToGroup('web', \App\Http\Middleware\NormalizaCpf::class);

        // ADICIONE ESTA LINHA:
        $middleware->alias([
            'admin' => \App\Http\Middleware\IsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Todo erro registrado no log leva um código de referência e o contexto da requisição (sem dados digitados, sem senha,
        // sem query string). O mesmo código aparece na página de erro 500, para o usuário informar e o log ser achado pelo código.
        $exceptions->context(function (\Throwable $e): array {
            if (app()->runningInConsole() && !app()->runningUnitTests()) {
                return [];
            }

            $ref = strtoupper(\Illuminate\Support\Str::random(8));
            app()->instance('erro.ref', $ref);

            $contexto = ['ref' => $ref];
            try {
                $req = request();
                $contexto += [
                    'metodo' => $req->method(),
                    'url' => $req->url(),
                    'usuario' => $req->user()?->id,
                    'ip' => $req->ip(),
                    'agente' => \Illuminate\Support\Str::limit((string) $req->userAgent(), 90, ''),
                ];
            } catch (\Throwable) {
                // contexto parcial: nunca atrapalhar o registro do erro
            }

            return $contexto;
        });
    })->create();
