<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove vínculos duplicados (mesma pessoa duas vezes na equipe do mesmo evento), mantendo o mais antigo
        $manter = DB::table('equipes')->selectRaw('MIN(id) as id')->groupBy('evento_id', 'participante_id')->pluck('id');
        DB::table('equipes')->whereNotIn('id', $manter)->delete();

        Schema::table('equipes', function (Blueprint $table) {
            $table->unique(['evento_id', 'participante_id'], 'equipes_evento_participante_unique');
            $table->string('hash_validacao', 40)->nullable()->unique()->after('carga_horaria_trabalho');
            $table->dateTime('certificado_emitido_em')->nullable()->after('hash_validacao');
            $table->foreignId('certificado_emitido_por')->nullable()->after('certificado_emitido_em')->constrained('users')->nullOnDelete();
        });

        Schema::table('eventos', function (Blueprint $table) {
            $table->text('cert_equipe_corpo')->nullable()->after('cert_rodape');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', fn (Blueprint $table) => $table->dropColumn('cert_equipe_corpo'));

        Schema::table('equipes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('certificado_emitido_por');
            $table->dropColumn(['certificado_emitido_em', 'hash_validacao']);
            $table->dropUnique('equipes_evento_participante_unique');
        });
    }
};
