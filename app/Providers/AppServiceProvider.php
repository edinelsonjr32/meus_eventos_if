<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Limites independentes (um contador por finalidade) para as rotas públicas dos Anais
        \Illuminate\Support\Facades\RateLimiter::for('anais', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('anais-certificado', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($request->ip()));

        // Presença de um autor apresentador na sessão do trabalho confirma a apresentação
        \App\Models\Frequencia::created(function (\App\Models\Frequencia $frequencia) {
            app(\App\Services\ApresentacaoService::class)->sincronizarPorFrequencia($frequencia);
        });

        //
    }
}
