<?php

use App\Exports\NotasExport;
use App\Mail\TrabalhoReenviadoMail;
use App\Mail\TrabalhoSubmetidoMail;
use App\Models\CertificadoTrabalho;
use App\Models\Participante;
use App\Models\Trabalho;
use App\Models\TrabalhoAvaliacao;
use App\Models\TrabalhoConfig;
use App\Models\User;
use App\Services\CertificadoTrabalhoService;
use App\Services\TrabalhoWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/** Dados completos de um trabalho, como enviados pelo formulário da gestão. */
function gaDados(array $extra = []): array
{
    return array_merge([
        'titulo' => 'Trabalho cadastrado pela gestão',
        'tipo_trabalho' => 'pesquisa',
        'modalidade_apresentacao' => 'poster',
        'resumo' => str_repeat('palavra ', 120),
        'palavras_chave' => 'água, clima, Amazônia',
        'autores' => [gaAutor()],
    ], $extra);
}

function gaAutor(array $extra = []): array
{
    return array_merge([
        'nome_completo' => 'Ana Apresentadora', 'cpf' => '111.444.777-35', 'email' => 'ana@ifpa.edu.br', 'instituicao' => 'IFPA Campus Óbidos',
        'categoria_autor' => 'discente', 'tipo_autor' => 'autor_principal', 'apresentador' => 1, 'correspondente' => 1,
    ], $extra);
}

