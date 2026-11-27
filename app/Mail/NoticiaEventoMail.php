<?php

namespace App\Mail;

use App\Models\NoticiaEvento;
use App\Support\HtmlSeguro;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/** Aviso de notícia/informação nova do evento, para os inscritos. */
class NoticiaEventoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Excluir a notícia antes do envio cancela os e-mails que ainda estão na fila. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public NoticiaEvento $noticia)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: Str::limit("{$this->noticia->titulo} — {$this->noticia->evento->titulo}", 150));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.noticia-evento', with: [
            'resumo' => Str::limit(HtmlSeguro::paraTexto($this->noticia->conteudo), 600),
            'link' => route('evento.publico.show', $this->noticia->evento->slug) . '#noticias',
        ]);
    }
}
