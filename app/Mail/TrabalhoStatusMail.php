<?php

namespace App\Mail;

use App\Models\Trabalho;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso de mudança de status feita pela gestão (devolução ou rejeição na triagem). */
class TrabalhoStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Trabalho $trabalho, public string $assunto, public string $justificativa, public bool $podeEditar)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->assunto} — {$this->trabalho->codigo}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.trabalho-status');
    }
}
