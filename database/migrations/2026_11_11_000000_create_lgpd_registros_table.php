<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trilha de auditoria dos atendimentos a titulares. NÃO guarda o dado pessoal: só o hash do identificador.
        Schema::create('lgpd_registros', function (Blueprint $table) {
            $table->id();
            $table->string('acao', 30);                 // exportacao | anonimizacao | exclusao_conta | limpeza_rascunhos
            $table->string('titular_hash', 64)->nullable()->index();
            $table->foreignId('executado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->json('resumo')->nullable();         // contagens do que foi afetado
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgpd_registros');
    }
};
