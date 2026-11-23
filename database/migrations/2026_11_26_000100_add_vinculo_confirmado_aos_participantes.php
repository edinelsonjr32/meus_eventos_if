<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quando a própria pessoa escolheu/confirmou o tipo de cadastro (aluno, servidor, externo). Nulo em cadastros antigos:
        // quem consta como externo sem confirmação é perguntado uma única vez na próxima frequência/inscrição.
        Schema::table('participantes', fn (Blueprint $t) => $t->dateTime('vinculo_confirmado_em')->nullable()->after('turma_id'));
    }

    public function down(): void
    {
        Schema::table('participantes', fn (Blueprint $t) => $t->dropColumn('vinculo_confirmado_em'));
    }
};
