<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Certificado de quem avaliou trabalhos: um por pessoa, evento e fase (texto ou apresentação)
        Schema::create('certificados_avaliador', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('fase', 20);   // escrita | apresentacao
            $table->unsignedInteger('quantidade');
            $table->unsignedSmallInteger('carga_horaria')->nullable();
            $table->string('hash_validacao', 40)->unique();
            $table->dateTime('emitido_em');
            $table->foreignId('emitido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('revogado_em')->nullable();
            $table->timestamps();
            $table->unique(['evento_id', 'user_id', 'fase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_avaliador');
    }
};
