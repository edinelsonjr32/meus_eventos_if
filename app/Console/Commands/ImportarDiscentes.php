<?php

namespace App\Console\Commands;

use App\Models\Curso;
use App\Models\Participante;
use App\Models\Turma;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Corrige o cadastro de quem se registrou como "externo" (ou sem curso/turma) usando a planilha de discentes
 * ativos exportada do SIGAA. O participante é localizado pelo NOME (sem diferenciar maiúsculas, acentos e espaços).
 * Por padrão só SIMULA; grave com --aplicar.
 */
class ImportarDiscentes extends Command
{
    protected $signature = 'participantes:importar-discentes
        {arquivo : Caminho do CSV (consulta_geral_discente.csv)}
        {--aplicar : Grava as alterações (sem isso apenas simula)}
        {--criar-cursos : Cria os cursos que ainda não existem (nome igual ao da planilha)}
        {--relatorio= : Salva o relatório detalhado (CSV) neste caminho}';

    protected $description = 'Atualiza tipo (aluno), matrícula, curso e turma dos participantes que constam na planilha de discentes do SIGAA';

    public function handle(): int
    {
        $arquivo = $this->argument('arquivo');
        if (!is_file($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");
            return self::FAILURE;
        }

        $linhas = $this->lerCsv($arquivo);
        if ($linhas === null) {
            $this->error('Não encontrei as colunas Nome, Curso, Matrícula e AnoIngresso no CSV.');
            return self::FAILURE;
        }

        $aplicar = (bool) $this->option('aplicar');
        $criarCursos = (bool) $this->option('criar-cursos');
        $this->line($aplicar ? '<fg=yellow>MODO GRAVAÇÃO</>' : '<fg=cyan>SIMULAÇÃO (nada será gravado; use --aplicar)</>');
        $this->line(count($linhas) . ' discente(s) na planilha.');

        // Participantes por nome normalizado
        $porNome = [];
        foreach (Participante::all() as $p) {
            $porNome[self::normalizar($p->nome_completo)][] = $p;
        }

        // Cursos existentes por nome normalizado
        $cursos = [];
        foreach (Curso::all() as $c) {
            $cursos[self::normalizar($c->nome)] = $c;
        }

        $resumo = ['atualizados' => 0, 'ja_corretos' => 0, 'sem_cadastro' => 0, 'ambiguos' => 0, 'cpf_diferente' => 0, 'servidores' => 0, 'sem_curso' => 0, 'matricula_em_uso' => 0];
        $cursosFaltando = [];
        $relatorio = [];
        $matriculasUsadas = Participante::whereNotNull('matricula')->pluck('id', 'matricula')->all();

        $trabalho = function () use (&$linhas, &$porNome, &$cursos, &$resumo, &$cursosFaltando, &$relatorio, &$matriculasUsadas, $aplicar, $criarCursos) {
            foreach ($linhas as $l) {
                $chave = self::normalizar($l['nome']);
                $candidatos = $porNome[$chave] ?? [];

                if (!$candidatos) { $resumo['sem_cadastro']++; $relatorio[] = [$l['nome'], $l['matricula'], 'sem_cadastro', '']; continue; }

                // Mais de um participante com o mesmo nome: desempata pelo CPF da planilha
                if (count($candidatos) > 1) {
                    $porCpf = array_values(array_filter($candidatos, fn ($p) => $l['cpf'] && self::soDigitos($p->cpf) === $l['cpf']));
                    if (count($porCpf) !== 1) { $resumo['ambiguos']++; $relatorio[] = [$l['nome'], $l['matricula'], 'ambiguo', count($candidatos) . ' cadastros com este nome']; continue; }
                    $candidatos = $porCpf;
                }
                $p = $candidatos[0];

                // Homônimo: o CPF cadastrado é de outra pessoa
                $cpfCad = self::soDigitos($p->cpf);
                if ($l['cpf'] && strlen($cpfCad) === 11 && $cpfCad !== $l['cpf']) { $resumo['cpf_diferente']++; $relatorio[] = [$l['nome'], $l['matricula'], 'cpf_diferente', 'mesmo nome, CPF diferente: não alterado']; continue; }

                if ($p->tipo_vinculo === 'servidor') { $resumo['servidores']++; $relatorio[] = [$l['nome'], $l['matricula'], 'servidor', 'cadastro de servidor: não alterado']; continue; }

                // Matrícula já pertence a outro participante
                if (isset($matriculasUsadas[$l['matricula']]) && $matriculasUsadas[$l['matricula']] !== $p->id) { $resumo['matricula_em_uso']++; $relatorio[] = [$l['nome'], $l['matricula'], 'matricula_em_uso', 'matrícula já usada por outro cadastro']; continue; }

                // Curso
                $curso = $cursos[self::normalizar($l['curso'])] ?? null;
                if (!$curso) {
                    $cursosFaltando[$l['curso']] = ($cursosFaltando[$l['curso']] ?? 0) + 1;
                    if (!$criarCursos) { $resumo['sem_curso']++; $relatorio[] = [$l['nome'], $l['matricula'], 'sem_curso', $l['curso']]; continue; }
                    if ($aplicar) {
                        $curso = $cursos[self::normalizar($l['curso'])] = Curso::create(['nome' => self::tituloCurso($l['curso'])]);
                    }
                }

                // Turma = curso + ano de ingresso
                $turma = null;
                if ($curso) {
                    $turma = $aplicar
                        ? Turma::firstOrCreate(['curso_id' => $curso->id, 'ano' => $l['ano']])
                        : Turma::where('curso_id', $curso->id)->where('ano', $l['ano'])->first();
                }

                $ja = $p->tipo_vinculo === 'aluno' && (string) $p->matricula === $l['matricula'] && $turma && $p->turma_id === $turma->id;
                if ($ja) { $resumo['ja_corretos']++; $relatorio[] = [$l['nome'], $l['matricula'], 'ja_correto', '']; continue; }

                $resumo['atualizados']++;
                $relatorio[] = [$l['nome'], $l['matricula'], $aplicar ? 'atualizado' : 'seria_atualizado', "{$p->tipo_vinculo} → aluno | {$l['curso']} {$l['ano']}"];

                if ($aplicar) {
                    $p->forceFill([
                        'tipo_vinculo' => 'aluno',
                        'matricula' => $l['matricula'],
                        'turma_id' => $turma->id,
                        'vinculo_confirmado_em' => $p->vinculo_confirmado_em ?: now(),   // já está conferido pela planilha: não pede confirmação de novo
                    ])->save();
                    $matriculasUsadas[$l['matricula']] = $p->id;
                }
            }
        };

        $aplicar ? DB::transaction($trabalho) : $trabalho();

        $this->newLine();
        $this->table(['Resultado', 'Qtde'], collect($resumo)->map(fn ($v, $k) => [$k, $v])->values()->all());

        if ($cursosFaltando) {
            $this->warn('Cursos da planilha que não existem no sistema' . ($criarCursos ? ($aplicar ? ' (criados):' : ' (seriam criados):') : ' (use --criar-cursos para criá-los):'));
            foreach ($cursosFaltando as $nome => $n) {
                $this->line("  - {$nome} ({$n})");
            }
        }

        if ($rel = $this->option('relatorio')) {
            $f = fopen($rel, 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Nome', 'Matricula', 'Resultado', 'Detalhe'], ';');
            foreach ($relatorio as $r) fputcsv($f, $r, ';');
            fclose($f);
            $this->info("Relatório salvo em {$rel} (contém nomes: não compartilhe nem versione).");
        }

        $this->info($aplicar ? 'Concluído.' : 'Simulação concluída. Confira e rode de novo com --aplicar.');

        return self::SUCCESS;
    }

    /** Lê a planilha (.csv com ";" em UTF-8/ISO-8859-1, ou .xlsx) e devolve só o necessário. */
    private function lerCsv(string $arquivo): ?array
    {
        $linhas = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION)) === 'xlsx' ? $this->linhasDoXlsx($arquivo) : $this->linhasDoCsv($arquivo);

        $cab = array_shift($linhas) ?? [];
        $idx = [];
        foreach ($cab as $i => $nome) {
            $idx[self::normalizar((string) $nome)] = $i;
        }
        foreach (['nome', 'curso', 'matricula', 'anoingresso'] as $obrig) {
            if (!isset($idx[$obrig])) return null;
        }

        $texto = fn ($v) => is_float($v) ? number_format($v, 0, '', '') : trim((string) $v);   // número do Excel (2.0241E+11) vira texto inteiro

        $saida = [];
        foreach ($linhas as $c) {
            $nome = $texto($c[$idx['nome']] ?? '');
            $matricula = preg_replace('/\D/', '', $texto($c[$idx['matricula']] ?? ''));
            $ano = preg_replace('/\D/', '', $texto($c[$idx['anoingresso']] ?? ''));
            if ($nome === '' || $matricula === '' || $ano === '') continue;
            if (isset($idx['status']) && !str_contains(self::normalizar($texto($c[$idx['status']] ?? 'ativo')), 'ativo')) continue;

            $cpf = isset($idx['cpf']) ? preg_replace('/\D/', '', $texto($c[$idx['cpf']] ?? '')) : '';
            $saida[] = [
                'nome' => $nome,
                'curso' => $texto($c[$idx['curso']] ?? ''),
                'matricula' => $matricula,
                'ano' => $ano,
                'cpf' => $cpf !== '' ? str_pad($cpf, 11, '0', STR_PAD_LEFT) : '',   // o Excel costuma comer o zero à esquerda
            ];
        }

        return $saida;
    }

