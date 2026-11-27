<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('noticias_evento', function (Blueprint $t) {
            $t->boolean('notificar_inscritos')->default(false)->after('fixada');
            $t->dateTime('notificado_em')->nullable()->after('notificar_inscritos');
            $t->unsignedInteger('notificados_total')->nullable()->after('notificado_em');
        });
    }

    public function down(): void
    {
        Schema::table('noticias_evento', fn (Blueprint $t) => $t->dropColumn(['notificar_inscritos', 'notificado_em', 'notificados_total']));
    }
};
