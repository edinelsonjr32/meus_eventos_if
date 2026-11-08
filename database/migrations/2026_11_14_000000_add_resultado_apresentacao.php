<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabalhos', function (Blueprint $table) {
            // Resultado da apresentação (aprovado | reprovado), calculado quando todas as avaliações dela terminam
            $table->string('resultado_apresentacao', 12)->nullable()->after('nota_apresentacao');
        });

        Schema::table('trabalho_configs', function (Blueprint $table) {
            $table->decimal('nota_minima_apresentacao', 4, 2)->default(7);
        });
    }

    public function down(): void
    {
        Schema::table('trabalho_configs', fn (Blueprint $t) => $t->dropColumn('nota_minima_apresentacao'));
        Schema::table('trabalhos', fn (Blueprint $t) => $t->dropColumn('resultado_apresentacao'));
    }
};