function gaPdf(string $nome = 'novo.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nome, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

/** Avaliação concluída com notas por critério (os 2 critérios criados por mkEvento). */
function gaAvaliacao(Trabalho $t, User $avaliador, array $notasPorCriterio, string $recomendacao = 'aprovar', int $rodada = 1): TrabalhoAvaliacao
{
    $criterios = $t->evento->criterios()->get();
    $av = $t->avaliacoes()->create([
        'avaliador_id' => $avaliador->id, 'rodada' => $rodada, 'status' => 'concluida', 'recomendacao' => $recomendacao,
        'parecer' => 'Parecer público do avaliador', 'parecer_confidencial' => 'Comentário confidencial da comissão', 'concluida_em' => now(),
    ]);
    $soma = 0;
    $pesos = 0;
    foreach ($criterios as $i => $c) {
        $av->notas()->create(['criterio_id' => $c->id, 'nota' => $notasPorCriterio[$i]]);
        $soma += ($notasPorCriterio[$i] / $c->nota_max) * $c->peso;
        $pesos += $c->peso;
    }
    $av->update(['nota_final' => round($soma / $pesos * 10, 2)]);

    return $av;
}

beforeEach(function () {
    Mail::fake();
    Storage::fake('trabalhos');

    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->autorUser = mkUser();
    $this->w = app(TrabalhoWorkflow::class);
    $this->certificados = app(CertificadoTrabalhoService::class);
});

// =====================================================================
// 1) Gestão corrige dados e PDF; autores preservados (certificados intactos)
// =====================================================================
function gaTrabalhoComCertificados($ctx): Trabalho
{
    $t = mkTrabalho($ctx->evento, $ctx->autorUser, ['autores' => [
        gaAutor(['email' => 'ana@ifpa.edu.br']),
        ['nome_completo' => 'Caio Coautor', 'cpf' => '529.982.247-25', 'email' => 'caio@x.com', 'instituicao' => 'UFOPA', 'tipo_autor' => 'coautor'],
    ]], 'aprovado');
    $t->update(['apresentado' => true, 'apresentado_em' => now()]);
    $ctx->certificados->emitir($t->fresh(), $ctx->gestor);

    return $t->fresh(['autores', 'certificados']);
}

it('editar nome de autor pela gestão preserva o autor e o certificado já emitido', function () {
    $t = gaTrabalhoComCertificados($this);
    $ana = $t->autores->firstWhere('nome_completo', 'Ana Apresentadora');
    $hashAntes = $t->certificados->firstWhere('trabalho_autor_id', $ana->id)->hash_validacao;
    $caio = $t->autores->firstWhere('nome_completo', 'Caio Coautor');

    $this->actingAs($this->gestor)->put(route('gestao.trabalhos.atualizar', $t), gaDados([
        'titulo' => $t->titulo,
        'autores' => [
            gaAutor(['id' => $ana->id, 'nome_completo' => 'Ana Beatriz Apresentadora']),                  // nome corrigido
            ['id' => $caio->id, 'nome_completo' => 'Caio Coautor', 'cpf' => '529.982.247-25', 'email' => 'caio@x.com', 'instituicao' => 'UFOPA', 'tipo_autor' => 'coautor'],
        ],
    ]))->assertSessionHasNoErrors()->assertSessionHas('success');

    $t->refresh()->load(['autores', 'certificados']);
    expect($t->autores->pluck('id')->sort()->values()->all())->toBe(collect([$ana->id, $caio->id])->sort()->values()->all())
        ->and($t->certificados)->toHaveCount(2)
        ->and($t->certificados->firstWhere('trabalho_autor_id', $ana->id)->hash_validacao)->toBe($hashAntes);

    // o mesmo certificado passa a sair com o nome corrigido
    $cert = $t->certificados->firstWhere('trabalho_autor_id', $ana->id);
    expect($this->certificados->textos($cert->fresh())['corpo'])->toContain('ANA BEATRIZ APRESENTADORA')->not->toContain('ANA APRESENTADORA');
    $this->get(route('certificado.trabalho.validar', $hashAntes))->assertOk()->assertSee('Ana Beatriz Apresentadora');
});

it('sem o id do autor (formulário antigo), casa pelo CPF e não recria o autor', function () {
    $t = gaTrabalhoComCertificados($this);
    $idsAntes = $t->autores->pluck('id', 'cpf');

    $this->actingAs($this->gestor)->put(route('gestao.trabalhos.atualizar', $t), gaDados([
        'titulo' => $t->titulo,
        'autores' => [gaAutor(['nome_completo' => 'Ana Corrigida']), ['nome_completo' => 'Caio Coautor', 'cpf' => '529.982.247-25', 'instituicao' => 'UFOPA', 'tipo_autor' => 'coautor']],
    ]))->assertSessionHasNoErrors();

    $t->refresh()->load(['autores', 'certificados']);
    expect($t->autores->pluck('id', 'cpf')->all())->toBe($idsAntes->all())->and($t->certificados)->toHaveCount(2);
});

it('não deixa remover autor com certificado válido; depois de revogar, permite', function () {
    $t = gaTrabalhoComCertificados($this);
    $ana = $t->autores->firstWhere('nome_completo', 'Ana Apresentadora');
    $caio = $t->autores->firstWhere('nome_completo', 'Caio Coautor');
    $semCaio = gaDados(['titulo' => $t->titulo, 'autores' => [gaAutor(['id' => $ana->id])]]);
    $this->actingAs($this->gestor);

    $this->put(route('gestao.trabalhos.atualizar', $t), $semCaio)->assertSessionHas('error', fn ($m) => str_contains($m, 'Caio Coautor') && str_contains($m, 'Revogue'));
    expect($t->fresh()->autores)->toHaveCount(2)->and(CertificadoTrabalho::count())->toBe(2);

    $this->certificados->revogar($t->certificados->firstWhere('trabalho_autor_id', $caio->id), $this->gestor, 'Autor retirado');
    $this->put(route('gestao.trabalhos.atualizar', $t), $semCaio)->assertSessionHasNoErrors()->assertSessionHas('success');
    expect($t->fresh()->autores)->toHaveCount(1);
});

it('adiciona e reordena autores pela gestão; id de outro trabalho é ignorado', function () {
    $t = gaTrabalhoComCertificados($this);
    $outro = mkTrabalho($this->evento, $this->autorUser, [], 'aprovado');
    $idAlheio = $outro->autores->first()->id;
    $ana = $t->autores->firstWhere('nome_completo', 'Ana Apresentadora');
    $caio = $t->autores->firstWhere('nome_completo', 'Caio Coautor');

    $this->actingAs($this->gestor)->put(route('gestao.trabalhos.atualizar', $t), gaDados([
        'titulo' => $t->titulo,
        'autores' => [
            gaAutor(['id' => $ana->id]),
            ['id' => $caio->id, 'nome_completo' => 'Caio Coautor', 'cpf' => '529.982.247-25', 'instituicao' => 'UFOPA', 'tipo_autor' => 'coautor'],
            ['id' => $idAlheio, 'nome_completo' => 'Novo Autor', 'cpf' => '168.995.350-09', 'instituicao' => 'UEPA', 'tipo_autor' => 'coautor'],
        ],
    ]))->assertSessionHasNoErrors();

    $t->refresh()->load('autores');
    expect($t->autores)->toHaveCount(3)->and($t->autores->pluck('nome_completo')->all())->toBe(['Ana Apresentadora', 'Caio Coautor', 'Novo Autor'])
        ->and($outro->fresh()->autores)->toHaveCount(1);   // o trabalho alheio não foi tocado
});

// ---------- Edição pela gestão ----------
it('a gestão edita título, categoria e dados sem mudar o status, com registro interno', function () {
    $t = mkTrabalho($this->evento, $this->autorUser, [], 'em_avaliacao');

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.editar', $t))->assertOk()
        ->assertSee('Editar dados do trabalho (gestão)')->assertSee('certificados já emitidos passam a refletir', false)
        ->assertDontSee('Declaro que o trabalho é inédito')->assertSee('Salvar alterações');

    $this->put(route('gestao.trabalhos.atualizar', $t), gaDados(['titulo' => 'Título corrigido', 'tipo_trabalho' => 'extensao', 'autores' => [gaAutor(['id' => $t->autores->first()->id])]]))
        ->assertSessionHasNoErrors();

    $t->refresh();
    expect($t->titulo)->toBe('Título corrigido')->and($t->tipo_trabalho)->toBe('extensao')->and($t->status)->toBe('em_avaliacao');
    $ultimo = $t->historico()->reorder('id', 'desc')->first();
    expect($ultimo->de)->toBe($ultimo->para)->and($ultimo->justificativa)->toBe('Dados editados pela gestão')->and($ultimo->usuario_id)->toBe($this->gestor->id);
});

it('a gestão substitui o PDF criando nova versão ativa; sem arquivo, mantém o atual', function () {
    $t = mkTrabalho($this->evento, $this->autorUser, [], 'em_avaliacao');
    $url = route('gestao.trabalhos.atualizar', $t);
    $dados = fn (array $e = []) => gaDados(['autores' => [gaAutor(['id' => $t->autores->first()->id])]] + $e);
    $this->actingAs($this->gestor);

    $this->put($url, $dados())->assertSessionHasNoErrors();
    expect($t->fresh()->arquivos()->count())->toBe(0);

    $this->put($url, $dados() + ['arquivo' => gaPdf('v1.pdf')])->assertSessionHasNoErrors();
    $this->put($url, $dados() + ['arquivo' => gaPdf('v2.pdf')])->assertSessionHasNoErrors();

    $t->refresh();
    expect($t->arquivos()->count())->toBe(2)->and($t->arquivoAtual->versao)->toBe(2)->and($t->arquivos()->where('ativo', true)->count())->toBe(1)
        ->and($t->arquivoAtual->enviado_por)->toBe($this->gestor->id)
        ->and($t->historico()->reorder('id', 'desc')->first()->justificativa)->toBe('Dados editados pela gestão (PDF substituído)');

    $this->put($url, $dados() + ['arquivo' => UploadedFile::fake()->createWithContent('x.pdf', 'não é pdf')])->assertSessionHasErrors('arquivo');
});

it('a edição da gestão mantém as regras de completude e não exige declarações', function (array $alteracao, string $campo) {
    $t = mkTrabalho($this->evento, $this->autorUser, [], 'em_triagem');

    $this->actingAs($this->gestor)->put(route('gestao.trabalhos.atualizar', $t), gaDados($alteracao + ['autores' => [gaAutor(['id' => $t->autores->first()->id])]]))->assertSessionHasErrors($campo);
})->with([
    'sem título' => [['titulo' => ''], 'titulo'],
    'sem categoria' => [['tipo_trabalho' => ''], 'tipo_trabalho'],
    'categoria inválida' => [['tipo_trabalho' => 'livro'], 'tipo_trabalho'],
    'sem modalidade' => [['modalidade_apresentacao' => ''], 'modalidade_apresentacao'],
    'sem resumo (exigido)' => [['resumo' => ''], 'resumo'],
]);

it('rascunhos e trabalhos retirados não podem ser editados pela gestão', function () {
    $rascunho = $this->w->salvar($this->evento, $this->autorUser, ['titulo' => 'Rascunho', 'autores' => []]);
    $retirado = mkTrabalho($this->evento, $this->autorUser, [], 'retirado');
    $this->actingAs($this->gestor);

    $this->get(route('gestao.trabalhos.editar', $rascunho))->assertRedirect(route('gestao.trabalhos.index', $this->evento))->assertSessionHas('error');
    $this->get(route('gestao.trabalhos.editar', $retirado))->assertRedirect()->assertSessionHas('error');
    $this->put(route('gestao.trabalhos.atualizar', $retirado), gaDados())->assertNotFound();
});

it('o botão Editar dados aparece só para a gestão', function () {
    $t = mkTrabalho($this->evento, $this->autorUser, [], 'em_triagem');

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $t))->assertOk()->assertSee('Editar dados');
    $this->actingAs($this->autorUser)->get(route('trabalhos.show', $t))->assertOk()->assertDontSee('Editar dados');
});

