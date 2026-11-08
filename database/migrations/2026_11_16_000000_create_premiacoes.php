<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Premiação: 1º, 2º e 3º lugar por categoria (tipo de trabalho), pela nota geral (texto + apresentação)
        Schema::create('premiacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->onDelete('cascade');
            $table->foreignId('trabalho_id')->constrained('trabalhos')->onDelete('cascade');
            $table->string('tipo_trabalho', 40);                 // categoria
            $table->unsignedTinyInteger('colocacao');            // 1, 2 ou 3
            $table->decimal('nota_geral', 5, 2)->nullable();     // fotografia no momento da apuração
            $table->decimal('nota_texto', 5, 2)->nullable();
            $table->decimal('nota_apresentacao', 5, 2)->nullable();
            $table->foreignId('apurada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('trabalho_id');                                   // um trabalho ocupa um só lugar
            $table->unique(['evento_id', 'tipo_trabalho', 'colocacao']);     // um trabalho por lugar em cada categoria
        });

        // Certificados de premiação (um por autor): ficam separados dos certificados de participação
        Schema::create('certificados_premiacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('premiacao_id')->constrained('premiacoes')->onDelete('cascade');
            $table->foreignId('trabalho_autor_id')->constrained('trabalho_autores')->onDelete('cascade');
            $table->string('hash_validacao', 40)->unique();
            $table->dateTime('emitido_em');
            $table->foreignId('emitido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('downloads')->default(0);
            $table->dateTime('revogado_em')->nullable();
            $table->string('revogado_motivo')->nullable();
            $table->timestamps();

            $table->unique(['premiacao_id', 'trabalho_autor_id']);
        });

        Schema::table('trabalho_configs', function (Blueprint $table) {
            // Enquanto nulo, a premiação é só da gestão (o autor não vê o certificado em seu perfil)
            $table->dateTime('premiacao_divulgada_em')->nullable();
            $table->text('cert_premio_cabecalho')->nullable();
            $table->longText('cert_premio_corpo')->nullable();
            $table->text('cert_premio_rodape')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trabalho_configs', fn (Blueprint $t) => $t->dropColumn(['premiacao_divulgada_em', 'cert_premio_cabecalho', 'cert_premio_corpo', 'cert_premio_rodape']));
        Schema::dropIfExists('certificados_premiacao');
        Schema::dropIfExists('premiacoes');
    }
};
