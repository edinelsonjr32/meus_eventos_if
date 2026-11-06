<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tipos antigos (formato do arquivo) → categorias do formulário da Semana de Ciência e Tecnologia. */
    private const MAPA = [
        'resumo' => 'pesquisa', 'resumo_expandido' => 'pesquisa', 'artigo_completo' => 'pesquisa', 'poster' => 'pesquisa',
        'relato_experiencia' => 'relato_experiencia',
    ];

    private const CATEGORIAS = ['pesquisa', 'extensao', 'projeto_integrador', 'relato_experiencia'];

    public function up(): void
    {
        Schema::table('trabalho_configs', function (Blueprint $table) {
            $table->string('chamada_tema')->nullable();
            $table->text('chamada_intro')->nullable();
            $table->string('template_url', 500)->nullable();
            // Existentes mantêm o comportamento atual (exigem); novas chamadas definem pela tela de configuração
            $table->boolean('exige_resumo')->default(true);
            $table->boolean('exige_palavras_chave')->default(true);
            $table->boolean('exige_cpf_apresentador')->default(true);
        });

        // Autores adicionais só informam nome e instituição: CPF e e-mail passam a ser opcionais
        Schema::table('trabalho_autores', function (Blueprint $table) {
            $table->string('cpf', 14)->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('categoria_autor', 20)->nullable()->after('tipo_autor');   // docente | discente
        });

        DB::table('trabalho_autores')->where('vinculo', 'aluno')->update(['categoria_autor' => 'discente']);
        DB::table('trabalho_autores')->where('vinculo', 'servidor')->update(['categoria_autor' => 'docente']);

        foreach (self::MAPA as $antigo => $novo) {
            DB::table('trabalhos')->where('tipo_trabalho', $antigo)->update(['tipo_trabalho' => $novo]);
        }

        DB::table('trabalho_configs')->orderBy('id')->each(function ($c) {
            $atuais = json_decode($c->tipos_permitidos ?? 'null', true) ?: [];
            $novos = collect($atuais)->map(fn ($t) => self::MAPA[$t] ?? $t)->filter(fn ($t) => in_array($t, self::CATEGORIAS, true))->unique()->values()->all();

            DB::table('trabalho_configs')->where('id', $c->id)->update(['tipos_permitidos' => json_encode($novos ?: self::CATEGORIAS)]);
        });
    }

    public function down(): void
    {
        Schema::table('trabalho_autores', function (Blueprint $table) {
            $table->dropColumn('categoria_autor');
        });

        Schema::table('trabalho_configs', function (Blueprint $table) {
            $table->dropColumn(['chamada_tema', 'chamada_intro', 'template_url', 'exige_resumo', 'exige_palavras_chave', 'exige_cpf_apresentador']);
        });
        // Colunas cpf/email permanecem opcionais e o mapeamento de categorias não é revertido (perde-se o tipo antigo)
    }
};
