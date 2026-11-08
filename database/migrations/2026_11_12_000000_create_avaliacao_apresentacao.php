<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Critérios próprios da apresentação (independentes dos critérios do trabalho escrito)
        Schema::create('criterios_apresentacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->onDelete('cascade');
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->decimal('peso', 5, 2)->default(1);
            $table->decimal('nota_max', 5, 2)->default(10);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('apresentacao_avaliacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_id')->constrained('trabalhos')->onDelete('cascade');
            $table->foreignId('avaliador_id')->constrained('users')->onDelete('restrict');
            $table->string('status', 20)->default('pendente');   // pendente | em_andamento | concluida | recusada
            $table->foreignId('atribuido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('prazo')->nullable();
            $table->decimal('nota_final', 5, 2)->nullable();
            $table->text('parecer')->nullable();
            $table->text('parecer_confidencial')->nullable();
            $table->dateTime('concluida_em')->nullable();
            $table->timestamps();

            $table->unique(['trabalho_id', 'avaliador_id']);
        });

        Schema::create('apresentacao_avaliacao_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('apresentacao_avaliacoes')->onDelete('cascade');
            $table->foreignId('criterio_id')->constrained('criterios_apresentacao')->onDelete('restrict');
            $table->decimal('nota', 5, 2);
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->unique(['avaliacao_id', 'criterio_id']);
        });

        Schema::table('trabalhos', function (Blueprint $table) {
            $table->decimal('nota_apresentacao', 5, 2)->nullable()->after('nota_final');
        });

        Schema::table('trabalho_configs', function (Blueprint $table) {
            $table->boolean('avaliacao_apresentacao_ativa')->default(false);
            $table->unsignedTinyInteger('peso_apresentacao')->default(30);   // % da nota geral
            $table->boolean('autor_ve_nota_apresentacao')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('trabalho_configs', fn (Blueprint $t) => $t->dropColumn(['avaliacao_apresentacao_ativa', 'peso_apresentacao', 'autor_ve_nota_apresentacao']));
        Schema::table('trabalhos', fn (Blueprint $t) => $t->dropColumn('nota_apresentacao'));
        Schema::dropIfExists('apresentacao_avaliacao_notas');
        Schema::dropIfExists('apresentacao_avaliacoes');
        Schema::dropIfExists('criterios_apresentacao');
    }
};
