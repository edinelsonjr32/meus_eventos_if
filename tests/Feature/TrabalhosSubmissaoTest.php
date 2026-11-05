<?php

use App\Mail\TrabalhoSubmetidoMail;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function usuario(string $role = 'user'): User
{
    $u = User::factory()->create();
    $u->forceFill(['role' => $role])->save();

    return $u;
}

function pdfValido(string $nome = 'trabalho.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nome, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function resumoCom(int $palavras): string
{
    return trim(str_repeat('palavra ', $palavras));
}

function autoresValidos(): array
{
    return [
        ['nome_completo' => 'Ana Autora', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => 1, 'correspondente' => 1],
        ['nome_completo' => 'Otto Orientador', 'cpf' => '529.982.247-25', 'email' => 'otto@x.com', 'tipo_autor' => 'orientador', 'apresentador' => 0, 'correspondente' => 0],
    ];
}

function dadosValidos(array $sobrescrever = []): array
{
    return array_merge([
        'acao' => 'submeter',
        'titulo' => 'Um trabalho de teste',
        'resumo' => resumoCom(40),
        'palavras_chave' => 'ensino, tecnologia, inovação',
        'tipo_trabalho' => 'resumo',
        'modalidade_apresentacao' => 'oral',
        'declaracao_ineditismo' => 1,
        'declaracao_regulamento' => 1,
        'autoriza_publicacao' => 1,
        'autores' => autoresValidos(),
        'arquivo' => pdfValido(),
    ], $sobrescrever);
}

beforeEach(function () {
    Storage::fake('trabalhos');
    Mail::fake();

    $this->dono = usuario('admin');
    $this->evento = Evento::create([
        'titulo' => 'Evento', 'slug' => 'ev-' . uniqid(), 'data_inicio' => now(), 'data_fim' => now()->addDays(3),
        'local' => 'X', 'criado_por' => $this->dono->id,
    ]);
    $this->config = TrabalhoConfig::create([
        'evento_id' => $this->evento->id, 'ativo' => true, 'submissao_inicio' => now()->subDay(), 'submissao_fim' => now()->addDays(5),
        'max_autores' => 3, 'resumo_min_palavras' => 20, 'resumo_max_palavras' => 100,
    ]);
    $this->autor = usuario();
    $this->url = route('trabalhos.store', $this->evento->slug);
});

// ---------- Submissão ----------
it('submete um trabalho completo: protocolo, status, autores, PDF privado e e-mails', function () {
    $this->actingAs($this->autor)->post($this->url, dadosValidos())->assertSessionHasNoErrors();

    $t = Trabalho::with(['autores', 'arquivoAtual'])->firstOrFail();

    expect($t->status)->toBe('submetido')
        ->and($t->codigo)->toBe("TRB-{$this->evento->id}-0001")
        ->and($t->autores)->toHaveCount(2)
        ->and($t->autores->first()->tipo_autor)->toBe('autor_principal')
        ->and($t->palavras_chave)->toBe(['ensino', 'tecnologia', 'inovação'])
        ->and($t->arquivoAtual->sha256)->toHaveLength(64)
        ->and(Storage::disk('trabalhos')->exists($t->arquivoAtual->path))->toBeTrue()
        ->and(Storage::disk('public')->exists($t->arquivoAtual->path))->toBeFalse()
        ->and($t->historico->pluck('para')->all())->toBe(['rascunho', 'submetido']);

    Mail::assertQueued(TrabalhoSubmetidoMail::class, 3); // 2 autores + submetente
});

it('gera protocolos sequenciais por evento', function () {
    foreach ([1, 2] as $n) {
        $this->actingAs($this->autor)->post($this->url, dadosValidos(['titulo' => "Trabalho {$n}"]))->assertSessionHasNoErrors();
    }

    expect(Trabalho::orderBy('id')->pluck('codigo')->all())
        ->toBe(["TRB-{$this->evento->id}-0001", "TRB-{$this->evento->id}-0002"]);
});

it('aceita rascunho apenas com o título e sem arquivo', function () {
    $this->actingAs($this->autor)->post($this->url, ['acao' => 'rascunho', 'titulo' => 'Em andamento'])->assertSessionHasNoErrors();

    $t = Trabalho::firstOrFail();
    expect($t->status)->toBe('rascunho')->and($t->codigo)->toBeNull();
    Mail::assertNothingQueued();
});

it('rejeita a submissão com dados incompletos ou inválidos', function (array $alteracao, string $campo) {
    $this->actingAs($this->autor)->post($this->url, dadosValidos($alteracao))->assertSessionHasErrors($campo);
    expect(Trabalho::count())->toBe(0);
})->with([
    'sem arquivo' => [['arquivo' => null], 'arquivo'],
    'arquivo que não é PDF' => [fn () => ['arquivo' => UploadedFile::fake()->createWithContent('x.pdf', 'isto não é um pdf')], 'arquivo'],
    'sem declaração de ineditismo' => [['declaracao_ineditismo' => null], 'declaracao_ineditismo'],
    'sem regulamento' => [['declaracao_regulamento' => null], 'declaracao_regulamento'],
    'resumo curto' => [['resumo' => 'poucas palavras'], 'resumo'],
    'resumo longo' => [['resumo' => str_repeat('palavra ', 150)], 'resumo'],
    'poucas palavras-chave' => [['palavras_chave' => 'uma, duas'], 'palavras_chave'],
    'muitas palavras-chave' => [['palavras_chave' => 'a, b, c, d, e, f'], 'palavras_chave'],
    'tipo inexistente' => [['tipo_trabalho' => 'livro'], 'tipo_trabalho'],
    'sem autores' => [['autores' => []], 'autores'],
]);

it('valida regras dos autores', function (array $autores, string $campo) {
    $this->actingAs($this->autor)->post($this->url, dadosValidos(['autores' => $autores]))->assertSessionHasErrors($campo);
})->with([
    'CPF inválido' => [[['nome_completo' => 'A', 'cpf' => '111.444.777-36', 'email' => 'a@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => 1]], 'autores.0.cpf'],
    'sem autor principal' => [[['nome_completo' => 'A', 'cpf' => '111.444.777-35', 'email' => 'a@x.com', 'tipo_autor' => 'coautor', 'apresentador' => 1]], 'autores'],
    'dois autores principais' => [[
        ['nome_completo' => 'A', 'cpf' => '111.444.777-35', 'email' => 'a@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => 1],
        ['nome_completo' => 'B', 'cpf' => '529.982.247-25', 'email' => 'b@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => 0],
    ], 'autores'],
    'sem apresentador' => [[['nome_completo' => 'A', 'cpf' => '111.444.777-35', 'email' => 'a@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => 0]], 'autores'],
    'CPF repetido' => [[
        ['nome_completo' => 'A', 'cpf' => '111.444.777-35', 'email' => 'a@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => 1],
        ['nome_completo' => 'B', 'cpf' => '11144477735', 'email' => 'b@x.com', 'tipo_autor' => 'coautor', 'apresentador' => 0],
    ], 'autores'],
    'e-mail inválido' => [[['nome_completo' => 'A', 'cpf' => '111.444.777-35', 'email' => 'invalido', 'tipo_autor' => 'autor_principal', 'apresentador' => 1]], 'autores.0.email'],
    'ORCID inválido' => [[['nome_completo' => 'A', 'cpf' => '111.444.777-35', 'email' => 'a@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => 1, 'orcid' => '123']], 'autores.0.orcid'],
]);

it('respeita o limite de autores (orientadores não contam)', function () {
    $cpfs = ['111.444.777-35', '529.982.247-25', '168.995.350-09', '935.411.347-80'];
    $quatro = collect($cpfs)->map(fn ($cpf, $i) => [
        'nome_completo' => "Autor {$i}", 'cpf' => $cpf, 'email' => "a{$i}@x.com",
        'tipo_autor' => $i === 0 ? 'autor_principal' : 'coautor', 'apresentador' => $i === 0 ? 1 : 0,
    ])->all();

    $this->actingAs($this->autor)->post($this->url, dadosValidos(['autores' => $quatro]))->assertSessionHasErrors('autores');

    $quatro[3]['tipo_autor'] = 'orientador'; // agora são 3 contáveis + 1 orientador
    $this->actingAs($this->autor)->post($this->url, dadosValidos(['autores' => $quatro]))->assertSessionHasNoErrors();
});

it('padroniza o CPF dos autores e vincula participante existente sem alterá-lo', function () {
    $p = \App\Models\Participante::create(['nome_completo' => 'Nome Original', 'cpf' => '111.444.777-35', 'email' => 'orig@x.com', 'tipo_vinculo' => 'externo']);

    $autores = autoresValidos();
    $autores[0]['cpf'] = '11144477735';
    $autores[0]['nome_completo'] = 'Nome Diferente';

    $this->actingAs($this->autor)->post($this->url, dadosValidos(['autores' => $autores]))->assertSessionHasNoErrors();

    $a = Trabalho::first()->autores->first();
    expect($a->cpf)->toBe('111.444.777-35')->and($a->participante_id)->toBe($p->id)
        ->and($p->fresh()->nome_completo)->toBe('Nome Original');
});

// ---------- Chamada ----------
it('bloqueia nova submissão com chamada inativa, não iniciada ou encerrada', function (array $config) {
    $this->config->update($config);

    $this->actingAs($this->autor)->post($this->url, dadosValidos())->assertRedirect(route('evento.publico.show', $this->evento->slug));
    $this->actingAs($this->autor)->get(route('trabalhos.create', $this->evento->slug))->assertRedirect();
    expect(Trabalho::count())->toBe(0);
})->with([
    'inativa' => [['ativo' => false]],
    'ainda não começou' => [['submissao_inicio' => now()->addDay(), 'submissao_fim' => now()->addDays(5)]],
    'encerrada' => [['submissao_inicio' => now()->subDays(5), 'submissao_fim' => now()->subMinute()]],
]);

it('respeita o limite de trabalhos por usuário', function () {
    $this->config->update(['max_trabalhos_por_usuario' => 1]);

    $this->actingAs($this->autor)->post($this->url, dadosValidos())->assertSessionHasNoErrors();
    $this->actingAs($this->autor)->post($this->url, dadosValidos(['titulo' => 'Outro']))->assertRedirect(route('evento.publico.show', $this->evento->slug));

    expect(Trabalho::count())->toBe(1);
});

it('exige login para submeter', function () {
    $this->get(route('trabalhos.create', $this->evento->slug))->assertRedirect(route('login'));
    $this->post($this->url, dadosValidos())->assertRedirect(route('login'));
});

// ---------- Edição e ciclo de vida ----------
it('permite editar rascunho e substituir o PDF gerando nova versão', function () {
    $this->actingAs($this->autor)->post($this->url, dadosValidos(['acao' => 'rascunho']))->assertSessionHasNoErrors();
    $t = Trabalho::first();

    $this->actingAs($this->autor)->put(route('trabalhos.update', $t), dadosValidos(['acao' => 'rascunho', 'titulo' => 'Título novo', 'arquivo' => pdfValido('v2.pdf')]))
        ->assertSessionHasNoErrors();

    $t->refresh();
    expect($t->titulo)->toBe('Título novo')
        ->and($t->arquivos()->count())->toBe(2)
        ->and($t->arquivoAtual->versao)->toBe(2)
        ->and($t->arquivos()->where('ativo', true)->count())->toBe(1);
});

it('não permite editar depois de submetido, mas permite quando a gestão pede correção', function () {
    $this->actingAs($this->autor)->post($this->url, dadosValidos())->assertSessionHasNoErrors();
    $t = Trabalho::first();

    $this->actingAs($this->autor)->get(route('trabalhos.edit', $t))->assertForbidden();
    $this->actingAs($this->autor)->put(route('trabalhos.update', $t), dadosValidos(['acao' => 'rascunho']))->assertForbidden();

    $t->update(['status' => 'aguardando_correcao']);
    $this->config->update(['submissao_fim' => now()->subDay()]); // mesmo com a chamada encerrada

    $this->actingAs($this->autor)->get(route('trabalhos.edit', $t))->assertOk();
    $this->actingAs($this->autor)->put(route('trabalhos.update', $t), dadosValidos(['arquivo' => pdfValido('corrigido.pdf')]))->assertSessionHasNoErrors();

    $t->refresh();
    expect($t->status)->toBe('resubmetido')->and($t->arquivoAtual->tipo)->toBe('corrigido');
});

it('permite retirar o próprio trabalho', function () {
    $this->actingAs($this->autor)->post($this->url, dadosValidos())->assertSessionHasNoErrors();
    $t = Trabalho::first();

    $this->actingAs($this->autor)->post(route('trabalhos.retirar', $t))->assertRedirect(route('trabalhos.index'));
    expect($t->fresh()->status)->toBe('retirado');
});

// ---------- Autorização ----------
it('outro usuário não vê, baixa, edita nem retira o trabalho', function () {
    $this->actingAs($this->autor)->post($this->url, dadosValidos())->assertSessionHasNoErrors();
    $t = Trabalho::first();
    $intruso = usuario();

    $this->actingAs($intruso);
    $this->get(route('trabalhos.show', $t))->assertForbidden();
    $this->get(route('trabalhos.arquivo', $t))->assertForbidden();
    $this->get(route('trabalhos.edit', $t))->assertForbidden();
    $this->post(route('trabalhos.retirar', $t))->assertForbidden();
    $this->get(route('gestao.trabalhos.show', $t))->assertForbidden();
});

it('autor baixa o próprio PDF com cabeçalhos seguros', function () {
    $this->actingAs($this->autor)->post($this->url, dadosValidos())->assertSessionHasNoErrors();

    $this->actingAs($this->autor)->get(route('trabalhos.arquivo', Trabalho::first()))
        ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'application/pdf');
});

it('lista Meus Trabalhos somente com os do usuário', function () {
    $this->actingAs($this->autor)->post($this->url, dadosValidos(['titulo' => 'Meu trabalho']));
    $outro = usuario();
    $this->actingAs($outro)->post($this->url, dadosValidos(['titulo' => 'Trabalho alheio']));

    $this->actingAs($this->autor)->get(route('trabalhos.index'))->assertOk()->assertSee('Meu trabalho')->assertDontSee('Trabalho alheio');
});

// ---------- Gestão ----------
function tornarGestor(User $u, Evento $e, string $papel = 'gestor_trabalhos'): void
{
    DB::table('evento_user')->insert(['evento_id' => $e->id, 'user_id' => $u->id, 'role' => $papel, 'created_at' => now(), 'updated_at' => now()]);
}

it('gestor de trabalhos vê a fila, abre trabalho e baixa o PDF; rascunho fica oculto', function () {
    $this->actingAs($this->autor)->post($this->url, dadosValidos(['titulo' => 'Enviado']));
    $this->actingAs($this->autor)->post($this->url, dadosValidos(['acao' => 'rascunho', 'titulo' => 'Meu rascunho secreto']));
    $gestor = usuario();
    tornarGestor($gestor, $this->evento);

    $enviado = Trabalho::where('titulo', 'Enviado')->first();
    $rascunho = Trabalho::where('titulo', 'Meu rascunho secreto')->first();

    $this->actingAs($gestor);
    $this->get(route('gestao.trabalhos.index', $this->evento))->assertOk()->assertSee('Enviado')->assertDontSee('Meu rascunho secreto');
    $this->get(route('gestao.trabalhos.show', $enviado))->assertOk();
    $this->get(route('trabalhos.arquivo', $enviado))->assertOk();
    $this->get(route('gestao.trabalhos.show', $rascunho))->assertNotFound();
});

it('gestor de um evento não acessa trabalhos de outro evento', function () {
    $this->actingAs($this->autor)->post($this->url, dadosValidos());
    $outroEvento = Evento::create(['titulo' => 'Outro', 'slug' => 'o-' . uniqid(), 'data_inicio' => now(), 'data_fim' => now()->addDay(), 'local' => 'Y', 'criado_por' => $this->dono->id]);
    $gestor = usuario();
    tornarGestor($gestor, $outroEvento);

    $this->actingAs($gestor)->get(route('gestao.trabalhos.index', $this->evento))->assertForbidden();
    $this->actingAs($gestor)->get(route('gestao.trabalhos.show', Trabalho::first()))->assertForbidden();
});

it('avaliador sem papel de gestão não acessa a fila nem a configuração', function () {
    $av = usuario();
    tornarGestor($av, $this->evento, 'avaliador');

    $this->actingAs($av)->get(route('gestao.trabalhos.index', $this->evento))->assertForbidden();
    $this->actingAs($av)->get(route('gestao.trabalhos.config', $this->evento))->assertForbidden();
});

it('dono e gestor configuram a chamada; admin de outro evento não', function () {
    $dados = [
        'ativo' => 1, 'submissao_inicio' => '2026-11-01T08:00', 'submissao_fim' => '2026-12-01T18:00', 'max_autores' => 5,
        'max_arquivo_mb' => 10, 'resumo_min_palavras' => 50, 'resumo_max_palavras' => 250, 'tipos_permitidos' => ['resumo', 'poster'],
        'avaliadores_por_trabalho' => 2, 'areas' => "Área A\nÁrea B\n\nÁrea A", 'regras_texto' => 'Regras',
    ];

    $this->actingAs($this->dono)->put(route('gestao.trabalhos.config.update', $this->evento), $dados)->assertSessionHasNoErrors();
    $this->config->refresh();
    expect($this->config->max_autores)->toBe(5)->and($this->config->tipos_permitidos)->toBe(['resumo', 'poster'])
        ->and($this->evento->areasTematicas()->pluck('nome')->all())->toBe(['Área A', 'Área B']);

    $gestor = usuario();
    tornarGestor($gestor, $this->evento);
    $this->actingAs($gestor)->put(route('gestao.trabalhos.config.update', $this->evento), array_merge($dados, ['max_autores' => 7]))->assertSessionHasNoErrors();
    expect($this->config->fresh()->max_autores)->toBe(7);

    $this->actingAs(usuario('admin'))->put(route('gestao.trabalhos.config.update', $this->evento), $dados)->assertForbidden();
});

it('valida a configuração da chamada', function () {
    $base = ['max_autores' => 5, 'max_arquivo_mb' => 10, 'resumo_min_palavras' => 50, 'resumo_max_palavras' => 250, 'tipos_permitidos' => ['resumo']];

    $this->actingAs($this->dono)->put(route('gestao.trabalhos.config.update', $this->evento), array_merge($base, ['resumo_max_palavras' => 10]))->assertSessionHasErrors('resumo_max_palavras');
    $this->actingAs($this->dono)->put(route('gestao.trabalhos.config.update', $this->evento), array_merge($base, ['tipos_permitidos' => []]))->assertSessionHasErrors('tipos_permitidos');
    $this->actingAs($this->dono)->put(route('gestao.trabalhos.config.update', $this->evento), array_merge($base, ['submissao_inicio' => '2026-12-02T10:00', 'submissao_fim' => '2026-12-01T10:00']))->assertSessionHasErrors('submissao_fim');
});

it('mostra o botão de submeter na página pública só com chamada aberta', function () {
    $this->get(route('evento.publico.show', $this->evento->slug))->assertOk()->assertSee('Submeter Trabalho');

    $this->config->update(['ativo' => false]);
    $this->get(route('evento.publico.show', $this->evento->slug))->assertOk()->assertDontSee('Submeter Trabalho');
});
