<?php

namespace App\Console\Commands;

use App\Models\Curso;
use App\Models\Turma;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Junta o curso "Técnico em X Integrado ao Ensino Médio" (criado pela importação de discentes) no curso correto
 * "Técnico em X": leva turmas e alunos e apaga o repetido. Sem curso correto, apenas renomeia.
 * Por padrão só SIMULA; grave com --aplicar.
 */
class UnificarCursos extends Command
{
    protected $signature = 'cursos:unificar-integrados
        {--de= : Junta um curso específico (nome ou número) em outro; usar junto com --para}
        {--para= : Curso que permanece (nome ou número)}
        {--aplicar : Grava as alterações (sem isso apenas simula)}';

    protected $description = 'Remove "Integrado ao Ensino Médio" do nome dos cursos, unificando turmas e alunos no curso correto';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $this->line($aplicar ? '<fg=yellow>MODO GRAVAÇÃO</>' : '<fg=cyan>SIMULAÇÃO (nada será gravado; use --aplicar)</>');

        if ($this->option('de') !== null || $this->option('para') !== null) {
            return $this->juntarEscolhidos($aplicar);
        }

        $repetidos = Curso::orderBy('id')->get()->filter(fn (Curso $c) => ImportarDiscentes::semSufixoIntegrado($c->nome) !== $c->nome);

        if ($repetidos->isEmpty()) {
            $this->info('Nenhum curso com "Integrado ao Ensino Médio" no nome.');
            $this->listar();

            return self::SUCCESS;
        }

        DB::transaction(function () use ($repetidos, $aplicar) {
            foreach ($repetidos as $errado) {
                $nome = ImportarDiscentes::semSufixoIntegrado($errado->nome);
                $certo = Curso::where('id', '!=', $errado->id)->get()
                    ->first(fn (Curso $c) => ImportarDiscentes::normalizar($c->nome) === ImportarDiscentes::normalizar($nome));
                $alunos = DB::table('participantes')->whereIn('turma_id', Turma::where('curso_id', $errado->id)->pluck('id'))->count();

                if (!$certo) {
                    $this->line("- \"{$errado->nome}\" → renomear para \"{$nome}\" ({$alunos} aluno(s))");
                    if ($aplicar) {
                        $errado->update(['nome' => $nome]);
                    }

                    continue;
                }

                $this->line("- \"{$errado->nome}\" → unificar em \"{$certo->nome}\" ({$alunos} aluno(s)) e excluir o repetido");
                if ($aplicar) {
                    $this->unificar($errado, $certo);
                }
            }
        });

        if ($aplicar) {
            $this->listar();
        }
        $this->info($aplicar ? 'Concluído.' : 'Simulação concluída. Confira e rode de novo com --aplicar.');

        return self::SUCCESS;
    }

    private function juntarEscolhidos(bool $aplicar): int
    {
        $de = $this->localizar((string) $this->option('de'));
        $para = $this->localizar((string) $this->option('para'));

        if (!$de || !$para || $de->id === $para->id) {
            $this->error('Informe em --de e --para dois cursos diferentes e existentes (nome exato ou número).');
            $this->listar();

            return self::FAILURE;
        }

        $alunos = DB::table('participantes')->whereIn('turma_id', Turma::where('curso_id', $de->id)->pluck('id'))->count();
        $this->line("- \"{$de->nome}\" (#{$de->id}) → unificar em \"{$para->nome}\" (#{$para->id}): {$alunos} aluno(s) mudam de curso e \"{$de->nome}\" é excluído");

        if ($aplicar) {
            DB::transaction(fn () => $this->unificar($de, $para));
            $this->listar();
        }
        $this->info($aplicar ? 'Concluído.' : 'Simulação concluída. Confira e rode de novo com --aplicar.');

        return self::SUCCESS;
    }

    /** Pelo número ou pelo nome (sem diferenciar maiúsculas e acentos). Nome repetido não é aceito: use o número. */
    private function localizar(string $valor): ?Curso
    {
        if (ctype_digit($valor)) {
            return Curso::find((int) $valor);
        }
        $achados = Curso::all()->filter(fn (Curso $c) => ImportarDiscentes::normalizar($c->nome) === ImportarDiscentes::normalizar($valor));

        return $achados->count() === 1 ? $achados->first() : null;
    }

    /** Turma do mesmo ano é fundida; turma de ano que o curso correto não tem muda de curso. */
    private function unificar(Curso $errado, Curso $certo): void
    {
        foreach (Turma::where('curso_id', $errado->id)->get() as $turma) {
            $destino = Turma::where('curso_id', $certo->id)->where('ano', $turma->ano)->first();

            if (!$destino) {
                DB::table('turmas')->where('id', $turma->id)->update(['curso_id' => $certo->id]);

                continue;
            }

            $this->repontar('turmas', $turma->id, $destino->id);
            DB::table('turmas')->where('id', $turma->id)->delete();
        }

        $this->repontar('cursos', $errado->id, $certo->id, ['turmas']);
        DB::table('cursos')->where('id', $errado->id)->delete();
    }

    /** Aponta para o registro que fica tudo que apontava para o que sai (qualquer tabela com chave estrangeira). */
    private function repontar(string $tabelaReferida, int $de, int $para, array $ignorar = []): void
    {
        foreach (Schema::getTables(Schema::getCurrentSchemaName()) as $tabela) {
            if (in_array($tabela['name'], $ignorar, true)) {
                continue;
            }
            foreach (Schema::getForeignKeys($tabela['name']) as $fk) {
                if ($fk['foreign_table'] === $tabelaReferida && count($fk['columns']) === 1) {
                    DB::table($tabela['name'])->where($fk['columns'][0], $de)->update([$fk['columns'][0] => $para]);
                }
            }
        }
    }

    private function listar(): void
    {
        $this->newLine();
        $this->line('Cursos cadastrados:');
        foreach (Curso::orderBy('nome')->get() as $c) {
            $n = DB::table('participantes')->whereIn('turma_id', Turma::where('curso_id', $c->id)->pluck('id'))->count();
            $this->line("  - #{$c->id} {$c->nome} ({$n} aluno(s))");
        }
    }
}
