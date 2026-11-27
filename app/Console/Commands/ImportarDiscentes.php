<?php

namespace App\Console\Commands;

use App\Models\Curso;
use App\Models\Participante;
use App\Models\Turma;
use Illuminate\Console\Command;
use App\Models\Frequencia;
use App\Models\Inscricao;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        {--corrigir-conflitos : A planilha manda: servidor vira aluno, CPF divergente é corrigido e cadastros duplicados da mesma pessoa são unificados}
        {--corrigir-nomes : Troca o nome do cadastro pelo nome oficial da planilha (em formato de nome próprio)}
        {--ignorar=* : IDs de cadastros (participantes) que não devem ser alterados; pode repetir a opção}
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
        $corrigir = (bool) $this->option('corrigir-conflitos');
        $corrigirNomes = (bool) $this->option('corrigir-nomes');
        $ignorar = array_map('intval', (array) $this->option('ignorar'));
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
            $cursos[self::normalizar(self::semSufixoIntegrado($c->nome))] ??= $c;
        }

        // Para --corrigir-conflitos: localizar também pelo CPF e pela matrícula da planilha
        $porCpf = [];
        $porMatricula = [];
        foreach (Participante::all() as $p) {
            if (strlen(self::soDigitos($p->cpf)) === 11) $porCpf[self::soDigitos($p->cpf)] = $p;
            if ($p->matricula) $porMatricula[$p->matricula] = $p;
        }

        $resumo = ['atualizados' => 0, 'ja_corretos' => 0, 'sem_cadastro' => 0, 'ambiguos' => 0, 'cpf_diferente' => 0, 'servidores' => 0, 'sem_curso' => 0, 'matricula_em_uso' => 0, 'conflitos_corrigidos' => 0, 'cadastros_unificados' => 0, 'bloqueados' => 0, 'achados_pelo_cpf' => 0, 'ignorados' => 0, 'nomes_corrigidos' => 0];
        $cursosFaltando = [];
        $relatorio = [];
        $matriculasUsadas = Participante::whereNotNull('matricula')->pluck('id', 'matricula')->all();

        $trabalho = function () use (&$linhas, &$porNome, &$porCpf, &$porMatricula, &$cursos, &$resumo, &$cursosFaltando, &$relatorio, &$matriculasUsadas, $aplicar, $criarCursos, $corrigir, $ignorar, $corrigirNomes) {
            foreach ($linhas as $l) {
                $chave = self::normalizar($l['nome']);
                $candidatos = $porNome[$chave] ?? [];
                $forcado = false;

                // --corrigir-conflitos: nome + dono do CPF + dono da matrícula são tratados como a MESMA pessoa
                if ($corrigir) {
                    $grupo = [];
                    foreach ($candidatos as $c) $grupo[$c->id] = $c;
                    // CPF igual é a mesma pessoa, mesmo que o nome tenha sido digitado diferente ("Ana Clara" x "Ana Clara Barros").
                    // Matrícula igual com nome E CPF diferentes é outra pessoa: nunca é unificada (vai para revisão manual).
                    $outraPessoa = null;
                    $donoCpf = $l['cpf'] ? ($porCpf[$l['cpf']] ?? null) : null;
                    if ($donoCpf && self::normalizar($donoCpf->nome_completo) !== $chave) $grupo = [];   // o CPF achou a pessoa com outro nome: quem só tem o mesmo nome pode ser um homônimo
                    if ($donoCpf) $grupo[$donoCpf->id] = $donoCpf;
                    if ($dono = ($porMatricula[$l['matricula']] ?? null)) {
                        if (self::normalizar($dono->nome_completo) === $chave || ($donoCpf && $donoCpf->id === $dono->id)) $grupo[$dono->id] = $dono; else $outraPessoa = $dono;
                    }
                    if ($outraPessoa) { $resumo['bloqueados']++; $relatorio[] = [$l['nome'], $l['matricula'], 'bloqueado', "matrícula já está no cadastro #{$outraPessoa->id} ({$outraPessoa->nome_completo}), de outra pessoa: não alterado"]; continue; }
                    $grupo = array_values($grupo);

                    $unico = count($grupo) === 1 ? $grupo[0] : null;
                    $cpfUnico = $unico ? self::soDigitos($unico->cpf) : '';
                    $nomeDiferente = $unico && self::normalizar($unico->nome_completo) !== $chave;
                    $conflito = count($grupo) > 1 || ($unico && ($unico->tipo_vinculo === 'servidor' || ($l['cpf'] && strlen($cpfUnico) === 11 && $cpfUnico !== $l['cpf'])));

                    if ($conflito) {
                        [$p, $detalhe] = $this->resolverConflito($grupo, $l, $aplicar);
                        $forcado = true;
                        $resumo['conflitos_corrigidos']++;
                        if (count($grupo) > 1) $resumo['cadastros_unificados'] += count($grupo) - 1;
                        $relatorio[] = [$l['nome'], $l['matricula'], $aplicar ? 'conflito_corrigido' : 'seria_corrigido', $detalhe];
                        if ($aplicar) {
                            $matriculasUsadas[$l['matricula']] = $p->id;
                            $porMatricula[$l['matricula']] = $p;
                        }
                    } elseif ($nomeDiferente) {
                        // achado pelo CPF, com o nome escrito de outro jeito no cadastro
                        $p = $unico;
                        $forcado = true;
                        $resumo['achados_pelo_cpf']++;
                        $relatorio[] = [$l['nome'], $l['matricula'], 'achado_pelo_cpf', "cadastro #{$p->id} está como \"{$p->nome_completo}\""];
                    }
                }

                if (!$forcado) {
                    if (!$candidatos) { $resumo['sem_cadastro']++; $relatorio[] = [$l['nome'], $l['matricula'], 'sem_cadastro', '']; continue; }

                    // Mais de um participante com o mesmo nome: desempata pelo CPF da planilha
                    if (count($candidatos) > 1) {
                        $doCpf = array_values(array_filter($candidatos, fn ($p) => $l['cpf'] && self::soDigitos($p->cpf) === $l['cpf']));
                        if (count($doCpf) !== 1) { $resumo['ambiguos']++; $relatorio[] = [$l['nome'], $l['matricula'], 'ambiguo', count($candidatos) . ' cadastros com este nome']; continue; }
                        $candidatos = $doCpf;
                    }
                    $p = $candidatos[0];

                    // Homônimo: o CPF cadastrado é de outra pessoa
                    $cpfCad = self::soDigitos($p->cpf);
                    if ($l['cpf'] && strlen($cpfCad) === 11 && $cpfCad !== $l['cpf']) { $resumo['cpf_diferente']++; $relatorio[] = [$l['nome'], $l['matricula'], 'cpf_diferente', 'mesmo nome, CPF diferente: não alterado']; continue; }

                    if ($p->tipo_vinculo === 'servidor') { $resumo['servidores']++; $relatorio[] = [$l['nome'], $l['matricula'], 'servidor', 'cadastro de servidor: não alterado']; continue; }

                    // Matrícula já pertence a outro participante
                    if (isset($matriculasUsadas[$l['matricula']]) && $matriculasUsadas[$l['matricula']] !== $p->id) { $resumo['matricula_em_uso']++; $relatorio[] = [$l['nome'], $l['matricula'], 'matricula_em_uso', 'matrícula já usada por outro cadastro']; continue; }
                }

                if (in_array($p->id, $ignorar, true)) { $resumo['ignorados']++; $relatorio[] = [$l['nome'], $l['matricula'], 'ignorado', "cadastro #{$p->id} ({$p->nome_completo}) na lista --ignorar: não alterado"]; continue; }

                // Nome oficial da planilha (o do cadastro vai no certificado)
                if ($corrigirNomes) {
                    $oficial = self::tituloNome($l['nome']);
                    if ($p->nome_completo !== $oficial) {
                        $resumo['nomes_corrigidos']++;
                        $relatorio[] = [$l['nome'], $l['matricula'], $aplicar ? 'nome_corrigido' : 'seria_nome_corrigido', "\"{$p->nome_completo}\" → \"{$oficial}\""];
                        if ($aplicar) { $p->nome_completo = $oficial; $p->save(); }
                    }
                }

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

    /**
     * Resolve um conflito pela planilha: escolhe o cadastro a manter (o com mais frequências/inscrições; empate: o do CPF
     * da planilha), leva para ele tudo dos demais, apaga os repetidos e corrige o CPF. Em simulação só escolhe e descreve.
     *
     * @return array{0: Participante, 1: string}
     */
    private function resolverConflito(array $grupo, array $l, bool $aplicar): array
    {
        $peso = fn (Participante $p) => Frequencia::where('participante_id', $p->id)->count() * 10 + Inscricao::where('participante_id', $p->id)->count();
        usort($grupo, function ($a, $b) use ($peso, $l) {
            return [$peso($b), self::soDigitos($b->cpf) === $l['cpf'] ? 1 : 0, -$b->id] <=> [$peso($a), self::soDigitos($a->cpf) === $l['cpf'] ? 1 : 0, -$a->id];
        });
        $manter = array_shift($grupo);
        $detalhe = [];

        if ($manter->tipo_vinculo === 'servidor') $detalhe[] = 'servidor → aluno';
        foreach ($grupo as $repetido) {
            $detalhe[] = "cadastro #{$repetido->id} unificado em #{$manter->id}" . ($repetido->email ? " (conta: {$repetido->email})" : '');
            if ($aplicar) $this->unificar($manter, $repetido);
        }

        $cpfManter = self::soDigitos($manter->cpf);
        if ($l['cpf'] && $cpfManter !== $l['cpf']) {
            $detalhe[] = 'CPF corrigido pela planilha';
            if ($aplicar) {
                $manter->cpf = substr($l['cpf'], 0, 3) . '.' . substr($l['cpf'], 3, 3) . '.' . substr($l['cpf'], 6, 3) . '-' . substr($l['cpf'], 9, 2);
                $manter->save();
            }
        }

        return [$manter, implode('; ', $detalhe) ?: 'ok'];
    }

    /** Move tudo que aponta para o cadastro repetido (inscrições, frequências, trabalhos...) para o que fica e apaga o repetido. */
    private function unificar(Participante $manter, Participante $repetido): void
    {
        foreach (Schema::getTables() as $tabela) {
            foreach (Schema::getForeignKeys($tabela['name']) as $fk) {
                if ($fk['foreign_table'] !== 'participantes' || count($fk['columns']) !== 1) continue;
                $coluna = $fk['columns'][0];

                foreach (DB::table($tabela['name'])->where($coluna, $repetido->id)->get() as $linha) {
                    $consulta = DB::table($tabela['name']);
                    foreach ((array) $linha as $k => $v) {
                        $v === null ? $consulta->whereNull($k) : $consulta->where($k, $v);
                    }
                    try {
                        $consulta->update([$coluna => $manter->id]);
                    } catch (QueryException) {
                        $consulta->delete();   // o cadastro que fica já tem este mesmo registro (ex.: mesma presença): o repetido é redundante
                    }
                }
            }
        }

        // A conta de login do repetido fica sem cadastro; se o que fica não tem conta, herda a dele
        if (!$manter->user_id && $repetido->user_id) {
            $user = $repetido->user_id;
            $repetido->forceFill(['user_id' => null])->save();
            $manter->forceFill(['user_id' => $user])->save();
        }
        $repetido->delete();
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

        // Algumas planilhas vêm com acentos corrompidos ("T…CNICO", "JO√O"): bytes ISO-8859-1 lidos como MacRoman
        $reparar = $this->planilhaCorrompida($linhas, $idx['curso']);
        if ($reparar) {
            $this->warn('Acentos corrompidos na planilha (codificação MacRoman): reparando automaticamente.');
        }

        $texto = fn ($v) => $reparar && is_string($v) ? self::repararAcentos($v) : (is_float($v) ? number_format($v, 0, '', '') : trim((string) $v));

        $saida = [];
        foreach ($linhas as $c) {
            // "Técnico em X Integrado ao Ensino Médio" do SIGAA é o curso "Técnico em X" do sistema
            $nome = $texto($c[$idx['nome']] ?? '');
            $matricula = preg_replace('/\D/', '', $texto($c[$idx['matricula']] ?? ''));
            $ano = preg_replace('/\D/', '', $texto($c[$idx['anoingresso']] ?? ''));
            if ($nome === '' || $matricula === '' || $ano === '') continue;
            if (isset($idx['status']) && !str_contains(self::normalizar($texto($c[$idx['status']] ?? 'ativo')), 'ativo')) continue;

            $cpf = isset($idx['cpf']) ? preg_replace('/\D/', '', $texto($c[$idx['cpf']] ?? '')) : '';
            $saida[] = [
                'nome' => $nome,
                'curso' => self::semSufixoIntegrado($texto($c[$idx['curso']] ?? '')),
                'matricula' => $matricula,
                'ano' => $ano,
                'cpf' => $cpf !== '' ? str_pad($cpf, 11, '0', STR_PAD_LEFT) : '',   // o Excel costuma comer o zero à esquerda
            ];
        }

        return $saida;
    }

    /** A coluna Curso deveria ter "TÉCNICO/TECNOLOGIA…": se só tem depois de reparar, o arquivo inteiro está corrompido. */
    private function planilhaCorrompida(array $linhas, int $colCurso): bool
    {
        $esperado = '/T[EÉ]CNIC|TECNOLOG|BACHAREL|LICENCIATURA|ENGENHARIA|ESPECIALIZA/iu';
        foreach ($linhas as $l) {
            $c = is_string($l[$colCurso] ?? null) ? $l[$colCurso] : '';
            if (trim($c) === '') continue;

            return !preg_match($esperado, $c) && preg_match($esperado, self::repararAcentos($c));
        }

        return false;
    }

    /** Desfaz "ISO-8859-1 lido como MacRoman". Se o texto não tiver como ser convertido, devolve como veio. */
    public static function repararAcentos(string $texto): string
    {
        $bytes = @iconv('UTF-8', 'MACINTOSH', $texto);

        return $bytes === false ? trim($texto) : trim(mb_convert_encoding($bytes, 'UTF-8', 'ISO-8859-1'));
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

    /** Tira o "Integrado ao Ensino Médio" do fim do nome do curso ("… - PROEJA" é outro curso e fica como está). */
    public static function semSufixoIntegrado(string $nome): string
    {
        return trim(preg_replace('/\s+integrado\s+ao\s+ensino\s+m[eé]dio\s*$/iu', '', $nome));
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

    /** "JOÃO DA SILVA" → "João da Silva" (partículas em minúscula, algarismos romanos em maiúscula) */
    public static function tituloNome(string $nome): string
    {
        $palavras = preg_split('/\s+/u', mb_strtolower(trim($nome)));

        return implode(' ', array_map(function ($w, $i) {
            if ($i > 0 && in_array($w, ['da', 'de', 'do', 'das', 'dos', 'e'], true)) return $w;
            if (preg_match('/^(ii|iii|iv|vi|vii|viii|ix)$/u', $w)) return mb_strtoupper($w);

            return preg_replace_callback('/(^|[\-\'])(\p{L})/u', fn ($m) => $m[1] . mb_strtoupper($m[2]), $w);
        }, $palavras, array_keys($palavras)));
    }

    /** "TÉCNICO EM INFORMÁTICA INTEGRADO AO ENSINO MÉDIO" → "Técnico em Informática Integrado ao Ensino Médio" */
    private static function tituloCurso(string $nome): string
    {
        $t = mb_convert_case(mb_strtolower($nome), MB_CASE_TITLE);

        return preg_replace_callback('/\s(Em|Ao|De|Da|Do|E)\b/u', fn ($m) => ' ' . mb_strtolower($m[1]), $t);
    }
}
