<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Atividades acadêmicas (ex.: jogos) usam só o controle de frequência, sem certificado. Padrão: gera (comportamento anterior).
        Schema::table('atividades', fn (Blueprint $t) => $t->boolean('gera_certificado')->default(true)->after('carga_horaria'));

        // O tipo vira texto livre (jogo, gincana, exibição de filmes etc.): a coluna era ENUM no MySQL
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE atividades MODIFY tipo VARCHAR(80) NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::table('atividades', fn (Blueprint $t) => $t->dropColumn('gera_certificado'));
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::table('atividades')->whereNotIn('tipo', ['palestra', 'minicurso', 'oficina', 'mesa_redonda', 'outro'])->update(['tipo' => 'outro']);
            DB::statement("ALTER TABLE atividades MODIFY tipo ENUM('palestra','minicurso','oficina','mesa_redonda','outro') NOT NULL");
        }
    }
};
