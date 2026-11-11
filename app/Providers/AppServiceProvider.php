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

        // E-mails de conta (o Laravel os envia em inglês, em modelo próprio): mesmo layout dos demais e-mails do sistema
        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function ($usuario, string $token) {
            $link = url(route('password.reset', ['token' => $token, 'email' => $usuario->getEmailForPasswordReset()], false));

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Redefinição de senha — ' . config('app.name'))
                ->view('emails.redefinir-senha', [
                    'nome' => $usuario->name, 'link' => $link,
                    'minutos' => (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60),
                ]);
        });
        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function ($usuario, string $url) {
            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Confirme seu e-mail — ' . config('app.name'))
                ->view('emails.verificar-email', ['nome' => $usuario->name, 'link' => $url]);
        });

        // Limites independentes (um contador por finalidade) para as rotas públicas dos Trabalhos Publicados
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

        // Gestão de usuários: todo administrador real. Contas fictícias de teste (domínio em MAIL_BLOQUEAR_DOMINIOS) ficam de fora,
        // para não enxergarem os usuários reais. (O "superadmin" acima continua valendo para a busca de pessoas de todo o sistema.)
        \Illuminate\Support\Facades\Gate::define('gerir-usuarios', function (\App\Models\User $user) {
            if ($user->role !== 'admin') {
                return false;
            }
            $dominios = array_filter(array_map('trim', explode(',', mb_strtolower((string) config('mail.bloquear_dominios')))));
            $dominioDaConta = mb_strtolower(substr(strrchr($user->email, '@') ?: '', 1));

            return !in_array($dominioDaConta, $dominios, true);
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
