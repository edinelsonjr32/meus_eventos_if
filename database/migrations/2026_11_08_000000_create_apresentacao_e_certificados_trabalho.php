<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabalho_configs', function (Blueprint $table) {
            $table->boolean('exige_apresentacao_para_certificado')->default(true)->after('autor_ve_pareceres');
            $table->text('cert_trab_cabecalho')->nullable();
            $table->longText('cert_trab_corpo')->nullable();
            $table->text('cert_trab_rodape')->nullable();
        });

        Schema::table('trabalhos', function (Blueprint $table) {
            // Sessão em que o trabalho é apresentado (atividade do evento)
            $table->foreignId('atividade_id')->nullable()->after('area_tematica_id')->constrained('atividades')->nullOnDelete();
            $table->boolean('apresentado')->default(false)->after('prazo_correcao');
            $table->dateTime('apresentado_em')->nullable()->after('apresentado');
            $table->string('apresentacao_origem', 20)->nullable()->after('apresentado_em'); // manual | frequencia
        });

        Schema::create('certificados_trabalho', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabalho_id')->constrained('trabalhos')->onDelete('cascade');
            $table->foreignId('trabalho_autor_id')->constrained('trabalho_autores')->onDelete('cascade');
            $table->string('hash_validacao', 40)->unique();
            $table->string('tipo', 30);                       // autor | coautor | orientador | coorientador
            $table->boolean('apresentador')->default(false);
            $table->dateTime('emitido_em');
            $table->foreignId('emitido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('downloads')->default(0);
            $table->dateTime('revogado_em')->nullable();
            $table->string('revogado_motivo')->nullable();
            $table->timestamps();

            $table->unique('trabalho_autor_id'); // um certificado por autor
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_trabalho');

        Schema::table('trabalhos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('atividade_id');
            $table->dropColumn(['apresentado', 'apresentado_em', 'apresentacao_origem']);
        });

        Schema::table('trabalho_configs', function (Blueprint $table) {
            $table->dropColumn(['exige_apresentacao_para_certificado', 'cert_trab_cabecalho', 'cert_trab_corpo', 'cert_trab_rodape']);
        });
    }
};
