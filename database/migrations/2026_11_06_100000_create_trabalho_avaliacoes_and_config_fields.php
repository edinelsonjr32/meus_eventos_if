<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Atribuições de avaliador (na Fase 3 recebem nota, parecer e recomendação)
        Schema::create('trabalho_avaliacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_id')->constrained('trabalhos')->onDelete('cascade');
            $table->foreignId('avaliador_id')->constrained('users')->onDelete('cascade');
            $table->unsignedTinyInteger('rodada')->default(1);
            $table->string('status', 20)->default('pendente');   // pendente | em_andamento | concluida | recusada
            $table->foreignId('atribuido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('prazo')->nullable();
            $table->decimal('nota_final', 5, 2)->nullable();
            $table->text('parecer')->nullable();
            $table->text('parecer_confidencial')->nullable();
            $table->string('recomendacao', 30)->nullable();      // aprovar | aprovar_com_ressalvas | reprovar
            $table->dateTime('concluida_em')->nullable();
            $table->timestamps();

            $table->unique(['trabalho_id', 'avaliador_id', 'rodada']);
            $table->index(['avaliador_id', 'status']);
        });

        Schema::table('trabalho_configs', function (Blueprint $table) {
            $table->dateTime('avaliacao_fim')->nullable()->after('submissao_fim');
            $table->unsignedTinyInteger('avaliadores_por_trabalho')->default(2)->after('max_trabalhos_por_usuario');
        });
    }

    public function down(): void
    {
        Schema::table('trabalho_configs', function (Blueprint $table) {
            $table->dropColumn(['avaliacao_fim', 'avaliadores_por_trabalho']);
        });
        Schema::dropIfExists('trabalho_avaliacoes');
    }
};
