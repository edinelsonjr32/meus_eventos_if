<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->onDelete('cascade');

            $table->string('titulo');
            // Tipos de atividades comuns no IFPA
            $table->enum('tipo', ['palestra', 'minicurso', 'oficina', 'mesa_redonda', 'outro']);
            $table->dateTime('data_inicio');
            $table->dateTime('data_fim');
            $table->integer('carga_horaria'); // Em horas (inteiro)
            // O segredo do QR Code. Deve ser único no sistema todo.
            $table->string('token_frequencia')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividades');
    }
};
