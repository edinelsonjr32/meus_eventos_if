<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Atribuição de cada pessoa indicada na proposta: também ministra ou apenas ajuda
        Schema::table('proposta_apoios', fn (Blueprint $t) => $t->string('funcao', 20)->default('ajudante')->after('cpf'));
    }

    public function down(): void
    {
        Schema::table('proposta_apoios', fn (Blueprint $t) => $t->dropColumn('funcao'));
    }
};
