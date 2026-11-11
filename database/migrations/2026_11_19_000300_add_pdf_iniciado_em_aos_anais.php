<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anais', function (Blueprint $table) {
            $table->dateTime('pdf_iniciado_em')->nullable()->after('pdf_status');
        });

        // Gerações que já estavam "presas" antes desta versão: libera para tentar de novo
        DB::table('anais')->where('pdf_status', 'gerando')->update([
            'pdf_status' => 'erro', 'pdf_erro' => 'A geração anterior não terminou (ficou presa). Gere o PDF novamente.',
        ]);
    }

    public function down(): void
    {
        Schema::table('anais', function (Blueprint $table) {
            $table->dropColumn('pdf_iniciado_em');
        });
    }
};