// ---------- Cadastro pela gestão ----------
it('a gestão cadastra um trabalho em nome do autor, que entra como submetido com protocolo', function () {
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.novo', $this->evento))->assertOk()
        ->assertSee('Cadastrar trabalho em nome dos autores')->assertSee('E-mail da conta do autor responsável')->assertDontSee('Declaro que o trabalho é inédito');

    $this->post(route('gestao.trabalhos.criar', $this->evento), gaDados(['responsavel_email' => $this->autorUser->email, 'arquivo' => gaPdf()]))
        ->assertSessionHasNoErrors()->assertRedirect();

    $t = Trabalho::with('autores')->firstOrFail();
    expect($t->status)->toBe('submetido')->and($t->codigo)->toBe("TRB-{$this->evento->id}-0001")->and($t->submetido_por)->toBe($this->autorUser->id)
        ->and($t->autores)->toHaveCount(1)->and($t->arquivoAtual)->not->toBeNull()
        ->and($t->historico()->reorder('id', 'desc')->first()->justificativa)->toBe('Cadastrado pela gestão em nome dos autores');
    Mail::assertQueued(TrabalhoSubmetidoMail::class, fn ($m) => $m->hasTo('ana@ifpa.edu.br'));

    // o autor responsável o enxerga em "Meus Trabalhos"
    $this->actingAs($this->autorUser)->get(route('trabalhos.index'))->assertOk()->assertSee('Trabalho cadastrado pela gestão');
});

