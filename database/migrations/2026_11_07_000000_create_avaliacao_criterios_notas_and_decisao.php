<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criterios_avaliacao', function (Blueprint $table) {
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

        Schema::create('trabalho_avaliacao_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avaliacao_id')->constrained('trabalho_avaliacoes')->onDelete('cascade');
            $table->foreignId('criterio_id')->constrained('criterios_avaliacao')->onDelete('restrict');
            $table->decimal('nota', 5, 2);
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->unique(['avaliacao_id', 'criterio_id']);
        });

        Schema::table('trabalho_configs', function (Blueprint $table) {
            $table->decimal('nota_minima_aprovacao', 4, 2)->default(7)->after('avaliadores_por_trabalho');
            $table->decimal('divergencia_maxima', 4, 2)->default(3)->after('nota_minima_aprovacao');
            $table->boolean('autor_ve_notas')->default(true)->after('divergencia_maxima');
            $table->boolean('autor_ve_pareceres')->default(true)->after('autor_ve_notas');
        });

        Schema::table('trabalhos', function (Blueprint $table) {
            $table->decimal('nota_final', 5, 2)->nullable()->after('status');
            $table->string('decisao', 30)->nullable()->after('nota_final');        // aprovado | aprovado_com_ressalvas | reprovado
            $table->text('decisao_justificativa')->nullable()->after('decisao');   // mensagem da gestão ao autor
            $table->dateTime('decisao_em')->nullable()->after('decisao_justificativa');
            $table->foreignId('decidido_por')->nullable()->after('decisao_em')->constrained('users')->nullOnDelete();
            $table->dateTime('prazo_correcao')->nullable()->after('decidido_por');
        });

        Schema::table('trabalho_avaliacoes', function (Blueprint $table) {
            $table->dateTime('lembrete_enviado_em')->nullable()->after('concluida_em');
        });
    }

    public function down(): void
    {
        Schema::table('trabalho_avaliacoes', fn (Blueprint $t) => $t->dropColumn('lembrete_enviado_em'));

        Schema::table('trabalhos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decidido_por');
            $table->dropColumn(['nota_final', 'decisao', 'decisao_justificativa', 'decisao_em', 'prazo_correcao']);
        });

        Schema::table('trabalho_configs', fn (Blueprint $t) => $t->dropColumn(['nota_minima_aprovacao', 'divergencia_maxima', 'autor_ve_notas', 'autor_ve_pareceres']));

        Schema::dropIfExists('trabalho_avaliacao_notas');
        Schema::dropIfExists('criterios_avaliacao');
    }
};
