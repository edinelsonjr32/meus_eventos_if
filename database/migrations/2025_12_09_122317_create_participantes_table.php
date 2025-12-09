<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participantes', function (Blueprint $table) {
            $table->id();
            $table->string('nome_completo');
            $table->string('cpf', 14)->unique(); // Formato 000.000.000-00
            $table->string('email');
            // Distinção importante para relatórios
            $table->enum('tipo_vinculo', ['aluno', 'servidor', 'externo']);
            // Dados Acadêmicos (Opcionais, pois externo não tem)
            $table->string('matricula')->nullable();
            $table->foreignId('turma_id')->nullable()->constrained('turmas');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participantes');
    }
};
