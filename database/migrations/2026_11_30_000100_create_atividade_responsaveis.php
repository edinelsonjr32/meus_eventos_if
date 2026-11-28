<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pessoas cadastradas (participante ou conta de usuário) responsáveis por uma atividade
        Schema::create('atividade_responsaveis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atividade_id')->constrained('atividades')->cascadeOnDelete();
            $table->foreignId('participante_id')->nullable()->constrained('participantes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('nome', 160);   // como foi escrito na programação ao salvar
            $table->timestamps();
            $table->unique(['atividade_id', 'participante_id']);
            $table->unique(['atividade_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividade_responsaveis');
    }
};
