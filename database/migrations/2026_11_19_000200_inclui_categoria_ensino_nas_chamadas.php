<?php

use App\Models\TrabalhoConfig;
use Illuminate\Database\Migrations\Migration;

/** Chamadas que já tinham categorias salvas passam a aceitar também "Ensino" (as sem lista já aceitam todas). */
return new class extends Migration
{
    public function up(): void
    {
        TrabalhoConfig::whereNotNull('tipos_permitidos')->get()->each(function (TrabalhoConfig $c) {
            $tipos = $c->tipos_permitidos ?? [];
            if ($tipos !== [] && !in_array('ensino', $tipos, true)) {
                $c->update(['tipos_permitidos' => [...$tipos, 'ensino']]);
            }
        });
    }

    public function down(): void
    {
        TrabalhoConfig::whereNotNull('tipos_permitidos')->get()->each(function (TrabalhoConfig $c) {
            $c->update(['tipos_permitidos' => array_values(array_diff($c->tipos_permitidos ?? [], ['ensino']))]);
        });
    }
};
