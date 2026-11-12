<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------- Propostas de minicurso/oficina ----------
        Schema::create('chamadas_propostas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $t->string('titulo');
            $t->longText('descricao')->nullable();
            $t->json('tipos_aceitos')->nullable();               // ['minicurso','oficina']
            $t->dateTime('inicio')->nullable();
            $t->dateTime('fim')->nullable();
            $t->boolean('ativa')->default(true);
            $t->unsignedTinyInteger('max_por_usuario')->default(3);
            $t->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('propostas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chamada_id')->constrained('chamadas_propostas')->cascadeOnDelete();
            $t->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('participante_id')->nullable()->constrained('participantes')->nullOnDelete();
            $t->string('tipo', 20);
            $t->string('titulo');
            $t->text('resumo');
            $t->text('objetivos')->nullable();
            $t->string('publico_alvo')->nullable();
            $t->text('conteudo_programatico')->nullable();
            $t->text('metodologia')->nullable();
            $t->unsignedSmallInteger('carga_horaria_prevista');
            $t->unsignedSmallInteger('vagas_sugeridas');
            $t->boolean('requer_laboratorio')->default(false);
            $t->string('espaco_solicitado')->nullable();
            $t->text('equipamentos_solicitados')->nullable();
            $t->text('observacoes')->nullable();
            $t->string('status', 20)->default('enviada');
            $t->text('parecer')->nullable();                     // mensagem da organização ao proponente
            $t->foreignId('decidido_por')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('decidido_em')->nullable();
            $t->unsignedBigInteger('atividade_id')->nullable();   // programação criada na aprovação
            $t->timestamps();
            $t->index(['evento_id', 'status']);
        });

        Schema::create('proposta_apoios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('proposta_id')->constrained('propostas')->cascadeOnDelete();
            $t->string('nome');
            $t->string('email')->nullable();
            $t->string('cpf', 14)->nullable();
            $t->foreignId('participante_id')->nullable()->constrained('participantes')->nullOnDelete();
            $t->dateTime('adicionado_ao_evento_em')->nullable();
            $t->timestamps();
        });

        // ---------- Voluntários ----------
        Schema::create('chamadas_voluntarios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $t->string('titulo');
            $t->longText('descricao')->nullable();
            $t->text('requisitos')->nullable();
            $t->unsignedSmallInteger('vagas')->nullable();
            $t->dateTime('inicio')->nullable();
            $t->dateTime('fim')->nullable();
            $t->boolean('ativa')->default(true);
            $t->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('voluntarios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chamada_id')->constrained('chamadas_voluntarios')->cascadeOnDelete();
            $t->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('participante_id')->nullable()->constrained('participantes')->nullOnDelete();
            $t->text('motivacao')->nullable();
            $t->text('disponibilidade')->nullable();
            $t->text('habilidades')->nullable();
            $t->string('telefone', 30)->nullable();
            $t->string('status', 20)->default('inscrito');
            $t->text('parecer')->nullable();
            $t->foreignId('decidido_por')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('decidido_em')->nullable();
            $t->timestamps();
            $t->unique(['chamada_id', 'user_id']);
            $t->index(['evento_id', 'status']);
        });

        Schema::create('demandas_voluntarios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $t->foreignId('atividade_id')->nullable()->constrained('atividades')->nullOnDelete();
            $t->string('titulo');
            $t->text('descricao')->nullable();
            $t->dateTime('data_inicio')->nullable();
            $t->dateTime('data_fim')->nullable();
            $t->string('local')->nullable();
            $t->unsignedSmallInteger('vagas')->nullable();
            $t->decimal('carga_horaria', 5, 1)->default(0);
            $t->string('responsavel_nome')->nullable();          // servidor responsável: quem o voluntário deve procurar
            $t->string('responsavel_contato')->nullable();
            $t->timestamps();
        });

        Schema::create('voluntario_demanda', function (Blueprint $t) {
            $t->id();
            $t->foreignId('voluntario_id')->constrained('voluntarios')->cascadeOnDelete();
            $t->foreignId('demanda_id')->constrained('demandas_voluntarios')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['voluntario_id', 'demanda_id']);
        });

        // ---------- Atividade: dados vindos da proposta aprovada ----------
        Schema::table('atividades', function (Blueprint $t) {
            $t->text('descricao')->nullable()->after('titulo');
            $t->string('espaco')->nullable()->after('local');            // espaço específico (ex.: laboratório)
            $t->text('equipamentos')->nullable()->after('espaco');
            $t->text('observacoes_organizacao')->nullable()->after('equipamentos');
            $t->unsignedBigInteger('proposta_id')->nullable()->after('token_frequencia');
        });

        // ---------- Equipe: uma pessoa pode ter mais de uma função no evento (ex.: ministrante e voluntária) ----------
        Schema::table('equipes', function (Blueprint $t) {
            $t->text('detalhe')->nullable()->after('carga_horaria_trabalho');   // ex.: atividades ministradas ou demandas atendidas
            $t->unique(['evento_id', 'participante_id', 'funcao'], 'equipes_evento_participante_funcao_unique');
        });
        Schema::table('equipes', fn (Blueprint $t) => $t->dropUnique('equipes_evento_participante_unique'));
    }

    public function down(): void
    {
        Schema::table('equipes', function (Blueprint $t) {
            $t->unique(['evento_id', 'participante_id'], 'equipes_evento_participante_unique');
        });
        Schema::table('equipes', function (Blueprint $t) {
            $t->dropUnique('equipes_evento_participante_funcao_unique');
            $t->dropColumn('detalhe');
        });
        Schema::table('atividades', fn (Blueprint $t) => $t->dropColumn(['descricao', 'espaco', 'equipamentos', 'observacoes_organizacao', 'proposta_id']));

        foreach (['voluntario_demanda', 'demandas_voluntarios', 'voluntarios', 'chamadas_voluntarios', 'proposta_apoios', 'propostas', 'chamadas_propostas'] as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};
