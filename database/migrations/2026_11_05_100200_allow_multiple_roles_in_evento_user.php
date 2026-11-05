<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite que o mesmo usuário tenha mais de um papel no mesmo evento
     * (ex.: gestor de trabalhos e avaliador). A chave passa a ser (evento, usuário, papel).
     */
    public function up(): void
    {
        Schema::table('evento_user', function (Blueprint $table) {
            // Índice próprio para a FK de user_id antes de remover a PK composta
            $table->index('user_id', 'evento_user_user_id_index');
            $table->unique(['evento_id', 'user_id', 'role'], 'evento_user_evento_user_role_unique');
        });

        Schema::table('evento_user', function (Blueprint $table) {
            $table->dropPrimary(['evento_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('evento_user', function (Blueprint $table) {
            $table->primary(['evento_id', 'user_id']);
            $table->dropUnique('evento_user_evento_user_role_unique');
            $table->dropIndex('evento_user_user_id_index');
        });
    }
};