it('sem conta do responsável, o trabalho fica em nome do gestor; e-mail desconhecido é recusado', function () {
    $this->actingAs($this->gestor);

    $this->post(route('gestao.trabalhos.criar', $this->evento), gaDados(['responsavel_email' => 'ninguem@nao-existe.com']))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'Não há conta'));
    expect(Trabalho::count())->toBe(0);

    $this->post(route('gestao.trabalhos.criar', $this->evento), gaDados())->assertSessionHasNoErrors();
    expect(Trabalho::first()->submetido_por)->toBe($this->gestor->id);
});

it('o cadastro pela gestão ignora o período e o limite da chamada e não exige PDF', function () {
    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['ativo' => false, 'max_trabalhos_por_usuario' => 1]);
    $this->actingAs($this->gestor);

    foreach ([1, 2] as $n) {
        $this->post(route('gestao.trabalhos.criar', $this->evento), gaDados(['titulo' => "Fora do prazo {$n}", 'responsavel_email' => $this->autorUser->email]))->assertSessionHasNoErrors();
    }

    expect(Trabalho::count())->toBe(2)->and(Trabalho::first()->arquivoAtual)->toBeNull();
});

it('o cadastro pela gestão valida os dados', function (array $alteracao, string $campo) {
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.criar', $this->evento), gaDados($alteracao))->assertSessionHasErrors($campo);
    expect(Trabalho::count())->toBe(0);
})->with([
    'sem título' => [['titulo' => ''], 'titulo'],
    'apresentador sem e-mail' => [['autores' => [gaAutor(['email' => ''])]], 'autores.0.email'],
    'CPF inválido' => [['autores' => [gaAutor(['cpf' => '111.444.777-36'])]], 'autores.0.cpf'],
    'sem autores' => [['autores' => []], 'autores'],
    'e-mail do responsável inválido' => [['responsavel_email' => 'xx'], 'responsavel_email'],
]);

it('somente quem gere trabalhos cadastra, edita ou vê o formulário da gestão', function (string $quem) {
    $t = mkTrabalho($this->evento, $this->autorUser, [], 'em_triagem');
    $usuario = match ($quem) {
        'autor' => $this->autorUser,
        'avaliador' => tap(mkUser(), fn ($u) => mkPapel($u, $this->evento, 'avaliador')),
        'gestor de outro evento' => tap(mkUser(), fn ($u) => mkPapel($u, mkEvento($this->dono), 'gestor_trabalhos')),
        'admin de outro evento' => mkUser('admin'),
    };
    $this->actingAs($usuario);

    $this->get(route('gestao.trabalhos.novo', $this->evento))->assertForbidden();
    $this->post(route('gestao.trabalhos.criar', $this->evento), gaDados())->assertForbidden();
    $this->get(route('gestao.trabalhos.editar', $t))->assertForbidden();
    $this->put(route('gestao.trabalhos.atualizar', $t), gaDados(['titulo' => 'Invasão']))->assertForbidden();

    expect($t->fresh()->titulo)->not->toBe('Invasão')->and(Trabalho::count())->toBe(1);
})->with(['autor', 'avaliador', 'gestor de outro evento', 'admin de outro evento']);

