<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail único para avisar movimentações e mudanças de status (trabalhos, propostas de minicurso/oficina, voluntariado).
 * Leva dados simples (texto), sem modelos, para continuar válido mesmo se o registro mudar depois de enfileirado.
 *
 * $d: assunto, titulo, evento, item, situacao?, tipo (info|ok|alerta|erro), mensagem?, detalhes? (rótulo => valor),
 *     botao, href, area (nome da tela do perfil), area_href
 */
class MovimentacaoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public array $d)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->d['assunto']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.movimentacao');
    }
}
