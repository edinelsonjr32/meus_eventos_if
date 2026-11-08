<?php

namespace App\Mail;

use App\Models\ApresentacaoAvaliacao;
use App\Models\Trabalho;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApresentacaoAvaliadorAtribuidoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Trabalho $trabalho, public ApresentacaoAvaliacao $avaliacao)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Apresentação para avaliar — {$this->trabalho->evento->titulo}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.apresentacao-avaliador-atribuido');
    }
}