// =====================================================================
// 2) Aviso à gestão quando o autor reenvia
// =====================================================================
function gaReenviar($ctx, Trabalho $t): void
{
    $ctx->actingAs($ctx->autorUser)->put(route('trabalhos.update', $t), [
        'acao' => 'submeter', 'titulo' => $t->titulo, 'tipo_trabalho' => 'pesquisa', 'modalidade_apresentacao' => 'poster',
        'resumo' => str_repeat('palavra ', 120), 'palavras_chave' => 'a, b, c', 'declaracao_ineditismo' => 1, 'declaracao_regulamento' => 1,
        'arquivo' => gaPdf('corrigido.pdf'), 'autores' => [gaAutor(['id' => $t->autores->first()->id, 'email' => $ctx->autorUser->email])],
    ])->assertSessionHasNoErrors();
}

it('reenvio após pedido de correção avisa dono, co-administrador e gestores (e só eles)', function () {
    $coadmin = mkUser('admin');
    mkPapel($coadmin, $this->evento, 'editor');
    $avaliador = mkUser();
    mkPapel($avaliador, $this->evento, 'avaliador');
    $t = mkTrabalho($this->evento, $this->autorUser, [], 'aguardando_correcao');
    Mail::fake();

    gaReenviar($this, $t);

    expect($t->fresh()->status)->toBe('resubmetido');
    Mail::assertQueued(TrabalhoReenviadoMail::class, 3);
    foreach ([$this->dono, $coadmin, $this->gestor] as $u) {
        Mail::assertQueued(TrabalhoReenviadoMail::class, fn ($m) => $m->hasTo($u->email) && $m->statusAnterior === 'aguardando_correcao');
    }
    Mail::assertNotQueued(TrabalhoReenviadoMail::class, fn ($m) => $m->hasTo($avaliador->email) || $m->hasTo($this->autorUser->email));
});

it('reenvio após devolução na triagem também avisa a gestão', function () {
    $t = mkTrabalho($this->evento, $this->autorUser, [], 'devolvido_triagem');
    Mail::fake();

    gaReenviar($this, $t);

    Mail::assertQueued(TrabalhoReenviadoMail::class, fn ($m) => $m->hasTo($this->gestor->email) && $m->statusAnterior === 'devolvido_triagem');
});

it('a primeira submissão não dispara o aviso de reenvio', function () {
    $t = $this->w->salvar($this->evento, $this->autorUser, gaDados(['palavras_chave' => 'a, b, c']));
    Mail::fake();
    $this->w->submeter($t, $this->autorUser);

    Mail::assertNotQueued(TrabalhoReenviadoMail::class);
});

it('o e-mail de reenvio leva ao trabalho na gestão', function () {
    $t = mkTrabalho($this->evento, $this->autorUser, [], 'aguardando_correcao');

    $html = (new TrabalhoReenviadoMail($t->load('evento'), 'aguardando_correcao'))->render();

    expect($html)->toContain($t->codigo)->toContain(route('gestao.trabalhos.show', $t))->toContain('versão corrigida');
});

// =====================================================================
// 3) Coautores com conta veem o trabalho
// =====================================================================
function gaTrabalhoComCoautor($ctx, array $coautor): Trabalho
{
    return mkTrabalho($ctx->evento, $ctx->autorUser, ['autores' => [
        gaAutor(['email' => $ctx->autorUser->email]),
        array_merge(['nome_completo' => 'Caio Coautor', 'instituicao' => 'UFOPA', 'tipo_autor' => 'coautor'], $coautor),
    ]], 'aprovado');
}

it('coautor (por e-mail) vê o trabalho na lista, abre o detalhe e baixa o PDF, sem poder editar nem retirar', function () {
    $coautor = mkUser('user', 'caio@x.com');
    $t = gaTrabalhoComCoautor($this, ['email' => 'caio@x.com']);
    Storage::disk('trabalhos')->put('p.pdf', "%PDF-1.4\n%%EOF");
    $t->arquivos()->create(['versao' => 1, 'tipo' => 'original', 'path' => 'p.pdf', 'nome_original' => 'p.pdf', 'mime' => 'application/pdf', 'tamanho' => 10, 'sha256' => str_repeat('a', 64), 'enviado_por' => $this->autorUser->id, 'ativo' => true]);
    $this->actingAs($coautor);

    $this->get(route('trabalhos.index'))->assertOk()->assertSee($t->titulo)->assertSee('Você consta como autor')->assertDontSee('Responsável pela submissão');
    $this->get(route('trabalhos.show', $t))->assertOk();
    $this->get(route('trabalhos.arquivo', $t))->assertOk();
    $this->get(route('trabalhos.edit', $t))->assertForbidden();
    $this->post(route('trabalhos.retirar', $t))->assertForbidden();
    $this->get(route('gestao.trabalhos.show', $t))->assertForbidden();
});

