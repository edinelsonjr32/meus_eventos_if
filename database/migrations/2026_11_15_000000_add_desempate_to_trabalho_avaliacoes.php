<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabalho_avaliacoes', function (Blueprint $table) {
            $table->boolean('desempate')->default(false)->after('rodada');   // avaliador adicionado por divergência entre os demais
        });
    }

    public function down(): void
    {
        Schema::table('trabalho_avaliacoes', fn (Blueprint $t) => $t->dropColumn('desempate'));
    }
};
