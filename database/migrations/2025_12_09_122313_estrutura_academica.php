<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Cursos
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->string('nome'); // Ex: Técnico em Informática
            $table->timestamps();
        });

        // 2. Turmas (Agora definida apenas pelo ANO e CURSO)
        Schema::create('turmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');

            // Removemos 'nome'. Agora a turma é identificada apenas pelo ano.
            $table->string('ano'); // Ex: "2024", "2025"

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turmas');
        Schema::dropIfExists('cursos');
    }
};
