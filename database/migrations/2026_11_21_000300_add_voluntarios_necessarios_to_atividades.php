<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quantos voluntários a atividade precisa (vira a capacidade da demanda automática). Nulo = sem limite.
        Schema::table('atividades', fn (Blueprint $t) => $t->unsignedSmallInteger('voluntarios_necessarios')->nullable()->default(2)->after('vagas'));
    }

    public function down(): void
    {
        Schema::table('atividades', fn (Blueprint $t) => $t->dropColumn('voluntarios_necessarios'));
    }
};
