<?php

use App\Mail\AutorizacaoPublicacaoMail;
use App\Models\Anais;
use App\Services\CertificadoTrabalhoService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    Storage::fake('trabalhos');
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->evento->update(['slug' => 'conclusao']);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->autor = mkUser('user', 'autor@x.com');
    Anais::create(['evento_id' => $this->evento->id, 'titulo' => 'Anais', 'ano' => 2026, 'status' => 'publicado', 'publicado_em' => now(), 'dados_publicacao_visiveis' => false]);
});

/** Trabalho concluído (aprovado, apresentado, certificados emitidos), com PDF e SEM autorização de publicação. */
function concluidoSemAutorizacao($ctx, string $titulo = 'Concluído sem autorização')
{
    $t = mkTrabalho($ctx->evento, $ctx->autor, ['titulo' => $titulo, 'autoriza_publicacao' => false], 'aprovado');
    $t->update(['apresentado' => true, 'apresentado_em' => now()]);
    Storage::disk('trabalhos')->put("t{$t->id}.pdf", "%PDF-1.4\n%%EOF");
    $t->arquivos()->create(['versao' => 1, 'tipo' => 'original', 'path' => "t{$t->id}.pdf", 'nome_original' => 'x.pdf', 'mime' => 'application/pdf', 'tamanho' => 10, 'sha256' => str_repeat('a', 64), 'enviado_por' => $ctx->autor->id, 'ativo' => true]);
    app(CertificadoTrabalhoService::class)->emitir($t->fresh(), $ctx->gestor);

    return $t->fresh();
}

it('trabalho concluído sem autorização dos autores NÃO aparece nos Anais e a gestão vê o motivo', function () {
    $t = concluidoSemAutorizacao($this);
    expect($t->statusFinal())->toBe('concluido')->and(Anais::elegiveis($this->evento)->count())->toBe(0);

    $this->get(route('anais.index', 'conclusao'))->assertOk()->assertDontSee($t->titulo);
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.anais.edit', $this->evento))->assertOk()
        ->assertSee('Concluídos que não entram nos Anais')->assertSee($t->codigo)->assertSee('Autores não autorizaram a publicação')->assertSee('Solicitar autorização aos autores (1)');
});

it('o autor autoriza depois de aprovado e o trabalho concluído passa a constar nos Anais; revogar o tira', function () {
    $t = concluidoSemAutorizacao($this);

    $this->actingAs($this->autor)->get(route('trabalhos.show', $t))->assertOk()->assertSee('Autorizar publicação');
    $this->post(route('trabalhos.autorizacao-publicacao', $t), ['autoriza' => 1])->assertSessionHas('success');
    expect($t->fresh()->autoriza_publicacao)->toBeTrue()->and($t->fresh()->autorizou_em)->not->toBeNull()
        ->and($t->fresh()->historico->last()->justificativa)->toContain('autorizada pelo autor');

    auth()->logout();
    $this->get(route('anais.index', 'conclusao'))->assertOk()->assertSee('Concluído sem autorização')->assertSee('1 trabalho(s) publicado(s)');
    $this->get(route('anais.show', ['conclusao', $t->codigo]))->assertOk();

    $this->actingAs($this->autor)->post(route('trabalhos.autorizacao-publicacao', $t), ['autoriza' => 0])->assertSessionHas('success');
    expect($t->fresh()->autoriza_publicacao)->toBeFalse();
    auth()->logout();
    $this->get(route('anais.index', 'conclusao'))->assertDontSee('Concluído sem autorização');
});

it('só o autor autoriza: gestão, avaliador e outros usuários são barrados', function () {
    $t = concluidoSemAutorizacao($this);
    foreach ([$this->gestor, $this->dono, mkUser('user', 'estranho@x.com')] as $u) {
        $this->actingAs($u)->post(route('trabalhos.autorizacao-publicacao', $t), ['autoriza' => 1])->assertForbidden();
    }
    expect($t->fresh()->autoriza_publicacao)->toBeFalse();

    // coautor (por e-mail) também pode
    $co = mkUser('user', 'ana@x.com');
    $t2 = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Com coautor', 'autoriza_publicacao' => false, 'autores' => [
        ['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => true],
    ]], 'aprovado');
    $this->actingAs($co)->post(route('trabalhos.autorizacao-publicacao', $t2), ['autoriza' => 1])->assertSessionHas('success');
    expect($t2->fresh()->autoriza_publicacao)->toBeTrue();
});

it('a autorização só pode ser alterada em trabalhos já avaliados (não em rascunho/triagem)', function () {
    $t = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Em triagem'], 'em_triagem');
    $this->actingAs($this->autor)->post(route('trabalhos.autorizacao-publicacao', $t), ['autoriza' => 1])->assertStatus(422);
});

it('a gestão solicita a autorização por e-mail aos autores e não repete o pedido em 24 h', function () {
    $t = concluidoSemAutorizacao($this);
    $autorizado = concluidoSemAutorizacao($this, 'Já autorizado');
    $autorizado->update(['autoriza_publicacao' => true]);
    Mail::fake();

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.anais.solicitar-autorizacao', $this->evento))->assertSessionHas('success');
    Mail::assertQueued(AutorizacaoPublicacaoMail::class, fn ($m) => $m->hasTo('autor@x.com') && $m->trabalho->id === $t->id);
    Mail::assertQueued(AutorizacaoPublicacaoMail::class, 1);
    expect($t->fresh()->autorizacao_solicitada_em)->not->toBeNull()->and($autorizado->fresh()->autorizacao_solicitada_em)->toBeNull();

    $this->post(route('gestao.trabalhos.anais.solicitar-autorizacao', $this->evento))->assertSessionHas('warning');
    Mail::assertQueued(AutorizacaoPublicacaoMail::class, 1);   // nada novo

    $t->update(['autorizacao_solicitada_em' => now()->subDays(2)]);
    $this->post(route('gestao.trabalhos.anais.solicitar-autorizacao', $this->evento))->assertSessionHas('success');
    Mail::assertQueued(AutorizacaoPublicacaoMail::class, 2);

    $this->actingAs($this->autor)->post(route('gestao.trabalhos.anais.solicitar-autorizacao', $this->evento))->assertForbidden();
});

