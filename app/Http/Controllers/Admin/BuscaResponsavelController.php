<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Services\ResponsaveisDaAtividade;
use Illuminate\Http\Request;

/** Busca de pessoas para vincular como responsáveis por uma atividade. */
class BuscaResponsavelController extends Controller
{
    public function __invoke(Request $request, Evento $evento, ResponsaveisDaAtividade $responsaveis)
    {
        $this->authorize('gerenciar', $evento);

        $termo = trim($request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q']);

        return response()->json(['results' => $responsaveis->buscar($request->user(), $evento, $termo)]);
    }
}
