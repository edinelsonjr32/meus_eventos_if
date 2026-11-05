<?php

namespace App\Mail;

use App\Models\Trabalho;
use App\Models\TrabalhoAvaliacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AvaliadorAtribuidoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Trabalho $trabalho, public TrabalhoAvaliacao $avaliacao)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Novo trabalho para avaliar — {$this->trabalho->evento->titulo}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.avaliador-atribuido');
    }
}
