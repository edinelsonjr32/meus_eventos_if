<?php

use App\Mail\AvaliadorAtribuidoMail;
use App\Mail\CertificadoTrabalhoMail;
use App\Services\AvaliacaoService;
use App\Services\CertificadoTrabalhoService;
use App\Services\TrabalhoWorkflow;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);   // 2 avaliadores por trabalho; divergência máxima 3
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    foreach (['av1', 'av2', 'av3'] as $n) {
        $this->$n = mkUser('user', "{$n}@x.com");
        mkPapel($this->$n, $this->evento, 'avaliador');
    }
    $this->autor = mkUser('user', 'autor@x.com');
    $this->w = app(TrabalhoWorkflow::class);
    $this->av = app(AvaliacaoService::class);
    $this->crit = $this->evento->criterios()->pluck('id');
});

/** Trabalho avaliado por av1 (nota 10) e av2 (nota 2): divergência alta. */
function trabalhoDivergente($ctx)
{
    $t = mkTrabalho($ctx->evento, $ctx->autor, ['titulo' => 'Divergente'], 'em_triagem');
    $ctx->w->atribuirAvaliador($t, $ctx->av1, $ctx->gestor);
    $ctx->w->atribuirAvaliador($t, $ctx->av2, $ctx->gestor);
    $ctx->w->transicionar($t->fresh(), $ctx->gestor, 'em_avaliacao');
    foreach ([[$ctx->av1, 10], [$ctx->av2, 2]] as [$u, $nota]) {
        $a = $t->avaliacoes()->where('avaliador_id', $u->id)->first();
        $ctx->av->concluir($a, $u, ['notas' => [$ctx->crit[0] => $nota, $ctx->crit[1] => $nota], 'parecer' => 'Parecer com mais de vinte caracteres.', 'recomendacao' => 'aprovar']);
    }

    return $t->fresh();
}

// ---------- Desempate ----------
it('divergência alta: adiciona avaliador de desempate, reabre a avaliação e a nota passa a ser a média dos três', function () {
    $t = trabalhoDivergente($this);
    expect($t->status)->toBe('avaliado')->and($this->av->divergenciaAlta($t))->toBeTrue()->and($this->av->motivoSemDesempate($t))->toBeNull();

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.desempate', $t), ['avaliador_id' => $this->av3->id])->assertSessionHas('success');

    $t = $t->fresh();
    $a3 = $t->avaliacoes()->where('avaliador_id', $this->av3->id)->first();
    expect($t->status)->toBe('em_avaliacao')->and($a3->desempate)->toBeTrue()->and($a3->status)->toBe('pendente')
        ->and($t->historico->last()->justificativa)->toContain('desempate');
    Mail::assertQueued(AvaliadorAtribuidoMail::class, fn ($m) => $m->hasTo('av3@x.com'));

    // o avaliador de desempate conclui: volta a "avaliado" e a nota é a média de todos
    $this->av->concluir($a3, $this->av3, ['notas' => [$this->crit[0] => 6, $this->crit[1] => 6], 'parecer' => 'Parecer de desempate bem fundamentado.', 'recomendacao' => 'aprovar_com_ressalvas']);
    $t = $t->fresh();
    expect($t->status)->toBe('avaliado')->and($t->nota_final)->toBe(6.0);
});

it('não permite desempate sem divergência, em duplicidade, em status errado, com conflito ou por quem não gere', function () {
    // sem divergência
    $t = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Sem divergência'], 'em_triagem');
    $this->w->atribuirAvaliador($t, $this->av1, $this->gestor);
    $this->w->atribuirAvaliador($t, $this->av2, $this->gestor);
    $this->w->transicionar($t->fresh(), $this->gestor, 'em_avaliacao');
    foreach ([[$this->av1, 8], [$this->av2, 7]] as [$u, $n]) {
        $this->av->concluir($t->avaliacoes()->where('avaliador_id', $u->id)->first(), $u, ['notas' => [$this->crit[0] => $n, $this->crit[1] => $n], 'parecer' => 'Parecer com mais de vinte caracteres.', 'recomendacao' => 'aprovar']);
    }
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.desempate', $t), ['avaliador_id' => $this->av3->id])->assertSessionHas('error');
    expect($this->av->motivoSemDesempate($t->fresh()))->toContain('Não há divergência');

    $d = trabalhoDivergente($this);
    // conflito de interesse: o autor também é avaliador
    mkPapel($this->autor, $this->evento, 'avaliador');
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.desempate', $d), ['avaliador_id' => $this->autor->id])->assertSessionHas('error');
    expect($d->fresh()->status)->toBe('avaliado');   // nada mudou

    // quem já avaliou não pode ser o desempate
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.desempate', $d), ['avaliador_id' => $this->av1->id])->assertSessionHas('error');

    // autorização
    $this->actingAs($this->autor)->post(route('gestao.trabalhos.desempate', $d), ['avaliador_id' => $this->av3->id])->assertForbidden();

    // desempate feito; um segundo é recusado
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.desempate', $d), ['avaliador_id' => $this->av3->id])->assertSessionHas('success');
    $av4 = mkUser('user', 'av4@x.com'); mkPapel($av4, $this->evento, 'avaliador');
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.desempate', $d), ['avaliador_id' => $av4->id])->assertSessionHas('error');
    expect($d->avaliacoes()->where('desempate', true)->count())->toBe(1);

    // status errado (aprovado)
    $d->update(['status' => 'aprovado']);
    expect($this->av->motivoSemDesempate($d->fresh()))->toContain('só pode ser pedido');
});

