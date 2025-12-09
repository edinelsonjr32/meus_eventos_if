<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->string('caminho_fundo')->nullable(); // Imagem de Background
            $table->string('caminho_brasao')->nullable(); // Brasão personalizado
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['caminho_fundo', 'caminho_brasao']);
        });
    }
};
