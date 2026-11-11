<?php

namespace App\Http\Controllers;

/** Serve o ícone do site (favicon) direto do aplicativo: funciona mesmo quando a pasta pública fica fora do projeto. */
class IconeController extends Controller
{
    private const CACHE = 'public, max-age=604800';

    public function svg()
    {
        return response()->file(resource_path('icones/favicon.svg'), ['Content-Type' => 'image/svg+xml', 'Cache-Control' => self::CACHE]);
    }

    public function png()
    {
        return response()->file(resource_path('icones/icone-180.png'), ['Content-Type' => 'image/png', 'Cache-Control' => self::CACHE]);
    }
}
