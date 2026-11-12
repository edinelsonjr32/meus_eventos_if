<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Demanda criada sozinha a partir de uma atividade do evento (acompanha as mudanças da atividade)
        Schema::table('demandas_voluntarios', fn (Blueprint $t) => $t->boolean('automatica')->default(false)->after('responsavel_contato'));
    }

    public function down(): void
    {
        Schema::table('demandas_voluntarios', fn (Blueprint $t) => $t->dropColumn('automatica'));
    }
};
