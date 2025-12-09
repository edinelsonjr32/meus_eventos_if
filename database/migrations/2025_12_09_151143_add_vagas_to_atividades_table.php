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
        Schema::table('atividades', function (Blueprint $table) {
            // Default null = ilimitado, ou defina um padrão como 50
            $table->integer('vagas')->nullable()->default(50)->after('carga_horaria');
        });
    }

    public function down(): void
    {
        Schema::table('atividades', function (Blueprint $table) {
            $table->dropColumn('vagas');
        });
    }
};
