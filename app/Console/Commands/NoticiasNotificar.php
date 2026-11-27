<?php

namespace App\Console\Commands;

use App\Models\NoticiaEvento;
use App\Services\NotificadorDeNoticias;
use Illuminate\Console\Command;

class NoticiasNotificar extends Command
{
    protected $signature = 'noticias:notificar';

    protected $description = 'Avisa os inscritos sobre notícias agendadas que acabaram de entrar no ar';

    public function handle(NotificadorDeNoticias $notificador): int
    {
        $total = NoticiaEvento::visiveis()->where('notificar_inscritos', true)->whereNull('notificado_em')->with('evento')->get()
            ->sum(fn (NoticiaEvento $n) => $notificador->notificar($n));

        $this->info("{$total} aviso(s) de notícia colocado(s) na fila.");

        return self::SUCCESS;
    }
}