it('coautor é reconhecido pelo CPF do cadastro de participante', function () {
    $coautor = mkUser('user', 'outro-email@x.com');   // e-mail diferente do informado na submissão
    Participante::create(['user_id' => $coautor->id, 'nome_completo' => 'Caio', 'cpf' => '529.982.247-25', 'email' => $coautor->email, 'tipo_vinculo' => 'externo']);
    $t = gaTrabalhoComCoautor($this, ['cpf' => '529.982.247-25', 'email' => 'caio-antigo@x.com']);

    $this->actingAs($coautor)->get(route('trabalhos.index'))->assertSee($t->titulo);
    $this->get(route('trabalhos.show', $t))->assertOk();
});

it('quem não é autor não vê o trabalho; o responsável continua vendo', function () {
    $t = gaTrabalhoComCoautor($this, ['email' => 'caio@x.com']);

    $this->actingAs(mkUser('user', 'estranho@x.com'))->get(route('trabalhos.index'))->assertDontSee($t->titulo);
    $this->get(route('trabalhos.show', $t))->assertForbidden();
    $this->actingAs($this->autorUser)->get(route('trabalhos.index'))->assertSee($t->titulo)->assertSee('Responsável pela submissão');
});

it('coautor vê apenas o próprio certificado; o responsável vê todos', function () {
    $coautor = mkUser('user', 'caio@x.com');
    $t = gaTrabalhoComCoautor($this, ['email' => 'caio@x.com']);
    $t->update(['apresentado' => true, 'apresentado_em' => now()]);
    $this->certificados->emitir($t->fresh(), $this->gestor);
    $dele = $t->certificados()->whereHas('autor', fn ($a) => $a->where('email', 'caio@x.com'))->first();
    $dela = $t->certificados()->whereHas('autor', fn ($a) => $a->where('email', $this->autorUser->email))->first();

    $this->actingAs($coautor)->get(route('trabalhos.show', $t))->assertOk()
        ->assertSee(route('certificado.trabalho.download', $dele->hash_validacao), false)
        ->assertDontSee(route('certificado.trabalho.download', $dela->hash_validacao), false);

    $this->actingAs($this->autorUser)->get(route('trabalhos.show', $t))->assertOk()
        ->assertSee(route('certificado.trabalho.download', $dele->hash_validacao), false)
        ->assertSee(route('certificado.trabalho.download', $dela->hash_validacao), false);
});

it('trabalhos de coautoria não contam no limite de trabalhos do usuário', function () {
    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['max_trabalhos_por_usuario' => 1]);
    $coautor = mkUser('user', 'caio@x.com');
    gaTrabalhoComCoautor($this, ['email' => 'caio@x.com']);

    $this->actingAs($coautor)->get(route('trabalhos.index'))->assertOk()->assertSee('Submeter trabalho')->assertDontSee('Limite de trabalhos atingido');
    $this->get(route('trabalhos.create', $this->evento->slug))->assertOk();
});

it('o coautor também não vê comentário confidencial nem avaliadores', function () {
    $coautor = mkUser('user', 'caio@x.com');
    $av = mkUser();
    mkPapel($av, $this->evento, 'avaliador');
    $t = gaTrabalhoComCoautor($this, ['email' => 'caio@x.com']);
    gaAvaliacao($t, $av, [8, 8]);
    $t->update(['decisao' => 'aprovado', 'status' => 'aprovado']);

    $this->actingAs($coautor)->get(route('trabalhos.show', $t))->assertOk()->assertDontSee('Comentário confidencial da comissão')->assertDontSee($av->name);
});

// =====================================================================
// 4) Ranking e exportação de notas e pareceres
// =====================================================================
function gaCenarioRanking($ctx): array
{
    $a1 = mkUser(); $a2 = mkUser();
    mkPapel($a1, $ctx->evento, 'avaliador'); mkPapel($a2, $ctx->evento, 'avaliador');
    $criado = [];
    foreach ([['Alfa trabalho', [10, 10], [10, 10]], ['Beta trabalho', [9, 9], [7, 7]], ['Gama trabalho', [8, 8], [8, 8]], ['Delta trabalho', [6, 6], [2, 2]]] as [$titulo, $n1, $n2]) {
        $t = mkTrabalho($ctx->evento, $ctx->autorUser, ['titulo' => $titulo], 'avaliado');
        gaAvaliacao($t, $a1, $n1, 'aprovar');
        gaAvaliacao($t, $a2, $n2, $titulo === 'Delta trabalho' ? 'reprovar' : 'aprovar');
        $criado[$titulo] = $t->fresh();
    }
    $semNota = mkTrabalho($ctx->evento, $ctx->autorUser, ['titulo' => 'Sem nota ainda'], 'em_avaliacao');

    return [$criado, $semNota, $a1, $a2];
}

