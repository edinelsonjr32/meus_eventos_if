<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Só atividades marcadas aparecem como sessão de apresentação de trabalhos (oficinas e palestras não)
        Schema::table('atividades', function (Blueprint $table) {
            $table->boolean('sessao_trabalhos')->default(false)->after('tipo');
        });

        // Já em uso como sessão, ou com nome que diz claramente que é de apresentação de trabalhos
        DB::table('atividades')->whereIn('id', DB::table('trabalhos')->whereNotNull('atividade_id')->select('atividade_id'))
            ->orWhere(function ($q) {
                foreach (['titulo', 'tipo'] as $campo) {
                    foreach (['%apresenta%trabalho%', 'sess%de trabalho%', 'sess%de p_ster%', 'sess%de comunica%'] as $padrao) {
                        $q->orWhereRaw("LOWER({$campo}) LIKE ?", [$padrao]);
                    }
                }
            })->update(['sessao_trabalhos' => true]);
    }

    public function down(): void
    {
        Schema::table('atividades', fn (Blueprint $table) => $table->dropColumn('sessao_trabalhos'));
    }
};
