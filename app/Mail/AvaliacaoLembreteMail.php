<?php

namespace App\Mail;

use App\Models\TrabalhoAvaliacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AvaliacaoLembreteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public TrabalhoAvaliacao $avaliacao)
    {
    }

    public function envelope(): Envelope
    {
        $atrasada = $this->avaliacao->atrasada();

        return new Envelope(subject: ($atrasada ? 'Avaliação em atraso' : 'Lembrete: avaliação com prazo próximo') . " — {$this->avaliacao->trabalho->codigo}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.avaliacao-lembrete');
    }
}
