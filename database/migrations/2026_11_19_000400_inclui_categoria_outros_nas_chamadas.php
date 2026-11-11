<?php

use App\Models\TrabalhoConfig;
use Illuminate\Database\Migrations\Migration;

/** Chamadas que já tinham categorias salvas passam a aceitar também "Outros" (as sem lista já aceitam todas). */
return new class extends Migration
{
    public function up(): void
    {
        TrabalhoConfig::whereNotNull('tipos_permitidos')->get()->each(function (TrabalhoConfig $c) {
            $tipos = $c->tipos_permitidos ?? [];
            if ($tipos !== [] && !in_array('outros', $tipos, true)) {
                $c->update(['tipos_permitidos' => [...$tipos, 'outros']]);
            }
        });
    }

    public function down(): void
    {
        TrabalhoConfig::whereNotNull('tipos_permitidos')->get()->each(function (TrabalhoConfig $c) {
            $c->update(['tipos_permitidos' => array_values(array_diff($c->tipos_permitidos ?? [], ['outros']))]);
        });
    }
};