it('o ranking ordena pela nota média, com empates na mesma posição e sem nota por último', function () {
    [$criado, $semNota] = gaCenarioRanking($this);
    // Gama (8,00) e Beta (8,00 = média de 9 e 7) empatam; Alfa 10; Delta 4
    $r = app(\App\Services\RelatorioNotas::class)->resumo($this->evento);

    expect($r->pluck('trabalho.titulo')->all())->toBe(['Alfa trabalho', 'Beta trabalho', 'Gama trabalho', 'Delta trabalho', 'Sem nota ainda'])
        ->and($r->pluck('nota')->all())->toBe([10.0, 8.0, 8.0, 4.0, null])
        ->and($r->pluck('posicao')->all())->toBe([1, 2, 2, 4, null])
        ->and($r[1]['divergencia'])->toBe(2.0)->and($r[0]['concluidas'])->toBe(2);
});

it('exportar com filtro de categoria não confunde categoria com tipo de relatório', function () {
    [$criado] = gaCenarioRanking($this);
    $criado['Delta trabalho']->update(['tipo_trabalho' => 'extensao']);
    $rel = app(\App\Services\RelatorioNotas::class);

    expect($rel->resumo($this->evento, ['tipo' => 'extensao']))->toHaveCount(1)->and($rel->detalhado($this->evento, ['tipo' => 'extensao']))->toHaveCount(2);

    $r = $this->actingAs($this->gestor)->get(route('gestao.trabalhos.exportar-notas', ['evento' => $this->evento, 'relatorio' => 'detalhado', 'formato' => 'csv', 'tipo' => 'extensao']))->assertOk();
    $csv = file_get_contents($r->baseResponse->getFile()->getPathname());
    expect($csv)->toContain('Delta trabalho')->not->toContain('Alfa trabalho');
});

it('a tela de ranking mostra notas, divergência e recomendações, e filtra', function () {
    [$criado] = gaCenarioRanking($this);
    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['divergencia_maxima' => 3]);
    $criado['Delta trabalho']->update(['tipo_trabalho' => 'extensao']);

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.ranking', $this->evento))->assertOk()
        ->assertSee('Notas e ranking')->assertSee('Alfa trabalho')->assertSee('10,00')->assertSee('Reprovar')->assertSee('divergência 4,00')->assertSee('Sem nota ainda');

    $this->get(route('gestao.trabalhos.ranking', [$this->evento, 'tipo' => 'extensao']))->assertSee('Delta trabalho')->assertDontSee('Alfa trabalho');
    $this->get(route('gestao.trabalhos.ranking', [$this->evento, 'status' => 'em_avaliacao']))->assertSee('Sem nota ainda')->assertDontSee('Alfa trabalho');
});

it('o ranking usa só a rodada vigente e ignora recusadas e rascunhos', function () {
    $a1 = mkUser(); $a2 = mkUser();
    mkPapel($a1, $this->evento, 'avaliador'); mkPapel($a2, $this->evento, 'avaliador');
    $t = mkTrabalho($this->evento, $this->autorUser, ['titulo' => 'Duas rodadas'], 'avaliado');
    gaAvaliacao($t, $a1, [2, 2], 'reprovar', 1);                  // rodada 1 (antiga)
    gaAvaliacao($t, $a1, [9, 9], 'aprovar', 2);                   // rodada 2 (vigente)
    $t->avaliacoes()->create(['avaliador_id' => $a2->id, 'rodada' => 2, 'status' => 'recusada', 'parecer_confidencial' => 'Conflito']);
    $this->w->salvar($this->evento, $this->autorUser, ['titulo' => 'Rascunho oculto', 'autores' => []]);

    $r = app(\App\Services\RelatorioNotas::class)->resumo($this->evento);

    expect($r)->toHaveCount(1)->and($r[0]['nota'])->toBe(9.0)->and($r[0]['concluidas'])->toBe(1)->and($r[0]['atribuidas'])->toBe(1)->and($r[0]['rodada'])->toBe(2);
});