it('o painel mostra o formulário de desempate só quando cabe e marca o avaliador de desempate', function () {
    $t = trabalhoDivergente($this);
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $t))->assertOk()
        ->assertSee('avaliador de desempate', false)->assertSee(route('gestao.trabalhos.desempate', $t), false)->assertSee('Adicionar avaliador de desempate');

    $this->av->atribuirDesempate($t, $this->av3, $this->gestor);
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $t->fresh()))->assertOk()
        ->assertDontSee(route('gestao.trabalhos.desempate', $t), false)->assertSee('já atribuído: aguardando a avaliação')->assertSee('Desempate');
    $this->actingAs($this->av3)->get(route('avaliador.avaliacoes.index'))->assertOk()->assertSee('Desempate');
});

// ---------- Aguardando emissão de certificado / Concluído ----------
function trabalhoPronto($ctx, string $titulo = 'Pronto', bool $apresentado = true)
{
    $t = mkTrabalho($ctx->evento, $ctx->autor, ['titulo' => $titulo, 'autores' => [
        ['nome_completo' => 'Ana Autora', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => true],
        ['nome_completo' => 'Caio Coautor', 'cpf' => '529.982.247-25', 'email' => 'caio@x.com', 'tipo_autor' => 'coautor'],
    ]], 'aprovado');
    $t->update(['apresentado' => $apresentado, 'apresentado_em' => $apresentado ? now() : null]);

    return $t->fresh();
}

it('aprovado e apresentado fica "Aguardando emissão de certificado"; ao emitir vira "Concluído"', function () {
    $t = trabalhoPronto($this, 'Fluxo', false);
    expect($t->statusFinal())->toBe('aprovado')->and($t->extraSituacao())->toBe('aguardando apresentação');   // ainda não apresentou

    $t->update(['apresentado' => true]);
    expect($t->fresh()->statusFinal())->toBe('aguardando_certificado')->and($t->fresh()->statusFinalRotulo())->toBe('Aguardando emissão de certificado')->and($t->fresh()->aprovadoCompleto())->toBeTrue();

    app(CertificadoTrabalhoService::class)->emitir($t->fresh(), $this->gestor);
    expect($t->fresh()->statusFinal())->toBe('concluido')->and($t->fresh()->statusFinalRotulo())->toContain('Concluído')->and($t->fresh()->aprovadoCompleto())->toBeTrue();

    // revogados todos os certificados, volta a aguardar emissão
    $t->certificados()->update(['revogado_em' => now(), 'revogado_motivo' => 'teste']);
    expect($t->fresh()->statusFinal())->toBe('aguardando_certificado');
});

it('sem exigir apresentação para o certificado, aprovado completo já aguarda emissão', function () {
    $this->evento->trabalhoConfig->update(['exige_apresentacao_para_certificado' => false]);
    $t = trabalhoPronto($this, 'Sem exigir', false);
    expect($t->statusFinal())->toBe('aguardando_certificado');
});

it('com avaliação de apresentação ativa, só aguarda certificado quem passou nas duas partes e apresentou', function () {
    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => true]);
    $t = trabalhoPronto($this, 'Duas partes');
    expect($t->statusFinal())->toBe('aprovado_escrita');
    $t->update(['resultado_apresentacao' => 'aprovado']);
    expect($t->fresh()->statusFinal())->toBe('aguardando_certificado');
});

