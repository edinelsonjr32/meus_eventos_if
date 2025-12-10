<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('evento_user', function (Blueprint $table) {
            // Chaves Estrangeiras Compostas (Chave Primária Composta)
            $table->foreignId('evento_id')->constrained('eventos')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Coluna para definir a Role do usuário DENTRO DESTE EVENTO
            $table->string('role', 20)->default('editor'); // Ex: 'editor', 'visualizador'

            // Define as duas chaves como chave primária composta
            $table->primary(['evento_id', 'user_id']);

            $table->timestamps();
        });
    }

 
    public function down(): void
    {
        Schema::dropIfExists('evento_user');
    }
};
