<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Vincular User -> Participante
        Schema::table('participantes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->onDelete('set null');
        });

        // 2. Tabela de Inscrição em Atividades (Agenda do Aluno)
        Schema::create('atividade_inscricoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atividade_id')->constrained('atividades')->onDelete('cascade');
            $table->foreignId('participante_id')->constrained('participantes')->onDelete('cascade');
            $table->timestamps();

            // Um participante só pode se inscrever uma vez na mesma atividade
            $table->unique(['atividade_id', 'participante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividade_inscricoes');
        Schema::table('participantes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
