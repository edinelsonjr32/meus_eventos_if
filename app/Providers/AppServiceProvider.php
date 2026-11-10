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

        // Superadministrador: quem enxerga/edita TODOS os usuários e busca pessoas de todo o sistema.
        // Sem SUPERADMIN_EMAILS definido, todo admin é superadmin (comportamento anterior).
        \Illuminate\Support\Facades\Gate::define('superadmin', function (\App\Models\User $user) {
            if ($user->role !== 'admin') {
                return false;
            }
            $lista = array_filter(array_map('trim', explode(',', mb_strtolower((string) config('app.superadmin_emails')))));

            return $lista === [] || in_array(mb_strtolower($user->email), $lista, true);
        });

        // Contas fictícias de teste (domínio em MAIL_BLOQUEAR_DOMINIOS) nunca recebem e-mail de verdade
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSending::class, function ($evento) {
            $dominios = array_filter(array_map('trim', explode(',', mb_strtolower((string) config('mail.bloquear_dominios')))));
            if ($dominios === []) {
                return null;
            }
            $enderecos = array_merge($evento->message->getTo(), $evento->message->getCc(), $evento->message->getBcc());
            if ($enderecos === []) {
                return null;
            }
            foreach ($enderecos as $endereco) {
                $dominio = mb_strtolower(substr(strrchr($endereco->getAddress(), '@') ?: '', 1));
                if (!in_array($dominio, $dominios, true)) {
                    return null;
                }
            }

            return false; // todos os destinatários são de teste: cancela o envio
        });
    }
}
