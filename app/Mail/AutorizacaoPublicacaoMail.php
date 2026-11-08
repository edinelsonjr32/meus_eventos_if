<?php

namespace App\Mail;

use App\Models\Trabalho;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AutorizacaoPublicacaoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Trabalho $trabalho)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Autorização para publicar seu trabalho nos Anais — {$this->trabalho->evento->titulo}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.autorizacao-publicacao');
    }
}
