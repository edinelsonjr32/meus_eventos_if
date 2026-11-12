<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Resultado de uma proposta de minicurso/oficina ou de uma inscrição de voluntário. */
class CandidaturaDecididaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $tipo,            // proposta | voluntario
        public string $evento,
        public string $titulo,
        public string $status,
        public string $statusRotulo,
        public ?string $parecer,
        public string $link,
        public ?string $programacao = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $assunto = $this->tipo === 'proposta' ? 'Sua proposta de minicurso/oficina' : 'Sua inscrição como voluntário(a)';

        return new Envelope(subject: "{$assunto}: {$this->statusRotulo} — {$this->evento}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.candidatura-decidida');
    }
}
