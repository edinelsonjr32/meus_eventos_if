<?php

namespace App\Mail;

use App\Models\Trabalho;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sala e horário da apresentação oral, para autores e avaliadores (sem nomes de autores na versão do avaliador). */
class ApresentacaoAgendadaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Trabalho $trabalho, public string $papel, public string $tipo = 'convite')
    {
    }

    public function envelope(): Envelope
    {
        $prefixo = match ($this->tipo) {
            'atualizacao' => 'Horário atualizado',
            'lembrete' => 'Lembrete: a apresentação começa em breve',
            default => 'Sala e horário da apresentação oral',
        };

        return new Envelope(subject: "{$prefixo} — {$this->trabalho->codigo}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.apresentacao-agendada', with: [
            'inicio' => $this->trabalho->apresentacao_inicio, 'fim' => $this->trabalho->apresentacao_fim, 'sala' => $this->trabalho->sala,
        ]);
    }
}
