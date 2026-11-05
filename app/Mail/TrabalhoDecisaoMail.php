<?php

namespace App\Mail;

use App\Models\Trabalho;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Resultado da avaliação comunicado aos autores. */
class TrabalhoDecisaoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const ROTULOS = [
        'aprovado' => 'Trabalho aprovado',
        'aprovado_com_ressalvas' => 'Trabalho aprovado com ressalvas',
        'reprovado' => 'Resultado da avaliação do trabalho',
    ];

    public function __construct(public Trabalho $trabalho, public string $decisao)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: (self::ROTULOS[$this->decisao] ?? 'Resultado da avaliação') . " — {$this->trabalho->codigo}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.trabalho-decisao');
    }
}
