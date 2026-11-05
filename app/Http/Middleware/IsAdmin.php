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

            // Qualquer usuário logado que não seja admin vai para a área do inscrito
            if (Auth::check()) {
                return redirect()->route('inscrito.dashboard');
            }

            abort(403, 'Acesso não autorizado. Apenas administradores.');
        }

        return $next($request);
    }
}
