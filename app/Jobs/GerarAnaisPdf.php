<?php

namespace App\Jobs;

use App\Models\Anais;
use App\Services\AnaisPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/** Compila o PDF dos Anais em segundo plano (pode levar minutos com muitos trabalhos). */
class GerarAnaisPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    // Menor que o --timeout do worker e que o retry_after da fila: ao estourar, o job FALHA (e o status vira "erro") em vez de ficar preso
    public int $timeout = 540;

    public bool $failOnTimeout = true;

    public function __construct(public Anais $anais)
    {
    }

    public function handle(AnaisPdfService $servico): void
    {
        $this->anais->refresh();

        // Já foi concluída/cancelada (ex.: gerada na hora pelo botão "Gerar agora"): nada a fazer
        if (!$this->anais->pdfGerando()) {
            return;
        }

        try {
            $servico->gerar($this->anais);
        } catch (\Throwable $e) {
            $this->failed($e);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->anais->update(['pdf_status' => 'erro', 'pdf_erro' => Str::limit($e->getMessage(), 500)]);
    }
}
