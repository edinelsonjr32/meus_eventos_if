<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Para onde a pessoa vai depois de entrar ou criar a conta.
 *
 * Regra: sempre a própria dashboard (a rota "dashboard" decide pelo papel: admin ou inscrito).
 * Exceções: quem veio de um QR Code de presença (/frequencia/{token}) ou de um formulário aberto a visitantes volta para onde estava.
 */
class DestinoPosAutenticacao
{
    public static function redirecionar(Request $request): RedirectResponse
    {
        $pretendido = (string) $request->session()->pull('url.intended', '');
        $caminho = (string) (parse_url($pretendido, PHP_URL_PATH) ?? '');

        if ($pretendido !== '' && self::caminhoPermitido($caminho) && self::mesmoSite($pretendido, $request)) {
            return redirect()->to($pretendido);
        }

        return redirect()->route(self::rotaDashboard($request->user()));
    }

    /** Presença por QR Code e formulários abertos a visitantes (proposta, voluntariado, submissão de trabalho). */
    private static function caminhoPermitido(string $caminho): bool
    {
        return preg_match('#^/frequencia/[^/]+$#', $caminho) === 1
            || preg_match('#^/(propostas|voluntariado)/chamada/[^/]+$#', $caminho) === 1
            || preg_match('#^/e/[^/]+/trabalhos/submeter$#', $caminho) === 1;
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
