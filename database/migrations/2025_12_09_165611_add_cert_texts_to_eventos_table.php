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
            $table->text('cert_cabecalho')->nullable(); // Texto do topo (MEC, IFPA...)
            $table->longText('cert_corpo')->nullable(); // O texto principal com as variáveis
            $table->text('cert_rodape')->nullable();    // Texto de validação extra
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['cert_cabecalho', 'cert_corpo', 'cert_rodape']);
        });
    }
};
