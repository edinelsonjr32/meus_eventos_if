<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscricoes', function (Blueprint $table) {
            $table->id();
            // Liga o Participante ao Evento Geral
            $table->foreignId('evento_id')->constrained('eventos')->onDelete('cascade');
            $table->foreignId('participante_id')->constrained('participantes')->onDelete('cascade');

            $table->timestamp('data_inscricao');

            // Evita inscrição duplicada no mesmo evento
            $table->unique(['evento_id', 'participante_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscricoes');
    }
};
