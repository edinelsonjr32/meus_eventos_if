<?php

namespace App\Mail;

use App\Models\Trabalho;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso à gestão: o autor reenviou o trabalho após devolução ou pedido de correção. */
class TrabalhoReenviadoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Trabalho $trabalho, public string $statusAnterior)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Trabalho reenviado — {$this->trabalho->codigo}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.trabalho-reenviado');
    }
}
