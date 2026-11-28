<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\NoticiaEvento;
use App\Services\NotificadorDeNoticias;
use App\Support\HtmlSeguro;
use Illuminate\Http\Request;

/** Notícias e informações do evento, publicadas ao longo do tempo na página pública. */
class NoticiaEventoController extends Controller
{
    public function index(Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $noticias = $evento->noticias()->orderByDesc('fixada')->orderByDesc('publicado_em')->orderByDesc('id')->paginate(15);

        $totalDestinatarios = app(NotificadorDeNoticias::class)->destinatarios($evento)->count();

        return view('admin.noticias.index', compact('evento', 'noticias', 'totalDestinatarios'));
    }

    public function store(Request $request, Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $noticia = $evento->noticias()->create($this->dados($request) + ['criado_por' => $request->user()->id]);

        return redirect()->route('admin.noticias.index', $evento)->with('success', 'Notícia salva.' . $this->avisar($noticia));
    }

    public function edit(Evento $evento, NoticiaEvento $noticia)
    {
        $this->conferir($evento, $noticia);

        $totalDestinatarios = app(NotificadorDeNoticias::class)->destinatarios($evento)->count();

        return view('admin.noticias.edit', compact('evento', 'noticia', 'totalDestinatarios'));
    }

    public function update(Request $request, Evento $evento, NoticiaEvento $noticia)
    {
        $this->conferir($evento, $noticia);

        $noticia->update($this->dados($request, $noticia));

        return redirect()->route('admin.noticias.index', $evento)->with('success', 'Notícia atualizada.' . $this->avisar($noticia));
    }

    /** Botão "Reenviar aviso". */
    public function avisarDeNovo(Evento $evento, NoticiaEvento $noticia, NotificadorDeNoticias $notificador)
    {
        $this->conferir($evento, $noticia);

        try {
            $n = $notificador->reenviar($noticia);
        } catch (\DomainException $e) {
            return redirect()->route('admin.noticias.index', $evento)->withErrors(['aviso' => $e->getMessage()]);
        }

        return redirect()->route('admin.noticias.index', $evento)
            ->with('success', $n ? "{$n} e-mail(s) aos inscritos colocado(s) na fila de envio." : 'Não há inscritos com e-mail para avisar.');
    }

    public function destroy(Evento $evento, NoticiaEvento $noticia)
    {
        $this->conferir($evento, $noticia);
        $noticia->delete();

        return redirect()->route('admin.noticias.index', $evento)->with('success', 'Notícia excluída.');
    }

    /** Envia o aviso (se marcado e a notícia já estiver no ar) e devolve a frase para a mensagem de sucesso. */
    private function avisar(NoticiaEvento $noticia): string
    {
        if ($noticia->notificado_em) {
            return '';
        }
        if (!$noticia->notificar_inscritos) {
            return ' Nenhum e-mail foi enviado: a opção "Avisar os inscritos por e-mail" está desligada. Para avisar, edite a notícia, ligue a opção e salve.';
        }
        if ($noticia->situacao() === 'agendada') {
            return ' Os inscritos serão avisados por e-mail quando ela entrar no ar.';
        }
        if ($noticia->situacao() === 'rascunho') {
            return ' O e-mail aos inscritos só sai quando a notícia ficar visível no site.';
        }
        $n = app(NotificadorDeNoticias::class)->notificar($noticia);

        return $n ? " {$n} e-mail(s) aos inscritos colocado(s) na fila de envio." : ' Não há inscritos com e-mail para avisar.';
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
        if (!$atual?->notificado_em) {   // depois de enviado, o aviso não se repete nem se desfaz
            $d['notificar_inscritos'] = $request->boolean('notificar_inscritos');
        }

        return $d;
    }
}
