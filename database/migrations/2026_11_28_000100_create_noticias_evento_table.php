<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('noticias_evento', function (Blueprint $t) {
            $t->id();
            $t->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $t->string('titulo', 160);
            $t->longText('conteudo')->nullable();
            $t->dateTime('publicado_em');
            $t->boolean('publicada')->default(true);   // desligada = rascunho, não aparece no site
            $t->boolean('fixada')->default(false);     // fica no topo
            $t->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['evento_id', 'publicada', 'publicado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noticias_evento');
    }
};
