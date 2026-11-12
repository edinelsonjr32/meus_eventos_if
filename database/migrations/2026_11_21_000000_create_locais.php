<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private function normalizar(string $nome): string
    {
        return (string) Str::of($nome)->squish()->ascii()->lower();
    }

    public function up(): void
    {
        Schema::create('locais', function (Blueprint $t) {
            $t->id();
            $t->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $t->string('nome');
            $t->string('nome_normalizado');           // minúsculo, sem acento e sem espaços repetidos: garante unicidade sem "case sensitive"
            $t->string('bloco')->nullable();          // prédio, andar, referência
            $t->string('tipo', 30)->default('sala');
            $t->unsignedInteger('capacidade')->nullable();
            $t->text('equipamentos')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();

            $t->unique(['evento_id', 'nome_normalizado']);
        });

        Schema::table('atividades', fn (Blueprint $t) => $t->foreignId('local_id')->nullable()->after('local')->constrained('locais')->nullOnDelete());
        Schema::table('demandas_voluntarios', fn (Blueprint $t) => $t->foreignId('local_id')->nullable()->after('local')->constrained('locais')->nullOnDelete());

        // Dados existentes: cada nome de local (sem diferenciar maiúsculas/acentos) vira um cadastro do evento
        $criados = [];
        $obter = function (int $eventoId, string $nome, ?string $bloco = null) use (&$criados) {
            $nome = trim(preg_replace('/\s+/', ' ', $nome));
            $chave = $eventoId . '|' . $this->normalizar($nome);
            if (!isset($criados[$chave])) {
                $criados[$chave] = DB::table('locais')->insertGetId([
                    'evento_id' => $eventoId, 'nome' => $nome, 'nome_normalizado' => $this->normalizar($nome), 'bloco' => $bloco,
                    'tipo' => preg_match('/lab/i', $nome) ? 'laboratorio' : 'sala', 'ativo' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $criados[$chave];
        };

        foreach (DB::table('atividades')->get() as $a) {
            $nome = trim((string) ($a->espaco ?: $a->local));
            if ($nome === '') {
                continue;
            }
            $bloco = $a->espaco && $a->local && $this->normalizar($a->local) !== $this->normalizar($a->espaco) ? $a->local : null;
            $id = $obter($a->evento_id, $nome, $bloco);
            DB::table('atividades')->where('id', $a->id)->update(['local_id' => $id, 'local' => $nome]);
        }

        foreach (DB::table('demandas_voluntarios')->whereNotNull('local')->get() as $d) {
            if (trim((string) $d->local) === '') {
                continue;
            }
            DB::table('demandas_voluntarios')->where('id', $d->id)->update(['local_id' => $obter($d->evento_id, $d->local)]);
        }
    }

    public function down(): void
    {
        Schema::table('demandas_voluntarios', function (Blueprint $t) {
            $t->dropConstrainedForeignId('local_id');
        });
        Schema::table('atividades', function (Blueprint $t) {
            $t->dropConstrainedForeignId('local_id');
        });
        Schema::dropIfExists('locais');
    }
};
