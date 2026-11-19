<?php

namespace App\Http\Controllers;

use App\Models\Trabalho;
use App\Services\AgendaApresentacoes;
use Illuminate\Http\Request;

/** Botão "Entrar na sala": só autores, avaliadores da apresentação e gestão chegam ao link (que não vai no HTML das telas). */
class ApresentacaoSalaController extends Controller
{
    public function entrar(Request $request, Trabalho $trabalho, AgendaApresentacoes $agenda)
    {
        $trabalho->loadMissing(['evento', 'sala']);
        abort_unless($agenda->podeEntrar($request->user(), $trabalho), 403);

        $link = $trabalho->sala?->ativo === false ? null : $trabalho->sala?->linkSeguro();
        if (!$trabalho->agendado() || !$link) {
            return back()->with('error', 'A sala desta apresentação ainda não foi definida.');
        }

        return redirect()->away($link);
    }
}
