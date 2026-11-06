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
        // Presença de um autor apresentador na sessão do trabalho confirma a apresentação
        \App\Models\Frequencia::created(function (\App\Models\Frequencia $frequencia) {
            app(\App\Services\ApresentacaoService::class)->sincronizarPorFrequencia($frequencia);
        });

        //
    }
}
