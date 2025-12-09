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
        Schema::table('eventos', function (Blueprint $table) {
            $table->string('latitude')->nullable(); // Ex: -1.91234
            $table->string('longitude')->nullable(); // Ex: -55.12345
            $table->integer('raio_permitido')->default(300); // Em metros
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'raio_permitido']);
        });
    }
};
