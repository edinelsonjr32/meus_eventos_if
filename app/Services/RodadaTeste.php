<?php

namespace App\Services;

use App\Models\AreaTematica;
use App\Models\Atividade;
use App\Models\CriterioAvaliacao;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Massa de dados da rodada de testes (contas, eventos, trabalhos, avaliações) com manifesto.
 *
 * Princípios: só CRIA o que o spec descreve; é idempotente (aborta se algo já existe e não está no manifesto);
 * a remoção só apaga o que está no manifesto, os eventos criados pelas contas do manifesto e os cadastros dos alunos
 * (p01@teste.com ... p30@teste.com, p01b, p01c...). Nunca roda migrate:fresh, truncate nem toca em dado alheio.
 */
class RodadaTeste
{
    public const REGEX_ALUNOS = '/^p(0[1-9]|[12]\d|30)[a-z]?@teste\.com$/i';

    public function __construct(private array $spec, private string $manifestoPath)
    {
    }

    // ------------------------------------------------------------------
    // Manifesto
    // ------------------------------------------------------------------

    public function manifesto(): ?array
    {
        return is_file($this->manifestoPath) ? json_decode((string) file_get_contents($this->manifestoPath), true) : null;
    }

    private function salvarManifesto(array $m): void
    {
        @mkdir(dirname($this->manifestoPath), 0700, true);
        file_put_contents($this->manifestoPath, json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        @chmod($this->manifestoPath, 0600);
    }

    // ------------------------------------------------------------------
    // Pré-checagem (somente leitura)
    // ------------------------------------------------------------------

    /** Lista colisões com dados existentes. Vazio = pode preparar. */
    public function colisoes(): array
    {
        $c = [];
        $emails = collect($this->spec['contas_a_criar'])->pluck('email')->map(fn ($e) => mb_strtolower($e));
        foreach (User::whereIn('email', $emails)->pluck('email') as $e) {
            $c[] = "usuário já existe: {$e}";
        }

        $cpfs = collect([$this->spec['cpf_vinculado_ao_inscrito']])
            ->merge(collect($this->spec['alunos_p01_p30'] ?? [])->pluck('cpf'));
        foreach (Participante::whereIn('cpf', $cpfs)->get(['cpf', 'user_id']) as $p) {
            $c[] = "CPF já cadastrado em participantes: {$p->cpf} (user_id " . ($p->user_id ?? 'null') . ')';
        }

        foreach (collect($this->definicaoEventos())->pluck('slug') as $slug) {
            if (Evento::withTrashed()->where('slug', $slug)->exists()) {
                $c[] = "evento (slug) já existe: {$slug}";
            }
        }

        $alunos = User::where('email', 'like', 'p%@teste.com')->pluck('email')->filter(fn ($e) => preg_match(self::REGEX_ALUNOS, $e));
        foreach ($alunos as $e) {
            $c[] = "cadastro de aluno pré-existente: {$e} (seria apagado na remoção; confira)";
        }

        return $c;
    }

    // ------------------------------------------------------------------
    // Preparar
    // ------------------------------------------------------------------

    private function definicaoEventos(): array
    {
        $tz = config('app.timezone');
        $fimChamada = Carbon::create(2026, 11, 10, 23, 59, 0, $tz);
        $prazoVencido = Carbon::create(2026, 11, 1, 23, 59, 0, $tz);
        $inicio = Carbon::create(2026, 11, 10, 8, 0, 0, $tz);
        $fim = Carbon::create(2026, 11, 11, 23, 59, 0, $tz);

        $base = fn (string $titulo, string $slug, string $dono, ?Carbon $submissaoFim, string $tipo) => compact('titulo', 'slug', 'dono', 'submissaoFim', 'tipo') + ['inicio' => $inicio, 'fim' => $fim];

        return [
            $base('Evento Teste', 'evento-teste', 'admin@teste.com', $fimChamada, 'teste'),
            $base('Evento Prazo Encerrado', 'evento-prazo-encerrado', 'admin@teste.com', $prazoVencido, 'prazo'),
            $base('Evento Org D1', 'evento-org-d1', 'org1@teste.com', $fimChamada, 'org'),
            $base('Evento Org D2', 'evento-org-d2', 'org2@teste.com', $fimChamada, 'org'),
            $base('Evento Org D3', 'evento-org-d3', 'org3@teste.com', $fimChamada, 'org'),
        ];
    }

    /** @return array manifesto criado */
    public function preparar(): array
    {
        if ($this->manifesto()) {
            throw new \RuntimeException('Já existe um manifesto em ' . $this->manifestoPath . '. Remova a rodada anterior antes de preparar de novo.');
        }
        if ($colisoes = $this->colisoes()) {
            throw new \RuntimeException("Colisão com dados existentes; nada foi criado:\n - " . implode("\n - ", $colisoes));
        }

        // Nada de e-mail real nem fila diferida durante a preparação
        Config::set('mail.default', 'array');
        Config::set('queue.default', 'sync');

        $m = ['criado_em' => now()->toIso8601String(), 'banco' => DB::connection()->getDatabaseName(),
            'users' => [], 'participantes' => [], 'eventos' => [], 'avaliacoes_org' => [], 'urls' => []];

        try {
            DB::transaction(function () use (&$m) {
                $users = $this->criarContas($m);
                $this->vincularCpf($users['inscrito@teste.com'], $m);
                foreach ($this->definicaoEventos() as $def) {
                    $this->criarEvento($def, $users, $m);
                }
                $this->salvarManifesto($m);
            });
        } catch (\Throwable $e) {
            // A transação desfez o banco; apaga também arquivos de PDF gravados
            foreach ($m['eventos'] as $ev) {
                Storage::disk('trabalhos')->deleteDirectory((string) $ev['id']);
            }
            @unlink($this->manifestoPath);
            throw $e;
        }

        return $m;
    }

    private function criarContas(array &$m): array
    {
        $users = [];
        foreach ($this->spec['contas_a_criar'] as $conta) {
            $email = mb_strtolower($conta['email']);
            $admin = str_starts_with($email, 'admin') || str_starts_with($email, 'org');
            $u = new User(['name' => $this->nomeDe($email), 'email' => $email, 'password' => $conta['senha']]);
            $u->role = $admin ? 'admin' : 'user';
            $u->email_verified_at = now();
            $u->save();
            $users[$email] = $u;
            $m['users'][] = ['id' => $u->id, 'email' => $email, 'role' => $u->role];
        }

        return $users;
    }

    private function nomeDe(string $email): string
    {
        return 'Teste ' . Str::before($email, '@');
    }

    private function vincularCpf(User $inscrito, array &$m): void
    {
        $p = Participante::create([
            'user_id' => $inscrito->id, 'nome_completo' => $inscrito->name, 'cpf' => $this->spec['cpf_vinculado_ao_inscrito'],
            'email' => $inscrito->email, 'tipo_vinculo' => 'externo',
        ]);
        // Estado original guardado: o CT-18 pode religar este participante a outra conta
        $m['participantes'][] = ['id' => $p->id, 'cpf' => $p->cpf, 'user_id' => $inscrito->id, 'nome' => $p->nome_completo, 'email' => $p->email];
    }

    private function criarEvento(array $def, array $users, array &$m): void
    {
        $dono = $users[$def['dono']];
        $evento = Evento::create([
            'titulo' => $def['titulo'], 'slug' => $def['slug'], 'descricao' => 'Evento fictício da rodada de testes. Dados 100% fictícios.',
            'data_inicio' => $def['inicio'], 'data_fim' => $def['fim'], 'local' => 'Laboratório de Informática (teste)',
            'criado_por' => $dono->id, // sem latitude/longitude: presença não exige GPS
        ]);
        $ev = ['id' => $evento->id, 'slug' => $evento->slug, 'tipo' => $def['tipo'], 'atividades' => [], 'trabalhos' => []];

        $teste = $def['tipo'] === 'teste';
        $prazo = $def['tipo'] === 'prazo';
        TrabalhoConfig::create([
            'evento_id' => $evento->id, 'ativo' => true, 'submissao_inicio' => now()->subDays(2), 'submissao_fim' => $def['submissaoFim'],
            'avaliadores_por_trabalho' => 2, 'max_arquivo_mb' => ($teste || $prazo) ? 2 : 20, 'resumo_min_palavras' => 5, 'resumo_max_palavras' => 300,
            'exige_palavras_chave' => false,
        ]);

        $areas = [];
        foreach ($this->spec['areas_tematicas'] as $i => $nome) {
            $areas[] = AreaTematica::create(['evento_id' => $evento->id, 'nome' => $nome, 'ordem' => $i]);
        }
        foreach ($this->spec['criterios_avaliacao'] as $i => $nome) {
            CriterioAvaliacao::create(['evento_id' => $evento->id, 'nome' => $nome, 'peso' => 1, 'nota_max' => 10, 'ordem' => $i]);
        }

        if ($prazo) {
            $m['eventos'][] = $ev;

            return;
        }

        foreach (['aval01', 'aval02', 'aval03', 'aval04', 'aval05', 'aval06'] as $a) {
            DB::table('evento_user')->insert(['evento_id' => $evento->id, 'user_id' => $users["{$a}@teste.com"]->id, 'role' => Evento::PAPEL_AVALIADOR, 'created_at' => now(), 'updated_at' => now()]);
        }

        if ($teste) {
            foreach ($this->atividades($evento) as $a) {
                $ev['atividades'][] = ['id' => $a->id, 'titulo' => $a->titulo, 'token' => $a->token_frequencia];
            }
        }

        $this->criarTrabalhos($evento, $dono, $areas, $users, $teste, $ev, $m);
        $m['eventos'][] = $ev;

        if ($teste) {
            $m['urls']['evento_admin_a'] = [
                'edicao' => "/admin/eventos/{$evento->id}/edit",
                'equipe' => "/admin/eventos/{$evento->id}/equipe",
                'trabalhos' => "/gestao/trabalhos/evento/{$evento->id}",
            ];
        }
    }

    /** @return Atividade[] 4 atividades em 10/11, duas no mesmo horário */
    private function atividades(Evento $evento): array
    {
        $tz = config('app.timezone');
        $h = fn (int $hora, int $min = 0) => Carbon::create(2026, 11, 10, $hora, $min, 0, $tz);
        $lista = [
            ['Palestra de abertura (teste)', 'palestra', $h(9), $h(10)],
            ['Oficina A (teste)', 'oficina', $h(10), $h(11)],
            ['Minicurso B (teste, mesmo horário da Oficina A)', 'minicurso', $h(10), $h(11)],
            ['Mesa redonda (teste)', 'mesa_redonda', $h(14), $h(15, 30)],
        ];
        $out = [];
        foreach ($lista as [$titulo, $tipo, $ini, $fim]) {
            $out[] = Atividade::create([
                'evento_id' => $evento->id, 'titulo' => $titulo, 'tipo' => $tipo, 'responsavel' => 'Equipe de teste', 'local' => 'Sala de teste',
                'data_inicio' => $ini, 'data_fim' => $fim, 'carga_horaria' => 1, 'vagas' => 60, 'token_frequencia' => Str::random(10),
            ]);
        }

        return $out;
    }

    private function pdfMinimo(): string
    {
        $dir = sys_get_temp_dir() . '/rodada-teste-' . Str::random(8);
        mkdir($dir);
        $arq = $dir . '/trabalho.pdf';
        file_put_contents($arq, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        return $arq;
    }

    private function criarTrabalhos(Evento $evento, User $dono, array $areas, array $users, bool $teste, array &$ev, array &$m): void
    {
        $wf = app(TrabalhoWorkflow::class);
        $av = app(AvaliacaoService::class);
        $criterios = $evento->criterios()->get();
        $atrib = $this->spec['atribuicoes'];
        $recusados = collect($this->spec['trabalhos'])->where('recusado', true)->pluck('n')->all();
        $notasOrg = [1 => [[8, 9, 7, 8], [7, 8, 8, 9]], 2 => [[6, 7, 6, 7], [8, 8, 9, 8]], 3 => [[9, 9, 8, 9], [7, 6, 7, 7]]];

        foreach ($this->spec['trabalhos'] as $i => $t) {
            $n = $t['n'];
            $arquivoTmp = $this->pdfMinimo();
            $trabalho = $wf->salvar($evento, $dono, [
                'titulo' => $t['titulo'], 'resumo' => 'Resumo fictício do ' . Str::lower($t['titulo']) . ' para a rodada de testes do sistema.',
                'tipo_trabalho' => 'pesquisa', 'modalidade_apresentacao' => 'oral', 'area_tematica_id' => $areas[$i % count($areas)]->id,
                'autoriza_publicacao' => !$teste,
                'autores' => [['nome_completo' => $t['autor'], 'tipo_autor' => 'autor_principal', 'instituicao' => 'Instituição Fictícia', 'apresentador' => true]],
            ], null, new UploadedFile($arquivoTmp, 'trabalho_exemplo.pdf', 'application/pdf', null, true));
            @unlink($arquivoTmp);
            $wf->submeter($trabalho, $dono);
            $trabalho->refresh();
            $ev['trabalhos'][] = ['n' => $n, 'id' => $trabalho->id];

            if ($teste) {
                // Evento Teste: todos em avaliação, com as atribuições pendentes do spec
                $trabalho->update(['status' => 'em_avaliacao']);
                foreach ($atrib as $conta => $lista) {
                    if (in_array($n, $lista, true)) {
                        $a = $wf->atribuirAvaliador($trabalho->fresh(), $users["{$conta}@teste.com"], $dono);
                        if ($n === 1) {
                            $m['urls']['avaliacoes_trabalho1'][$conta] = "/avaliador/avaliacoes/{$a->id}";
                        }
                    }
                }
                if ($n === 1) {
                    $m['urls']['trabalho_nao_atribuido_aval05_aval06'] = "/trabalhos/{$trabalho->id}";
                }
                continue;
            }

            // Eventos Org: 1-3 avaliados (2 avaliadores, notas lançadas) e aprovados; 4-5 em avaliação SEM atribuição (CT-12);
            // 6-8 aprovados e apresentados; 9-10 recusados (reprovado)
            if (isset($notasOrg[$n])) {
                $trabalho->update(['status' => 'em_avaliacao']);
                foreach (['aval05', 'aval06'] as $k => $conta) {
                    $a = $wf->atribuirAvaliador($trabalho->fresh(), $users["{$conta}@teste.com"], $dono);
                    $dados = ['notas' => [], 'comentarios' => [], 'parecer' => 'Parecer fictício da avaliação de teste.', 'recomendacao' => 'aprovar'];
                    foreach ($criterios as $c => $criterio) {
                        $dados['notas'][$criterio->id] = $notasOrg[$n][$k][$c];
                    }
                    $av->concluir($a->fresh(), $users["{$conta}@teste.com"], $dados);
                    $m['avaliacoes_org'][] = $a->id;
                }
                $av->decidir($trabalho->fresh(), $dono, 'aprovado');
                $trabalho->refresh()->update(['apresentado' => true, 'apresentado_em' => now(), 'apresentacao_origem' => 'manual']);
            } elseif (in_array($n, [4, 5], true)) {
                $trabalho->update(['status' => 'em_avaliacao']);
            } elseif (in_array($n, $recusados, true)) {
                $trabalho->update(['status' => 'reprovado', 'decisao' => 'reprovado', 'decisao_em' => now(), 'decidido_por' => $dono->id, 'decisao_justificativa' => 'Recusado (dado fictício de teste).']);
            } else {
                $trabalho->update(['status' => 'aprovado', 'decisao' => 'aprovado', 'decisao_em' => now(), 'decidido_por' => $dono->id, 'apresentado' => true, 'apresentado_em' => now(), 'apresentacao_origem' => 'manual']);
            }
        }
    }

    // ------------------------------------------------------------------
    // Remover
    // ------------------------------------------------------------------

    /** Conjuntos que a remoção atinge. */
    public function alvo(): array
    {
        $m = $this->manifesto();
        if (!$m) {
            throw new \RuntimeException('Manifesto não encontrado em ' . $this->manifestoPath . '. Nada a remover.');
        }

        $manUsers = collect($m['users'])->pluck('id');
        $alunos = User::where('email', 'like', 'p%@teste.com')->get()->filter(fn ($u) => preg_match(self::REGEX_ALUNOS, $u->email));
        $userIds = $manUsers->merge($alunos->pluck('id'))->unique()->values();

        // Eventos do manifesto + criados por qualquer conta que será removida (ex.: "Evento Novo D1")
        $eventoIds = collect($m['eventos'])->pluck('id')
            ->merge(Evento::withTrashed()->whereIn('criado_por', $userIds)->pluck('id'))->unique()->values();

        $participanteIds = collect($m['participantes'])->pluck('id')
            ->merge(Participante::whereIn('user_id', $userIds)->pluck('id'))->unique()->values();

        $atividadeIds = Atividade::whereIn('evento_id', $eventoIds)->pluck('id');
        $trabalhoIds = Trabalho::withTrashed()->whereIn('evento_id', $eventoIds)->pluck('id');

        return compact('m', 'userIds', 'alunos', 'eventoIds', 'participanteIds', 'atividadeIds', 'trabalhoIds');
    }

    /** Dados dos alvos ligados a coisas FORA do alvo: se houver, a remoção aborta. */
    public function vinculosExternos(array $a): array
    {
        $x = [];
        $q = function (string $t, string $col, $ids, string $descr, \Closure $fora) use (&$x) {
            $n = DB::table($t)->whereIn($col, $ids)->where($fora)->count();
            if ($n) {
                $x[] = "{$n} registro(s) de {$descr}";
            }
        };

        $q('inscricoes', 'participante_id', $a['participanteIds'], 'inscrição em evento fora da rodada', fn ($b) => $b->whereNotIn('evento_id', $a['eventoIds']));
        $q('frequencias', 'participante_id', $a['participanteIds'], 'frequência em atividade fora da rodada', fn ($b) => $b->whereNotIn('atividade_id', $a['atividadeIds']));
        $q('equipes', 'participante_id', $a['participanteIds'], 'equipe de evento fora da rodada', fn ($b) => $b->whereNotIn('evento_id', $a['eventoIds']));
        $q('trabalhos', 'submetido_por', $a['userIds'], 'trabalho submetido em evento fora da rodada', fn ($b) => $b->whereNotIn('evento_id', $a['eventoIds']));
        $q('evento_user', 'user_id', $a['userIds'], 'papel em evento fora da rodada', fn ($b) => $b->whereNotIn('evento_id', $a['eventoIds']));
        $q('trabalho_avaliacoes', 'avaliador_id', $a['userIds'], 'avaliação de trabalho fora da rodada', fn ($b) => $b->whereNotIn('trabalho_id', $a['trabalhoIds']));
        $q('apresentacao_avaliacoes', 'avaliador_id', $a['userIds'], 'avaliação de apresentação fora da rodada', fn ($b) => $b->whereNotIn('trabalho_id', $a['trabalhoIds']));
        $q('eventos', 'criado_por', $a['userIds'], 'evento fora do alvo', fn ($b) => $b->whereNotIn('id', $a['eventoIds']));

        return $x;
    }

    /** Contagens do que será apagado (dry-run) e, se $executar, apaga. */
    public function remover(bool $executar): array
    {
        $a = $this->alvo();
        $ev = $a['eventoIds'];
        $cont = [
            'users' => $a['userIds']->count(),
            'users_alunos' => $a['alunos']->pluck('email')->all(),
            'participantes' => $a['participanteIds']->count(),
            'eventos' => $ev->count(),
            'atividades' => $a['atividadeIds']->count(),
            'inscricoes' => DB::table('inscricoes')->whereIn('evento_id', $ev)->count(),
            'frequencias' => DB::table('frequencias')->whereIn('atividade_id', $a['atividadeIds'])->count(),
            'trabalhos' => $a['trabalhoIds']->count(),
            'avaliacoes' => DB::table('trabalho_avaliacoes')->whereIn('trabalho_id', $a['trabalhoIds'])->count(),
            'certificados_trabalho' => DB::table('certificados_trabalho')->whereIn('trabalho_id', $a['trabalhoIds'])->count(),
        ];
        $externos = $this->vinculosExternos($a);
        $cont['vinculos_externos'] = $externos;

        if (!$executar) {
            return $cont;
        }
        if ($externos) {
            throw new \RuntimeException("Remoção abortada: dados de teste ligados a algo fora da rodada:\n - " . implode("\n - ", $externos));
        }

        DB::transaction(function () use ($a, $ev) {
            // 1) frequências (FK sem cascata) e vínculos de atividade
            DB::table('frequencias')->whereIn('atividade_id', $a['atividadeIds'])->delete();
            DB::table('frequencias')->whereIn('participante_id', $a['participanteIds'])->delete();
            // 2) trabalhos (arquivos/autores/avaliações/notas/histórico/certificados em cascata), depois os eventos (cascata do restante)
            DB::table('trabalhos')->whereIn('id', $a['trabalhoIds'])->delete();
            DB::table('eventos')->whereIn('id', $ev)->delete();
            // 3) participantes e contas
            DB::table('participantes')->whereIn('id', $a['participanteIds'])->delete();
            DB::table('users')->whereIn('id', $a['userIds'])->delete();
        });

        // Arquivos de PDF dos eventos removidos (pasta {evento_id} do disco "trabalhos")
        foreach ($ev as $id) {
            Storage::disk('trabalhos')->deleteDirectory((string) $id);
        }
        @rename($this->manifestoPath, $this->manifestoPath . '.removido-' . now()->format('Ymd-His'));

        return $cont;
    }
}
