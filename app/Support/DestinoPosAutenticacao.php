<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Para onde a pessoa vai depois de entrar ou criar a conta.
 *
 * Regra: sempre a própria dashboard (a rota "dashboard" decide pelo papel: admin ou inscrito).
 * Única exceção: quem veio de um QR Code de presença (/frequencia/{token}) volta para registrar a presença.
 */
class DestinoPosAutenticacao
{
    public static function redirecionar(Request $request): RedirectResponse
    {
        $pretendido = (string) $request->session()->pull('url.intended', '');
        $caminho = (string) (parse_url($pretendido, PHP_URL_PATH) ?? '');

        if ($pretendido !== '' && preg_match('#^/frequencia/[^/]+$#', $caminho) === 1 && self::mesmoSite($pretendido, $request)) {
            return redirect()->to($pretendido);
        }

        return redirect()->route(self::rotaDashboard($request->user()));
    }

    /** Dashboard da pessoa pelo papel, direto (sem passar por /dashboard, para o aviso da sessão não se perder no redirecionamento duplo). */
    public static function rotaDashboard(?\App\Models\User $user): string
    {
        return $user && $user->role === 'admin' ? 'admin.dashboard' : 'inscrito.dashboard';
    }

    private static function mesmoSite(string $url, Request $request): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host === null || $host === $request->getHost();
    }
}
