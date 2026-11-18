<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propostas', function (Blueprint $t) {
            $t->text('pre_requisitos')->nullable()->after('metodologia');
            $t->text('materiais_participante')->nullable()->after('pre_requisitos');
            $t->text('minicurriculo')->nullable()->after('materiais_participante');         // apresentação curta do ministrante
            $t->string('contato_whatsapp', 20)->nullable()->after('minicurriculo');          // para a organização falar com o ministrante
            $t->json('disponibilidade_dias')->nullable()->after('contato_whatsapp');         // {"2026-11-12": ["manha", "tarde"]}
        });
    }

    public function down(): void
    {
        Schema::table('propostas', fn (Blueprint $t) => $t->dropColumn(['pre_requisitos', 'materiais_participante', 'minicurriculo', 'contato_whatsapp', 'disponibilidade_dias']));
    }
};
