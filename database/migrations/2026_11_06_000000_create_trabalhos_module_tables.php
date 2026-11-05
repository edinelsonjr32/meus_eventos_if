<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configuração da chamada de trabalhos (1 por evento)
        Schema::create('trabalho_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->unique()->constrained('eventos')->onDelete('cascade');
            $table->boolean('ativo')->default(false);            // chamada aberta ao público
            $table->dateTime('submissao_inicio')->nullable();
            $table->dateTime('submissao_fim')->nullable();
            $table->unsignedTinyInteger('max_autores')->default(8);
            $table->unsignedSmallInteger('max_arquivo_mb')->default(20);
            $table->unsignedSmallInteger('resumo_min_palavras')->default(100);
            $table->unsignedSmallInteger('resumo_max_palavras')->default(300);
            $table->unsignedTinyInteger('max_trabalhos_por_usuario')->nullable(); // null = sem limite
            $table->boolean('avaliacao_cega')->default(false);
            $table->json('tipos_permitidos')->nullable();
            $table->text('regras_texto')->nullable();
            $table->unsignedInteger('proximo_numero')->default(1); // contador atômico do protocolo
            $table->timestamps();
        });

        Schema::create('areas_tematicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->onDelete('cascade');
            $table->string('nome');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('trabalhos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->onDelete('cascade');
            $table->string('codigo')->nullable()->unique();     // protocolo, gerado ao submeter
            $table->foreignId('submetido_por')->constrained('users');
            $table->string('titulo');
            $table->text('resumo')->nullable();
            $table->text('abstract')->nullable();
            $table->json('palavras_chave')->nullable();
            $table->string('tipo_trabalho')->nullable();
            $table->foreignId('area_tematica_id')->nullable()->constrained('areas_tematicas')->nullOnDelete();
            $table->string('modalidade_apresentacao')->nullable(); // oral | poster
            $table->string('agencia_fomento')->nullable();
            $table->string('projeto_pesquisa')->nullable();
            $table->string('comite_etica')->nullable();
            $table->string('status', 30)->default('rascunho')->index();
            $table->boolean('autoriza_publicacao')->default(false);
            $table->dateTime('autorizou_em')->nullable();
            $table->dateTime('submetido_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['evento_id', 'status']);
        });

        Schema::create('trabalho_autores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_id')->constrained('trabalhos')->onDelete('cascade');
            $table->unsignedTinyInteger('ordem')->default(1);
            $table->foreignId('participante_id')->nullable()->constrained('participantes')->nullOnDelete();
            $table->string('nome_completo');
            $table->string('cpf', 14);
            $table->string('email');
            $table->string('telefone', 20)->nullable();
            $table->string('instituicao')->nullable();
            $table->string('vinculo', 20)->nullable();          // aluno | servidor | externo
            $table->string('titulacao')->nullable();
            $table->string('lattes')->nullable();
            $table->string('orcid')->nullable();
            $table->string('tipo_autor', 30);                   // autor_principal | coautor | orientador | coorientador
            $table->boolean('apresentador')->default(false);
            $table->boolean('correspondente')->default(false);
            $table->timestamps();

            $table->unique(['trabalho_id', 'cpf']);
            $table->index('cpf');
        });

        Schema::create('trabalho_arquivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_id')->constrained('trabalhos')->onDelete('cascade');
            $table->unsignedSmallInteger('versao')->default(1);
            $table->string('tipo', 20)->default('original');    // original | corrigido | anonimizado | final_anais
            $table->string('path');                              // no disco privado "trabalhos"
            $table->string('nome_original');
            $table->string('mime', 100);
            $table->unsignedBigInteger('tamanho');
            $table->string('sha256', 64);
            $table->foreignId('enviado_por')->constrained('users');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('trabalho_historico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_id')->constrained('trabalhos')->onDelete('cascade');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('de', 30)->nullable();
            $table->string('para', 30);
            $table->text('justificativa')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabalho_historico');
        Schema::dropIfExists('trabalho_arquivos');
        Schema::dropIfExists('trabalho_autores');
        Schema::dropIfExists('trabalhos');
        Schema::dropIfExists('areas_tematicas');
        Schema::dropIfExists('trabalho_configs');
    }
};
