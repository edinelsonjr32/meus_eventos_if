<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frequencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participante_id')->constrained('participantes');
            $table->foreignId('atividade_id')->constrained('atividades');

            $table->dateTime('data_registro');

            // Controle de Emissão
            $table->boolean('certificado_emitido')->default(false);
            $table->string('hash_validacao')->unique(); // Código impresso no PDF

            $table->timestamps();

            // Regra de Ouro: Um participante só pode ter UMA presença por atividade
            $table->unique(['participante_id', 'atividade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frequencias');
    }
};
