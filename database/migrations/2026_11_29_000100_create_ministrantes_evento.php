<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Personalização de quem ministra (foto, descrição, ordem): os nomes vêm das atividades; aqui fica o que se acrescenta
        Schema::create('ministrantes_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->string('chave', 160);   // nome normalizado, igual ao escrito na programação
            $table->string('nome', 160);
            $table->string('descricao', 160)->nullable();
            $table->string('caminho_foto')->nullable();
            $table->unsignedSmallInteger('ordem')->nullable();
            $table->boolean('oculto')->default(false);
            $table->timestamps();
            $table->unique(['evento_id', 'chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ministrantes_evento');
    }
};
