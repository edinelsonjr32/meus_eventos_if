<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->unique()->constrained('eventos')->onDelete('cascade');
            $table->string('titulo');
            $table->string('edicao')->nullable();
            $table->string('issn', 20)->nullable();
            $table->string('isbn', 24)->nullable();
            $table->string('doi_prefixo', 40)->nullable();
            $table->text('apresentacao')->nullable();
            $table->text('comissao')->nullable();
            $table->string('local_publicacao')->default('Óbidos');
            $table->string('editora')->default('Instituto Federal do Pará - Campus Óbidos');
            $table->unsignedSmallInteger('ano');
            $table->string('status', 20)->default('rascunho');        // rascunho | publicado
            $table->dateTime('publicado_em')->nullable();
            $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();

            // PDF compilado (gerado em fila)
            $table->string('pdf_status', 20)->nullable();             // gerando | pronto | erro
            $table->text('pdf_erro')->nullable();
            $table->text('pdf_aviso')->nullable();
            $table->string('pdf_path')->nullable();
            $table->dateTime('pdf_gerado_em')->nullable();
            $table->unsignedInteger('pdf_paginas')->nullable();
            $table->unsignedBigInteger('pdf_tamanho')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anais');
    }
};
