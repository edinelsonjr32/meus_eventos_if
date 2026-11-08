<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabalhos', function (Blueprint $table) {
            $table->dateTime('autorizacao_solicitada_em')->nullable()->after('autorizou_em');   // último pedido da gestão para autorizar a publicação nos Anais
        });
    }

    public function down(): void
    {
        Schema::table('trabalhos', fn (Blueprint $t) => $t->dropColumn('autorizacao_solicitada_em'));
    }
};
