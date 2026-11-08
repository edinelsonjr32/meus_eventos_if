<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anais', function (Blueprint $table) {
            // Os dados de publicação (ISBN/ISSN/DOI, editora, local, ano, citação e PDF compilado) só existem depois do
            // evento: os Anais podem ficar visíveis antes, com esses dados ocultos até a gestão liberar.
            $table->boolean('dados_publicacao_visiveis')->default(false)->after('status');
            $table->string('local_publicacao')->nullable()->change();
            $table->string('editora')->nullable()->change();
        });

        // Anais já publicados continuam exibindo tudo, como antes
        DB::table('anais')->where('status', 'publicado')->update(['dados_publicacao_visiveis' => true]);
    }

    public function down(): void
    {
        Schema::table('anais', fn (Blueprint $t) => $t->dropColumn('dados_publicacao_visiveis'));
    }
};
