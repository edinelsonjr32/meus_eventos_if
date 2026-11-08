<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Http\Request;

/** Página inicial de quem tem o papel "Recepção": os eventos em que faz check-in e credenciamento. */
class RecepcaoController extends Controller
{
    public function index(Request $request)
    {
        $eventos = Evento::credenciamentoPor($request->user())
            ->withCount(['inscricoes as total_inscritos', 'inscricoes as total_credenciados' => fn ($q) => $q->whereNotNull('checkin_at')])
            ->orderByDesc('data_inicio')->get();

        return view('recepcao.index', compact('eventos'));
    }
}
