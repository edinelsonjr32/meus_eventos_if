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

    public int $timeout = 900;

    public function __construct(public Anais $anais)
    {
    }

    public function handle(AnaisPdfService $servico): void
    {
        $servico->gerar($this->anais);
    }

    public function failed(\Throwable $e): void
    {
        $this->anais->update(['pdf_status' => 'erro', 'pdf_erro' => Str::limit($e->getMessage(), 500)]);
    }
}
