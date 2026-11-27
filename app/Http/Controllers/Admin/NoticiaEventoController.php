<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\NoticiaEvento;
use App\Support\HtmlSeguro;
use Illuminate\Http\Request;

/** Notícias e informações do evento, publicadas ao longo do tempo na página pública. */
class NoticiaEventoController extends Controller
{
    public function index(Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $noticias = $evento->noticias()->orderByDesc('fixada')->orderByDesc('publicado_em')->orderByDesc('id')->paginate(15);

        return view('admin.noticias.index', compact('evento', 'noticias'));
    }

    public function store(Request $request, Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $evento->noticias()->create($this->dados($request) + ['criado_por' => $request->user()->id]);

        return redirect()->route('admin.noticias.index', $evento)->with('success', 'Notícia salva.');
    }

    public function edit(Evento $evento, NoticiaEvento $noticia)
    {
        $this->conferir($evento, $noticia);

        return view('admin.noticias.edit', compact('evento', 'noticia'));
    }

    public function update(Request $request, Evento $evento, NoticiaEvento $noticia)
    {
        $this->conferir($evento, $noticia);

        $noticia->update($this->dados($request, $noticia));

        return redirect()->route('admin.noticias.index', $evento)->with('success', 'Notícia atualizada.');
    }

    public function destroy(Evento $evento, NoticiaEvento $noticia)
    {
        $this->conferir($evento, $noticia);
        $noticia->delete();

        return redirect()->route('admin.noticias.index', $evento)->with('success', 'Notícia excluída.');
    }

    private function conferir(Evento $evento, NoticiaEvento $noticia): void
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($noticia->evento_id === $evento->id, 404);
    }

    private function dados(Request $request, ?NoticiaEvento $atual = null): array
    {
        $d = $request->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'conteudo' => ['required', 'string', 'max:60000'],
            'publicado_em' => ['nullable', 'date'],
        ], ['titulo.required' => 'Informe o título.', 'conteudo.required' => 'Escreva o texto da notícia.']);

        $d['conteudo'] = HtmlSeguro::limpar($d['conteudo']);
        if ($d['conteudo'] === null) {
            throw \Illuminate\Validation\ValidationException::withMessages(['conteudo' => 'Escreva o texto da notícia.']);
        }
        $d['publicado_em'] = $d['publicado_em'] ?? $atual?->publicado_em ?? now();
        $d['publicada'] = $request->boolean('publicada');
        $d['fixada'] = $request->boolean('fixada');

        return $d;
    }
}
