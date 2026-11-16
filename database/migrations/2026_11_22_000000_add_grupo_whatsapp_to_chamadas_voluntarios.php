<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chamadas_voluntarios', function (Blueprint $t) {
            $t->string('grupo_whatsapp_url')->nullable()->after('requisitos');
            $t->timestamp('grupo_whatsapp_enviado_em')->nullable()->after('grupo_whatsapp_url');
        });
    }

    public function down(): void
    {
        Schema::table('chamadas_voluntarios', fn (Blueprint $t) => $t->dropColumn(['grupo_whatsapp_url', 'grupo_whatsapp_enviado_em']));
    }
};
