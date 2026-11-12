<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Disponibilidade estruturada: {"2026-11-12": ["manha", "tarde"], ...}. O texto livre antigo fica só para inscrições já feitas.
        Schema::table('voluntarios', fn (Blueprint $t) => $t->json('disponibilidade_dias')->nullable()->after('disponibilidade'));
    }

    public function down(): void
    {
        Schema::table('voluntarios', fn (Blueprint $t) => $t->dropColumn('disponibilidade_dias'));
    }
};
