<?php

namespace App\Mail;

use App\Models\Trabalho;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrabalhoSubmetidoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Trabalho $trabalho)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Trabalho recebido — {$this->trabalho->codigo}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.trabalho-submetido');
    }
}
