<?php

namespace App\Console\Commands;

use App\Mail\AvaliacaoLembreteMail;
use App\Models\TrabalhoAvaliacao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class LembrarAvaliadores extends Command
{
    protected $signature = 'trabalhos:lembrar-avaliadores {--dias=2 : Antecedência (dias) para avisar antes do prazo}';

    protected $description = 'Envia lembrete (no máx. 1 por dia) a avaliadores com avaliações vencendo ou atrasadas';

    public function handle(): int
    {
        $limite = now()->addDays((int) $this->option('dias'))->endOfDay();

        $avaliacoes = TrabalhoAvaliacao::with(['trabalho.evento', 'avaliador'])
            ->whereIn('status', ['pendente', 'em_andamento'])
            ->whereNotNull('prazo')
            ->where('prazo', '<=', $limite)
            ->where(fn ($q) => $q->whereNull('lembrete_enviado_em')->orWhere('lembrete_enviado_em', '<', now()->startOfDay()))
            ->whereHas('trabalho', fn ($t) => $t->where('status', 'em_avaliacao'))
            ->get()
            // somente a rodada vigente de cada trabalho
            ->filter(fn ($a) => $a->rodada === $a->trabalho->rodadaAtual());

        foreach ($avaliacoes as $avaliacao) {
            Mail::to($avaliacao->avaliador->email)->queue(new AvaliacaoLembreteMail($avaliacao));
            $avaliacao->forceFill(['lembrete_enviado_em' => now()])->save();
        }

        $this->info("{$avaliacoes->count()} lembrete(s) enviado(s).");

        return self::SUCCESS;
    }
}
