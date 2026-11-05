<?php

namespace App\Http\Middleware;

use App\Rules\Cpf;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Padroniza o campo "cpf" recebido (11 dígitos -> 000.000.000-00) antes da validação,
 * evitando o mesmo CPF salvo com e sem máscara.
 */
class NormalizaCpf
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'], true) && is_string($request->input('cpf'))) {
            $request->merge(['cpf' => Cpf::formatar($request->input('cpf'))]);
        }

        return $next($request);
    }
}
