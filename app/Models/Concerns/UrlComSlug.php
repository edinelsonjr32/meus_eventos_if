<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Endereço amigável (SEO): "titulo-da-chamada-12", com o título como slug e o id no fim para diferenciar.
 * Endereços antigos (só o número) e com título desatualizado continuam funcionando; o controller redireciona ao canônico.
 */
trait UrlComSlug
{
    public function getRouteKey()
    {
        $slug = Str::slug(Str::limit((string) $this->titulo, 90, ''));

        return ($slug !== '' ? rtrim($slug, '-') . '-' : '') . $this->getKey();
    }

    public function resolveRouteBinding($valor, $campo = null)
    {
        if ($campo) {
            return parent::resolveRouteBinding($valor, $campo);
        }

        $valor = (string) $valor;
        if (ctype_digit($valor)) {
            return $this->whereKey((int) $valor)->first();
        }

        return preg_match('/-(\d+)$/', $valor, $m) ? $this->whereKey((int) $m[1])->first() : null;
    }

    /** Endereço canônico igual ao pedido? Quando não for (só id ou título antigo), devolve a URL certa para redirecionar. */
    public function urlCanonicaSe(string $valorPedido, string $rota): ?string
    {
        return $valorPedido === $this->getRouteKey() ? null : route($rota, $this);
    }
}
