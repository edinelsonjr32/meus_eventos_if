<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Presença de ministrantes e ajudantes lançada pela organização; é o que libera o certificado de cada um
        Schema::create('atividade_equipe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atividade_id')->constrained('atividades')->cascadeOnDelete();
            $table->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $table->string('funcao', 20);   // ministrante | ajudante
            $table->dateTime('presenca_em');
            $table->foreignId('presenca_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('certificado_emitido_em')->nullable();
            $table->string('hash_validacao', 40)->unique();
            $table->timestamps();
            $table->unique(['atividade_id', 'participante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividade_equipe');
    }
};
