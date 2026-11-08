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
        // Hospedagem compartilhada: a pasta pública (public_html) fica fora da pasta do aplicativo
        if ($publica = config('app.public_path')) {
            $this->app->usePublicPath($publica);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // O sistema usa Bootstrap: sem isto a paginação sai no estilo Tailwind (setas SVG gigantes e sem estilo)
        \Illuminate\Pagination\Paginator::useBootstrapFive();

        // Em produção com APP_URL https, todos os links e redirecionamentos saem em https
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

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
