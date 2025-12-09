<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Alterar Frequência para suportar tipos de participação (Palestrante, Ouvinte, etc)
        Schema::table('frequencias', function (Blueprint $table) {
            // Default é 'ouvinte', mas pode mudar para gerar certificado diferente
            $table->string('tipo_participacao')->default('ouvinte')->after('atividade_id');
            // Ex: ouvinte, palestrante, mediador, monitor
        });

        // 2. Tabela de Equipe/Comissão (Vínculo do Participante com o Evento Geral)
        Schema::create('equipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->onDelete('cascade');
            $table->foreignId('participante_id')->constrained('participantes')->onDelete('cascade');

            // Qual a função dele? (Coordenador, Voluntário, Apoio, Secretaria)
            $table->string('funcao');

            // Carga horária específica de trabalho (para certificado de comissão)
            $table->integer('carga_horaria_trabalho')->default(20);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipes');
        Schema::table('frequencias', function (Blueprint $table) {
            $table->dropColumn('tipo_participacao');
        });
    }
};
