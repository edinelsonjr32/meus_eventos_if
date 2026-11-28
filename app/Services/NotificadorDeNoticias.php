<?php

namespace App\Services;

use App\Mail\NoticiaEventoMail;
use App\Models\Evento;
use App\Models\Inscricao;
use App\Models\NoticiaEvento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/** Avisa por e-mail os inscritos no evento sobre uma notícia, uma única vez, quando ela entra no ar. */
class NotificadorDeNoticias
{
    /** Intervalo entre os e-mails (s): hospedagem compartilhada limita os envios por hora (aqui, até 360/h). */
    public const INTERVALO = 10;

    /** @return int quantos e-mails foram colocados na fila (0 se não é o caso de avisar) */
    public function notificar(NoticiaEvento $noticia): int
    {
        if (!$noticia->notificar_inscritos || $noticia->notificado_em || $noticia->situacao() !== 'no_ar' || $noticia->evento === null) {
            return 0;
        }

        // Reserva o envio antes de enfileirar: salvar duas vezes ou o agendador junto não duplicam os e-mails
        $reservou = NoticiaEvento::whereKey($noticia->id)->whereNull('notificado_em')->update(['notificado_em' => now()]);
        if (!$reservou) {
            return 0;
        }

        $emails = $this->destinatarios($noticia->evento);
        foreach ($emails->values() as $i => $email) {
            Mail::to($email)->later(now()->addSeconds($i * self::INTERVALO), new NoticiaEventoMail($noticia));
        }

        NoticiaEvento::whereKey($noticia->id)->update(['notificados_total' => $emails->count()]);
        $noticia->refresh();

        return $emails->count();
    }

    /** Intervalo mínimo (s) entre dois avisos da mesma notícia. */
    public const ESPERA_REENVIO = 900;

    /** Quando um novo aviso passa a ser permitido (null = já pode). Espera a fila do envio anterior esvaziar. */
    public function reenvioLiberadoEm(NoticiaEvento $noticia): ?\Carbon\CarbonInterface
    {
        if (!$noticia->notificado_em || !$noticia->notificados_total) {
            return null;
        }
        $libera = $noticia->notificado_em->copy()->addSeconds(max(self::ESPERA_REENVIO, $noticia->notificados_total * self::INTERVALO));

        return $libera->isFuture() ? $libera : null;
    }

    /**
     * Envia o aviso de novo (ou pela primeira vez) a pedido da organização.
     *
     * @return int quantos e-mails foram colocados na fila
     * @throws \DomainException
     */
    public function reenviar(NoticiaEvento $noticia): int
    {
        if ($noticia->situacao() !== 'no_ar') {
            throw new \DomainException('Só dá para avisar sobre notícia que já está no ar (visível no site).');
        }
        if ($libera = $this->reenvioLiberadoEm($noticia)) {
            throw new \DomainException('O último aviso ainda está sendo entregue. Um novo envio fica liberado em ' . $libera->format('d/m/Y \à\s H:i') . '.');
        }

        // Libera o envio somente se ninguém mexeu nele nesse meio tempo (evita dois reenvios simultâneos)
        $anterior = $noticia->notificado_em;
        $liberou = NoticiaEvento::whereKey($noticia->id)
            ->when($anterior, fn ($q) => $q->where('notificado_em', $anterior), fn ($q) => $q->whereNull('notificado_em'))
            ->update(['notificado_em' => null, 'notificar_inscritos' => true]);
        if (!$liberou && $anterior) {
            throw new \DomainException('Este aviso já está sendo reenviado.');
        }

        return $this->notificar($noticia->refresh());
    }

    /** E-mails (sem repetição) de quem está inscrito no evento. */
    public function destinatarios(Evento $evento): Collection
    {
        return Inscricao::where('evento_id', $evento->id)->with('participante.user')->get()
            ->map(fn (Inscricao $i) => mb_strtolower(trim((string) ($i->participante?->user?->email ?: $i->participante?->email))))
            ->filter(fn (string $e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values();
    }
}