    private function linhasDoCsv(string $arquivo): array
    {
        $conteudo = file_get_contents($arquivo);
        if (!mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
        }
        $conteudo = ltrim($conteudo, "\xEF\xBB\xBF");

        $saida = [];
        foreach (preg_split('/\r\n|\n|\r/', $conteudo) as $linha) {
            if (trim($linha) !== '') $saida[] = str_getcsv($linha, ';');
        }

        return $saida;
    }

    private function linhasDoXlsx(string $arquivo): array
    {
        $abas = \Maatwebsite\Excel\Facades\Excel::toArray(new class {}, $arquivo);

        return array_values(array_filter($abas[0] ?? [], fn ($l) => array_filter($l, fn ($v) => $v !== null && $v !== '')));
    }

    /** Minúsculas, sem acentos e com espaços simples: "JOÃO  da Silva" == "joao da silva". */
    public static function normalizar(?string $texto): string
    {
        $t = Str::lower(Str::ascii((string) $texto));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $t));
    }

    private static function soDigitos(?string $v): string
    {
        return preg_replace('/\D/', '', (string) $v);
    }

    /** "TÉCNICO EM INFORMÁTICA INTEGRADO AO ENSINO MÉDIO" → "Técnico em Informática Integrado ao Ensino Médio" */
    private static function tituloCurso(string $nome): string
    {
        $t = mb_convert_case(mb_strtolower($nome), MB_CASE_TITLE);

        return preg_replace_callback('/\s(Em|Ao|De|Da|Do|E)\b/u', fn ($m) => ' ' . mb_strtolower($m[1]), $t);
    }
}
