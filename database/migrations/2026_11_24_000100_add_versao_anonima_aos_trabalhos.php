<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PDF "sem identificação" para a avaliação cega, guardado ao lado do PDF principal (que pode ter os autores).
        Schema::table('trabalho_arquivos', fn (Blueprint $t) => $t->boolean('anonimo')->default(false)->after('tipo'));
        // Modelo de trabalho sem identificação dos autores (a chamada pode ter um para cada versão).
        Schema::table('trabalho_configs', fn (Blueprint $t) => $t->string('template_anonimo_url', 500)->nullable()->after('template_url'));
    }

    public function down(): void
    {
        Schema::table('trabalho_arquivos', fn (Blueprint $t) => $t->dropColumn('anonimo'));
        Schema::table('trabalho_configs', fn (Blueprint $t) => $t->dropColumn('template_anonimo_url'));
    }
};