it('lote: emite os certificados dos que aguardam e explica os demais; cada um vira Concluído', function () {
    $p1 = trabalhoPronto($this, 'Pronto 1');
    $p2 = trabalhoPronto($this, 'Pronto 2');
    $naoApres = trabalhoPronto($this, 'Não apresentou', false);
    $jaFeito = trabalhoPronto($this, 'Já emitido');
    app(CertificadoTrabalhoService::class)->emitir($jaFeito, $this->gestor);
    $emAval = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Em avaliação'], 'em_avaliacao');
    Mail::fake();

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.lote', $this->evento), [
        'acao' => 'certificados', 'trabalhos' => [$p1->id, $p2->id, $naoApres->id, $jaFeito->id, $emAval->id],
    ])->assertSessionHas('success');

    expect($p1->fresh()->statusFinal())->toBe('concluido')->and($p2->fresh()->statusFinal())->toBe('concluido')
        ->and($p1->certificados()->count())->toBe(2)->and($naoApres->fresh()->statusFinal())->toBe('aprovado');
    Mail::assertQueued(CertificadoTrabalhoMail::class, 4);   // 2 trabalhos × 2 autores com e-mail
    $linhas = collect(session('erros_lote'));
    expect($linhas->contains(fn ($l) => str_contains($l, 'Certificados já emitidos') && str_contains($l, $jaFeito->codigo)))->toBeTrue()
        ->and($linhas->contains(fn ($l) => str_contains($l, 'precisa estar aguardando emissão') && str_contains($l, $naoApres->codigo)))->toBeTrue()
        ->and($linhas->contains(fn ($l) => str_contains($l, 'precisa estar aguardando emissão') && str_contains($l, $emAval->codigo)))->toBeTrue();
});

it('lote de certificados sem nenhum apto devolve erro e nada é emitido; só quem gere usa', function () {
    $t = trabalhoPronto($this, 'Não apresentou', false);
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.lote', $this->evento), ['acao' => 'certificados', 'trabalhos' => [$t->id]])->assertSessionHas('error');
    expect($t->certificados()->count())->toBe(0);

    $this->actingAs($this->autor)->post(route('gestao.trabalhos.lote', $this->evento), ['acao' => 'certificados', 'trabalhos' => [$t->id]])->assertForbidden();
});

it('fila: filtro e contadores por "Aguardando emissão" e "Concluído", com a ação de lote e atributos por linha', function () {
    $ag = trabalhoPronto($this, 'Aguardando');
    $concl = trabalhoPronto($this, 'Concluído');
    app(CertificadoTrabalhoService::class)->emitir($concl, $this->gestor);

    $html = $this->actingAs($this->gestor)->get(route('gestao.trabalhos.index', $this->evento))->assertOk()
        ->assertSee('Emitir certificados')->assertSee('Aguardando emissão de certificado')->assertSee('Concluído (certificados emitidos)')->getContent();
    preg_match('/value="' . $ag->id . '"[^>]*data-certificados="(\d)"/', $html, $a);
    preg_match('/value="' . $concl->id . '"[^>]*data-certificados="(\d)" data-motivo-certificados="([^"]*)"/', $html, $c);
    expect($a[1])->toBe('1')->and($c[1])->toBe('0')->and($c[2])->toBe('certificados já emitidos');

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.index', [$this->evento, 'status' => 'aguardando_certificado']))->assertOk()->assertSee('Aguardando')->assertDontSee('Concluído</div>', false);
    $r = $this->actingAs($this->gestor)->get(route('gestao.trabalhos.index', [$this->evento, 'status' => 'concluido']))->assertOk()->getContent();
    expect($r)->toContain($concl->codigo)->not->toContain($ag->codigo);
});

it('o autor vê a situação nova e a mensagem de aprovação completa', function () {
    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => true]);
    $t = trabalhoPronto($this, 'Autor vê');
    $t->update(['resultado_apresentacao' => 'aprovado']);

    $this->actingAs($this->autor)->get(route('trabalhos.show', $t))->assertOk()->assertSee('Aguardando emissão de certificado')->assertSee('Aprovado (parte escrita e apresentação)');
    app(CertificadoTrabalhoService::class)->emitir($t->fresh(), $this->gestor);
    $this->actingAs($this->autor)->get(route('trabalhos.show', $t))->assertSee('Concluído (certificados emitidos)')->assertSee('Aprovado (parte escrita e apresentação)');
});
