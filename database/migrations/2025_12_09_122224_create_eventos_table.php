<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('slug')->unique(); // Para URLs bonitas
            $table->text('descricao')->nullable();

            $table->dateTime('data_inicio');
            $table->dateTime('data_fim');
            $table->string('local');

            // Configurações visuais e do certificado (JSON é perfeito para isso)
            // Ex: { "cor_fundo": "#000", "texto_certificado": "Certificamos que..." }
            $table->json('configuracoes')->nullable();

            $table->foreignId('criado_por')->constrained('users'); // Quem criou
            $table->timestamps();
            $table->softDeletes(); // Permite "lixeira" (recuperar evento deletado)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
