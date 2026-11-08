<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Trabalhos avaliados antes da coluna existir: calcula nota e resultado da apresentação a partir das avaliações concluídas. */
return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('apresentacao_avaliacoes')->distinct()->pluck('trabalho_id');

        foreach ($ids as $id) {
            $av = DB::table('apresentacao_avaliacoes')->where('trabalho_id', $id)->get();
            $notas = $av->where('status', 'concluida')->pluck('nota_final');
            $pendente = $av->contains(fn ($a) => in_array($a->status, ['pendente', 'em_andamento'], true));
            $media = $notas->isEmpty() ? null : round($notas->avg(), 2);

            $minima = (float) (DB::table('trabalhos')
                ->join('trabalho_configs', 'trabalho_configs.evento_id', '=', 'trabalhos.evento_id')
                ->where('trabalhos.id', $id)->value('trabalho_configs.nota_minima_apresentacao') ?? 7);

            DB::table('trabalhos')->where('id', $id)->update([
                'nota_apresentacao' => $media,
                'resultado_apresentacao' => ($media === null || $pendente) ? null : ($media >= $minima ? 'aprovado' : 'reprovado'),
            ]);
        }
    }

    public function down(): void
    {
    }
};
