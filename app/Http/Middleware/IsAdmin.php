<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Se não estiver logado ou não for admin, aborta ou redireciona
        if (!Auth::check() || Auth::user()->role !== 'admin') {

            // Se for um usuário comum tentando acessar admin, manda para a área dele
            if (Auth::check() && Auth::user()->role === 'user') {
                return redirect()->route('inscrito.dashboard');
            }

            abort(403, 'Acesso não autorizado. Apenas administradores.');
        }

        return $next($request);
    }
}
