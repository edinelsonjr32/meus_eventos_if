<?php

namespace App\Mail;

use App\Models\CertificadoTrabalho;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificadoTrabalhoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public CertificadoTrabalho $certificado)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Seu certificado está disponível — {$this->certificado->trabalho->codigo}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.certificado-trabalho');
    }
}
