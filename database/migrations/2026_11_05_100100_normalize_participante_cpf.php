<?php

use App\Rules\Cpf;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Padroniza CPFs salvos sem máscara (000.000.000-00), sem violar a unicidade
        DB::table('participantes')->orderBy('id')->each(function ($p) {
            $formatado = Cpf::formatar($p->cpf);

            if ($formatado === $p->cpf || strlen(Cpf::somenteDigitos($p->cpf)) !== 11) {
                return;
            }

            $existe = DB::table('participantes')->where('cpf', $formatado)->where('id', '!=', $p->id)->exists();
            if (!$existe) {
                DB::table('participantes')->where('id', $p->id)->update(['cpf' => $formatado]);
            }
        });
    }

    public function down(): void
    {
        // Sem reversão
    }
};
