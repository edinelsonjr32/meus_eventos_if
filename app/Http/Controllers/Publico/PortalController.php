<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Anais;
use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Frequencia;
use App\Models\Inscricao;
use App\Models\User;

/** Página inicial pública: eventos, números da plataforma, Anais publicados e orientações. */
class PortalController extends Controller
{
    public function home()
    {
        // Eventos criados por contas fictícias de teste não aparecem no portal público
        $contasDeTeste = User::where('email', 'like', '%@teste.com')->select('id');
        $publicos = fn () => Evento::whereNotIn('criado_por', $contasDeTeste);

        $proximosEventos = $publicos()->where('data_fim', '>=', now())
            ->withCount(['atividades', 'inscricoes'])
            ->with(['trabalhoConfig', 'anais'])
            ->orderBy('data_inicio')->take(9)->get();

        // Edições passadas com Anais publicados (e quantos trabalhos entram em cada uma)
        $edicoesComAnais = $publicos()->whereHas('anais', fn ($q) => $q->where('status', 'publicado'))
            ->with(['anais', 'trabalhoConfig'])->orderByDesc('data_fim')->take(6)->get()
            ->each(fn (Evento $e) => $e->total_trabalhos = Anais::elegiveis($e)->count());

        $ids = $publicos()->pluck('id');
        $numeros = [
            'eventos' => $ids->count(),
            'participantes' => Inscricao::whereIn('evento_id', $ids)->distinct('participante_id')->count('participante_id'),
            'atividades' => Atividade::whereIn('evento_id', $ids)->count(),
            'certificados' => Frequencia::where('certificado_emitido', true)->whereHas('atividade', fn ($q) => $q->whereIn('evento_id', $ids))->count(),
            'trabalhos' => $edicoesComAnais->sum('total_trabalhos'),
        ];

        return view('welcome', compact('proximosEventos', 'edicoesComAnais', 'numeros'));
    }
}