it('o detalhamento traz uma linha por avaliação, com notas por critério e pareceres', function () {
    gaCenarioRanking($this);
    $rel = app(\App\Services\RelatorioNotas::class);
    $linhas = $rel->detalhado($this->evento);

    expect($linhas)->toHaveCount(8)   // 4 trabalhos × 2 avaliações
        ->and(array_keys($linhas->first()['por_criterio']))->toBe(['Conteúdo', 'Metodologia'])
        ->and($linhas->first()['avaliacao']->parecer)->toContain('Parecer público');
});

it('exporta o ranking e os pareceres (xlsx e csv) respeitando filtros', function () {
    [$criado] = gaCenarioRanking($this);
    Excel::fake();
    Excel::matchByRegex();
    $this->actingAs($this->gestor);

    $this->get(route('gestao.trabalhos.exportar-notas', ['evento' => $this->evento, 'relatorio' => 'resumo', 'formato' => 'xlsx']))->assertOk();
    Excel::assertDownloaded('/^notas-resumo-.*\.xlsx$/', function (NotasExport $e) {
        $h = $e->headings();
        $linhas = $e->collection();

        return in_array('Posição', $h) && in_array('Média: Conteúdo', $h) && in_array('Média: Metodologia', $h)
            && $linhas->count() === 5 && $linhas->first()[1] !== null && $linhas->first()[8] === 10.0;
    });

    $this->get(route('gestao.trabalhos.exportar-notas', ['evento' => $this->evento, 'relatorio' => 'detalhado', 'formato' => 'csv', 'status' => 'avaliado']))->assertOk();
    Excel::assertDownloaded('/^notas-detalhado-.*\.csv$/', function (NotasExport $e) {
        $h = $e->headings();

        return in_array('Parecer ao autor', $h) && in_array('Conteúdo', $h) && $e->collection()->count() === 8;
    });
});

it('gera o CSV de verdade com cabeçalho, critérios e pareceres', function () {
    gaCenarioRanking($this);

    $r = $this->actingAs($this->gestor)->get(route('gestao.trabalhos.exportar-notas', ['evento' => $this->evento, 'relatorio' => 'detalhado', 'formato' => 'csv']))->assertOk();
    $csv = file_get_contents($r->baseResponse->getFile()->getPathname());

    expect($csv)->toContain('Protocolo')->toContain('Metodologia')->toContain('Parecer público')->toContain('Comentário confidencial da comissão');
});

it('as exportações de notas neutralizam fórmulas', function () {
    $a1 = mkUser();
    mkPapel($a1, $this->evento, 'avaliador');
    $t = mkTrabalho($this->evento, $this->autorUser, ['titulo' => '=HYPERLINK("http://x","y")'], 'avaliado');
    gaAvaliacao($t, $a1, [8, 8]);
    $t->avaliacoes()->first()->update(['parecer' => '@SOMA(A1)']);
    $rel = app(\App\Services\RelatorioNotas::class);

    $resumo = (new NotasExport($rel->resumo($this->evento), $rel->criterios($this->evento), 'resumo'))->collection()->first();
    $detalhe = (new NotasExport($rel->detalhado($this->evento), $rel->criterios($this->evento), 'detalhado'))->collection()->first();

    expect($resumo[2])->toStartWith("'=")->and(collect($detalhe)->contains("'@SOMA(A1)"))->toBeTrue();
});

it('somente a gestão acessa ranking e exportação de notas (dados de avaliação)', function (string $quem) {
    gaCenarioRanking($this);
    $usuario = match ($quem) {
        'autor' => $this->autorUser,
        'avaliador' => tap(mkUser(), fn ($u) => mkPapel($u, $this->evento, 'avaliador')),
        'gestor de outro evento' => tap(mkUser(), fn ($u) => mkPapel($u, mkEvento($this->dono), 'gestor_trabalhos')),
        'admin de outro evento' => mkUser('admin'),
        'visitante' => null,
    };
    $usuario ? $this->actingAs($usuario) : null;
    $esperado = $usuario ? 403 : 302;

    $this->get(route('gestao.trabalhos.ranking', $this->evento))->assertStatus($esperado);
    foreach (['resumo', 'detalhado'] as $tipo) {
        $this->get(route('gestao.trabalhos.exportar-notas', ['evento' => $this->evento, 'relatorio' => $tipo]))->assertStatus($esperado);
    }
})->with(['autor', 'avaliador', 'gestor de outro evento', 'admin de outro evento', 'visitante']);

it('os botões de ranking e de cadastro aparecem na fila da gestão', function () {
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.index', $this->evento))->assertOk()
        ->assertSee('Notas e ranking')->assertSee('Cadastrar trabalho')->assertSee(route('gestao.trabalhos.ranking', $this->evento), false);
});
