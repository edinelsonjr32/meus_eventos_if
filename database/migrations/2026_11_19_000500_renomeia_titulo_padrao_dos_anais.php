<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O termo "Anais" passou a ser "Trabalhos Publicados". Títulos criados pelo padrão antigo ("Anais do/da ...")
 * são atualizados; títulos escritos à mão de outra forma ficam como estão.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('anais')
            ->where(fn ($q) => $q->where('titulo', 'like', 'Anais do %')->orWhere('titulo', 'like', 'Anais da %')->orWhere('titulo', 'like', 'Anais de %'))
            ->orderBy('id')->each(function ($linha) {
                DB::table('anais')->where('id', $linha->id)->update(['titulo' => 'Trabalhos Publicados ' . mb_substr($linha->titulo, mb_strlen('Anais '))]);
            });
    }

    public function down(): void
    {
        // Sem volta: o título é conteúdo editável pela gestão
    }
};
