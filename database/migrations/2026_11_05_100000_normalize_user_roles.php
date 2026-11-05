<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Perfil comum passa a ser sempre "user" ("padrao" era um valor legado do cadastro de usuários)
        DB::table('users')->where('role', 'padrao')->update(['role' => 'user']);
    }

    public function down(): void
    {
        // Sem reversão: "padrao" e "user" eram equivalentes.
    }
};
