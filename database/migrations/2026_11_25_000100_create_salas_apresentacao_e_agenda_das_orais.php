<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Salas virtuais (link de reunião) do evento: o mesmo link atende várias apresentações em horários diferentes
        Schema::create('salas_apresentacao', function (Blueprint $t) {
            $t->id();
            $t->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $t->string('nome', 120);
            $t->string('nome_normalizado', 120);
            $t->string('link', 500);
            $t->text('instrucoes')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['evento_id', 'nome_normalizado']);
        });

        // Agenda da apresentação oral de cada trabalho
        Schema::table('trabalhos', function (Blueprint $t) {
            $t->foreignId('sala_apresentacao_id')->nullable()->after('atividade_id')->constrained('salas_apresentacao')->nullOnDelete();
            $t->dateTime('apresentacao_inicio')->nullable()->after('sala_apresentacao_id');
            $t->dateTime('apresentacao_fim')->nullable()->after('apresentacao_inicio');
            $t->dateTime('agenda_notificada_em')->nullable()->after('apresentacao_fim');   // último aviso de sala/horário enviado
            $t->dateTime('agenda_alterada_em')->nullable()->after('agenda_notificada_em'); // mudou depois do último aviso
            $t->dateTime('lembrete_enviado_em')->nullable()->after('agenda_alterada_em');
            $t->index(['sala_apresentacao_id', 'apresentacao_inicio'], 'trabalhos_sala_horario');
        });
    }

    public function down(): void
    {
        Schema::table('trabalhos', function (Blueprint $t) {
            $t->dropIndex('trabalhos_sala_horario');
            $t->dropConstrainedForeignId('sala_apresentacao_id');
            $t->dropColumn(['apresentacao_inicio', 'apresentacao_fim', 'agenda_notificada_em', 'agenda_alterada_em', 'lembrete_enviado_em']);
        });
        Schema::dropIfExists('salas_apresentacao');
    }
};