it('com todos autorizados, não há o que solicitar', function () {
    $t = concluidoSemAutorizacao($this);
    $t->update(['autoriza_publicacao' => true]);
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.anais.solicitar-autorizacao', $this->evento))->assertSessionHas('error');
});

it('concluído sem PDF publicável é apontado como "Sem PDF publicável"', function () {
    $t = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Sem PDF', 'autoriza_publicacao' => true], 'aprovado');
    $t->update(['apresentado' => true]);
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.anais.edit', $this->evento))->assertOk()->assertSee($t->codigo)->assertSee('Sem PDF publicável');
});

it('o autor vê o selo "Pendente de aprovação · Anais do evento" (diferente dos demais) enquanto não autoriza', function () {
    $t = concluidoSemAutorizacao($this);

    $this->actingAs($this->autor)->get(route('trabalhos.index'))->assertOk()->assertSee('Pendente de aprovação · Anais do evento');
    $this->get(route('trabalhos.show', $t))->assertOk()->assertSee('Pendente de aprovação · Anais do evento')->assertDontSee('Publicação nos Anais não autorizada');

    $this->post(route('trabalhos.autorizacao-publicacao', $t), ['autoriza' => 1]);
    $this->get(route('trabalhos.index'))->assertDontSee('Pendente de aprovação · Anais do evento');
    $this->get(route('trabalhos.show', $t))->assertSee('Publicação nos Anais autorizada')->assertDontSee('Pendente de aprovação · Anais do evento');

    // fora das fases em que dá para autorizar, não aparece como pendência
    $triagem = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Em triagem', 'autoriza_publicacao' => false], 'em_triagem');
    $this->get(route('trabalhos.show', $triagem))->assertSee('Publicação nos Anais não autorizada')->assertDontSee('Pendente de aprovação · Anais do evento');
});

it('o selo dos Anais tem cor e ícone próprios, distintos dos de status do trabalho', function () {
    $html = fn ($v) => \Illuminate\Support\Facades\Blade::render('<x-badge tipo="anais" :valor="$v" />', ['v' => $v]);
    preg_match('/background: (#\w+);.*?fa-solid (fa-[\w-]+)/s', $html('pendente'), $p);
    foreach (array_keys(\App\Models\Trabalho::STATUS) as $s) {
        preg_match('/background: (#\w+);.*?fa-solid (fa-[\w-]+)/s', \Illuminate\Support\Facades\Blade::render('<x-badge tipo="trabalho" :valor="$v" />', ['v' => $s]), $o);
        expect([$p[1], $p[2]])->not->toBe([$o[1], $o[2]]);
    }
});

// ---------- Autorização pré-marcada no cadastro do trabalho ----------
it('no cadastro do trabalho a autorização de publicação nos Anais já vem marcada (caso aprovado)', function () {
    $html = $this->actingAs($this->autor)->get(route('trabalhos.create', 'conclusao'))->assertOk()->getContent();

    expect($html)->toMatch('/<input class="form-check-input" type="checkbox" name="autoriza_publicacao" value="1" id="d3"\s+checked/')
        ->and($html)->toContain('caso seja aprovado');
});

it('a opção marcada por padrão é gravada; desmarcar grava como não autorizado', function () {
    $this->evento->trabalhoConfig->update(['exige_resumo' => false, 'exige_palavras_chave' => false]);
    $base = ['acao' => 'rascunho', 'titulo' => 'Com autorização padrão', 'tipo_trabalho' => 'pesquisa', 'modalidade_apresentacao' => 'poster',
        'autores' => [['nome_completo' => 'Autor', 'tipo_autor' => 'autor_principal', 'apresentador' => 1]]];

    $this->actingAs($this->autor)->post(route('trabalhos.store', 'conclusao'), $base + ['autoriza_publicacao' => 1]);
    $this->post(route('trabalhos.store', 'conclusao'), array_merge($base, ['titulo' => 'Sem autorização', 'autoriza_publicacao' => 0]));

    expect(\App\Models\Trabalho::where('titulo', 'Com autorização padrão')->first()->autoriza_publicacao)->toBeTrue()
        ->and(\App\Models\Trabalho::where('titulo', 'Sem autorização')->first()->autoriza_publicacao)->toBeFalse();
});

it('na edição vale o que está salvo (não volta a marcar sozinho); na gestão cadastrando por outra pessoa não vem marcado', function () {
    $t = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Não autorizado', 'autoriza_publicacao' => false], 'rascunho');
    $html = $this->actingAs($this->autor)->get(route('trabalhos.edit', $t))->assertOk()->getContent();
    expect($html)->not->toMatch('/name="autoriza_publicacao" value="1" id="d3"\s+checked/');

    $t->update(['autoriza_publicacao' => true]);
    expect($this->get(route('trabalhos.edit', $t))->getContent())->toMatch('/name="autoriza_publicacao" value="1" id="d3"\s+checked/');

    // consentimento é do autor: a gestão cadastrando em nome dele começa desmarcado
    $gestor = mkUser();
    mkPapel($gestor, $this->evento, 'gestor_trabalhos');
    expect($this->actingAs($gestor)->get(route('gestao.trabalhos.novo', $this->evento))->assertOk()->getContent())->not->toMatch('/name="autoriza_publicacao" value="1" id="d3"\s+checked/');
});
