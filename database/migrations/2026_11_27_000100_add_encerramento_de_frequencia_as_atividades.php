<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atividades', function (Blueprint $t) {
            $t->dateTime('frequencia_encerrada_em')->nullable()->after('token_frequencia');   // encerrada à mão pela organização
            $t->dateTime('frequencia_fecha_em')->nullable()->after('frequencia_encerrada_em'); // encerra sozinha neste horário (opcional)
        });
    }

    public function down(): void
    {
        Schema::table('atividades', fn (Blueprint $t) => $t->dropColumn(['frequencia_encerrada_em', 'frequencia_fecha_em']));
    }
};
