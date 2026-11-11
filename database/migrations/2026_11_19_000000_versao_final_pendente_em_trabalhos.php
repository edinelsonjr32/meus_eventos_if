<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Aprovado com ressalvas" deixa de devolver o trabalho para nova aprovação da gestão: o trabalho segue o fluxo
 * (apresentação, certificado) e o autor envia a versão corrigida no final. Enquanto não envia, fica "versão final pendente".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabalhos', function (Blueprint $table) {
            $table->boolean('versao_final_pendente')->default(false)->after('prazo_correcao');
            $table->timestamp('versao_final_enviada_em')->nullable()->after('versao_final_pendente');
        });

        // Trabalhos que estavam "aguardando correção" passam a seguir o novo fluxo
        DB::table('trabalhos')->where('status', 'aguardando_correcao')
            ->update(['status' => 'aprovado', 'versao_final_pendente' => true]);
    }

    public function down(): void
    {
        Schema::table('trabalhos', function (Blueprint $table) {
            $table->dropColumn(['versao_final_pendente', 'versao_final_enviada_em']);
        });
    }
};
