<?php

use App\Models\Atividade;
use App\Models\CriterioApresentacao;
use App\Support\ResultadoLote;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->av1 = mkUser('user', 'av1@x.com');
    $this->av2 = mkUser('user', 'av2@x.com');
    mkPapel($this->av1, $this->evento, 'avaliador');
    mkPapel($this->av2, $this->evento, 'avaliador');
    $this->autor = mkUser('user', 'autor@x.com');
});

it('ResultadoLote agrupa os itens ignorados por motivo e monta as mensagens', function () {
    $r = new ResultadoLote();
    $r->ok(3);
    $r->ignorar('TRB-1', 'Conflito de interesse: Ana é o responsável.');
    $r->ignorar('TRB-2', 'Conflito de interesse: Ana é o responsável');
    $r->ignorar('TRB-3', 'Status inválido');
    $r->ignorar('TRB-3', 'Status inválido');   // repetido não duplica

    expect($r->feitas)->toBe(3)->and($r->totalIgnorados())->toBe(4)
        ->and($r->linhas())->toBe(['Conflito de interesse: Ana é o responsável (2): TRB-1, TRB-2', 'Status inválido (1): TRB-3']);

    $outro = new ResultadoLote();
    $outro->ok();
    $outro->ignorar('TRB-9', 'Status inválido');
    $r->mesclar($outro);
    expect($r->feitas)->toBe(4)->and($r->linhas()[1])->toBe('Status inválido (2): TRB-3, TRB-9');
});

it('tela de trabalhos: o lote usa o componente único e marca o que cada trabalho aceita', function () {
    $emTriagem = mkTrabalho($this->evento, $this->autor, ['titulo' => 'A'], 'em_triagem');
    $submetido = mkTrabalho($this->evento, $this->autor, ['titulo' => 'B'], 'submetido');

    $r = $this->actingAs($this->gestor)->get(route('gestao.trabalhos.index', $this->evento))->assertOk();
    $r->assertSee('Ação em lote')->assertSee('data-lote', false)->assertSee('js-select-avaliadores-lote', false)->assertSee('sel-todos', false)
        ->assertSee('Atribuir avaliadores')->assertSee('Iniciar triagem');
    // em triagem aceita atribuir (não triagem); submetido aceita triagem (não atribuir)
    $html = $r->getContent();
    preg_match('/value="' . $emTriagem->id . '"[^>]*data-atribuir="(\d)" data-triagem="(\d)"/', $html, $a);
    preg_match('/value="' . $submetido->id . '"[^>]*data-atribuir="(\d)" data-triagem="(\d)"/', $html, $b);
    expect([$a[1], $a[2]])->toBe(['1', '0'])->and([$b[1], $b[2]])->toBe(['0', '1']);
});

it('lote de atribuição: resultado padronizado com ignorados agrupados por motivo', function () {
    $t1 = mkTrabalho($this->evento, $this->autor, ['titulo' => 'T1'], 'em_triagem');
    $t2 = mkTrabalho($this->evento, $this->autor, ['titulo' => 'T2'], 'em_triagem');
    $submetido = mkTrabalho($this->evento, $this->autor, ['titulo' => 'T3'], 'submetido');   // status não aceita avaliadores
    mkPapel($this->autor, $this->evento, 'avaliador');                                       // autor avaliador: conflito

    $resp = $this->actingAs($this->gestor)->post(route('gestao.trabalhos.lote', $this->evento), [
        'acao' => 'atribuir', 'trabalhos' => [$t1->id, $t2->id, $submetido->id], 'avaliadores' => [$this->av1->id, $this->av2->id, $this->autor->id],
    ])->assertSessionHas('success');

    expect($t1->avaliacoes()->count())->toBe(2)->and($t2->avaliacoes()->count())->toBe(2)->and($submetido->avaliacoes()->count())->toBe(0);
    $linhas = session('erros_lote');
    expect(collect($linhas)->contains(fn ($l) => str_contains($l, 'Conflito de interesse') && str_contains($l, 'T1') === false && str_contains($l, $t1->codigo) && str_contains($l, $t2->codigo)))->toBeTrue()
        ->and(collect($linhas)->contains(fn ($l) => str_contains($l, 'Só é possível atribuir') && str_contains($l, $submetido->codigo)))->toBeTrue();

    // repetir: tudo vira "já atribuído", nada novo e mensagem de erro
    $this->post(route('gestao.trabalhos.lote', $this->evento), ['acao' => 'atribuir', 'trabalhos' => [$t1->id, $t2->id], 'avaliadores' => [$this->av1->id]])
        ->assertSessionHas('error');
});

it('lote relata ids que não pertencem ao evento em vez de ignorá-los em silêncio', function () {
    $t = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Meu'], 'em_triagem');
    $outroEvento = mkEvento($this->dono);
    $estranho = mkTrabalho($outroEvento, $this->autor, ['titulo' => 'Estranho'], 'em_triagem');

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.lote', $this->evento), [
        'acao' => 'atribuir', 'trabalhos' => [$t->id, $estranho->id], 'avaliadores' => [$this->av1->id],
    ])->assertSessionHas('success');

    expect($estranho->avaliacoes()->count())->toBe(0)
        ->and(collect(session('erros_lote'))->contains(fn ($l) => str_contains($l, 'não encontrado neste evento')))->toBeTrue();
});

it('lote exige seleção explícita de trabalhos e de avaliadores', function () {
    $t = mkTrabalho($this->evento, $this->autor, ['titulo' => 'X'], 'em_triagem');
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.lote', $this->evento), ['acao' => 'atribuir', 'avaliadores' => [$this->av1->id]])->assertSessionHasErrors('trabalhos');
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.lote', $this->evento), ['acao' => 'atribuir', 'trabalhos' => [$t->id]])->assertSessionHasErrors('avaliadores');
});

it('apresentações: mesmo componente, mesmo resultado, e nada de "todos da sessão" implícito', function () {
    foreach ([['Domínio', 1]] as $i => [$n, $p]) {
        CriterioApresentacao::create(['evento_id' => $this->evento->id, 'nome' => $n, 'peso' => $p, 'nota_max' => 10, 'ordem' => $i]);
    }
    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => true]);
    $feira = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Feira', 'tipo' => 'outro', 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHours(2), 'carga_horaria' => 2, 'vagas' => 10, 'token_frequencia' => uniqid()]);
    $t1 = mkTrabalho($this->evento, $this->autor, ['titulo' => 'P1'], 'aprovado');
    $t2 = mkTrabalho($this->evento, $this->autor, ['titulo' => 'P2'], 'aprovado');
    $emAval = mkTrabalho($this->evento, $this->autor, ['titulo' => 'P3'], 'em_avaliacao');
    foreach ([$t1, $t2] as $t) { $t->update(['atividade_id' => $feira->id]); }

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.apresentacoes.index', $this->evento))->assertOk()
        ->assertSee('Ação em lote')->assertSee('data-lote', false)->assertSee('js-select-avaliadores-lote', false);

    // sem marcar trabalhos: erro (antes atribuía a todos da sessão)
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.apresentacoes.lote', $this->evento), ['avaliadores' => [$this->av1->id], 'atividade' => $feira->id])->assertSessionHasErrors('trabalhos');

    // selecionados + um não aprovado: aprovados recebem, o outro é reportado
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.apresentacoes.lote', $this->evento), [
        'trabalhos' => [$t1->id, $t2->id, $emAval->id], 'avaliadores' => [$this->av1->id, $this->av2->id],
    ])->assertSessionHas('success')->assertSessionHas('erros_lote');

    expect($t1->avaliacoesApresentacao()->count())->toBe(2)->and($t2->avaliacoesApresentacao()->count())->toBe(2)->and($emAval->avaliacoesApresentacao()->count())->toBe(0)
        ->and(collect(session('erros_lote'))->contains(fn ($l) => str_contains($l, 'não aprovado ou não encontrado')))->toBeTrue();
});

it('detalhe do trabalho: avaliadores aparecem agrupados e o impedido (atribuído, recusou, conflito) vem desabilitado com o motivo', function () {
    $t = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Meu trabalho'], 'em_triagem');
    $recusou = mkUser('user', 'recusou@x.com'); mkPapel($recusou, $this->evento, 'avaliador');
    mkPapel($this->autor, $this->evento, 'avaliador');   // o autor é avaliador do evento: conflito (não pode se autoavaliar)
    $w = app(\App\Services\TrabalhoWorkflow::class);
    $w->atribuirAvaliador($t, $this->av1, $this->gestor);
    $w->atribuirAvaliador($t, $recusou, $this->gestor)->update(['status' => 'recusada']);

    $html = $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $t))->assertOk()->getContent();

    expect($html)->toContain('seletor-avaliadores');
    expect($html)->toMatch('/value="' . $this->av1->id . '"\s+disabled\s+data-tipo="atribuido".*?já atribuído/su')
        ->toMatch('/value="' . $recusou->id . '"\s+disabled\s+data-tipo="recusou".*?recusou a avaliação/su')
        ->toMatch('/value="' . $this->autor->id . '"\s+disabled\s+data-tipo="conflito".*?é o responsável pela submissão/su')
        ->toMatch('/value="' . $this->av2->id . '"\s*>/');   // o disponível segue selecionável
    expect($html)->toContain('já atribuído(s)')->toContain('com conflito');
});

it('detalhe: sem nenhum avaliador disponível, avisa por que e bloqueia o botão', function () {
    $t = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Sem opção'], 'em_triagem');
    $w = app(\App\Services\TrabalhoWorkflow::class);
    $w->atribuirAvaliador($t, $this->av1, $this->gestor);
    $w->atribuirAvaliador($t, $this->av2, $this->gestor);

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $t))->assertOk()->assertSee('Nenhum avaliador disponível para este trabalho')->assertSee('2 já atribuído(s)/recusou');
});

it('restricoesAvaliadores informa conflito, já atribuído e recusou por trabalho', function () {
    $t = mkTrabalho($this->evento, $this->autor, ['titulo' => 'R'], 'em_triagem');
    mkPapel($this->autor, $this->evento, 'avaliador');
    $w = app(\App\Services\TrabalhoWorkflow::class);
    $w->atribuirAvaliador($t, $this->av1, $this->gestor);

    $r = $w->restricoesAvaliadores(collect([$t->fresh(['autores', 'avaliacoes'])]), $w->avaliadoresDoEvento($this->evento))[$t->id];

    expect($r[$this->av1->id]['tipo'])->toBe('atribuido')->and($r[$this->autor->id]['tipo'])->toBe('conflito')
        ->and($r[$this->autor->id]['motivo'])->toContain('responsável')->and($r)->not->toHaveKey($this->av2->id);
});

it('lote (trabalhos): a barra recebe os impedimentos de cada par para avisar antes de confirmar', function () {
    $t1 = mkTrabalho($this->evento, $this->autor, ['titulo' => 'L1'], 'em_triagem');
    $t2 = mkTrabalho($this->evento, $this->autor, ['titulo' => 'L2'], 'em_triagem');
    mkPapel($this->autor, $this->evento, 'avaliador');
    app(\App\Services\TrabalhoWorkflow::class)->atribuirAvaliador($t2, $this->av1, $this->gestor);

    $html = $this->actingAs($this->gestor)->get(route('gestao.trabalhos.index', $this->evento))->assertOk()->getContent();
    preg_match('/data-restricoes="([^"]*)"/', $html, $m);
    $mapa = json_decode(html_entity_decode($m[1]), true);

    expect($mapa[$t1->id][$this->autor->id]['tipo'])->toBe('conflito')->and($mapa[$t2->id][$this->autor->id]['tipo'])->toBe('conflito')
        ->and($mapa[$t2->id][$this->av1->id]['tipo'])->toBe('atribuido')->and($mapa)->not->toHaveKey("{$t1->id}.{$this->av1->id}")
        ->and($mapa[$t1->id] ?? [])->not->toHaveKey($this->av1->id);
    expect($html)->toContain('data-codigo="' . $t1->codigo . '"')->toContain('data-lote-impedimentos');
});

it('lote (apresentações): impedimentos usam as avaliações da apresentação; quem recusou pode ser reatribuído', function () {
    CriterioApresentacao::create(['evento_id' => $this->evento->id, 'nome' => 'D', 'peso' => 1, 'nota_max' => 10, 'ordem' => 0]);
    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => true]);
    $t = mkTrabalho($this->evento, $this->autor, ['titulo' => 'AP'], 'aprovado');
    mkPapel($this->autor, $this->evento, 'avaliador');
    $s = app(\App\Services\AvaliacaoApresentacaoService::class);
    $s->atribuir($t, $this->av1, $this->gestor);
    $s->atribuir($t, $this->av2, $this->gestor);
    $t->avaliacoesApresentacao()->where('avaliador_id', $this->av2->id)->update(['status' => 'recusada']);

    $html = $this->actingAs($this->gestor)->get(route('gestao.trabalhos.apresentacoes.index', $this->evento))->assertOk()->getContent();
    preg_match('/data-restricoes="([^"]*)"/', $html, $m);
    $mapa = json_decode(html_entity_decode($m[1]), true)[$t->id];

    expect($mapa[$this->av1->id]['tipo'])->toBe('atribuido')->and($mapa[$this->autor->id]['tipo'])->toBe('conflito')->and($mapa)->not->toHaveKey($this->av2->id);
});

it('lote: encaminha para avaliação só os trabalhos em triagem com o mínimo de avaliadores e explica os demais', function () {
    $w = app(\App\Services\TrabalhoWorkflow::class);
    $pronto = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Pronto'], 'em_triagem');
    $pronto2 = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Pronto 2'], 'em_triagem');
    $falta = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Falta'], 'em_triagem');
    $submetido = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Submetido'], 'submetido');
    foreach ([$pronto, $pronto2] as $t) { $w->atribuirAvaliador($t, $this->av1, $this->gestor); $w->atribuirAvaliador($t, $this->av2, $this->gestor); }
    $w->atribuirAvaliador($falta, $this->av1, $this->gestor);   // 1/2: abaixo do mínimo

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.lote', $this->evento), [
        'acao' => 'avaliacao', 'trabalhos' => [$pronto->id, $pronto2->id, $falta->id, $submetido->id],
    ])->assertSessionHas('success');

    expect($pronto->fresh()->status)->toBe('em_avaliacao')->and($pronto2->fresh()->status)->toBe('em_avaliacao')
        ->and($falta->fresh()->status)->toBe('em_triagem')->and($submetido->fresh()->status)->toBe('submetido');
    $linhas = collect(session('erros_lote'));
    expect($linhas->contains(fn ($l) => str_contains($l, 'Avaliadores insuficientes') && str_contains($l, "{$falta->codigo} (1/2)")))->toBeTrue()
        ->and($linhas->contains(fn ($l) => str_contains($l, 'Não está em triagem') && str_contains($l, $submetido->codigo)))->toBeTrue();

    // histórico registrado para quem foi encaminhado
    expect($pronto->historico()->where('para', 'em_avaliacao')->exists())->toBeTrue();
});

it('lote: se nenhum trabalho puder ser encaminhado, a resposta é de erro e nada muda', function () {
    $t = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Sem avaliador'], 'em_triagem');
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.lote', $this->evento), ['acao' => 'avaliacao', 'trabalhos' => [$t->id]])->assertSessionHas('error')->assertSessionHas('erros_lote');
    expect($t->fresh()->status)->toBe('em_triagem');
});

it('tela de trabalhos: ação "Encaminhar para avaliação" e atributos de aptidão por linha', function () {
    $w = app(\App\Services\TrabalhoWorkflow::class);
    $ok = mkTrabalho($this->evento, $this->autor, ['titulo' => 'OK'], 'em_triagem');
    $falta = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Falta'], 'em_triagem');
    $w->atribuirAvaliador($ok, $this->av1, $this->gestor); $w->atribuirAvaliador($ok, $this->av2, $this->gestor);
    $w->atribuirAvaliador($falta, $this->av1, $this->gestor);

    $html = $this->actingAs($this->gestor)->get(route('gestao.trabalhos.index', $this->evento))->assertOk()->assertSee('Encaminhar para avaliação')->getContent();
    preg_match('/value="' . $ok->id . '"[^>]*data-avaliacao="(\d)" data-motivo-avaliacao="([^"]*)"/', $html, $a);
    preg_match('/value="' . $falta->id . '"[^>]*data-avaliacao="(\d)" data-motivo-avaliacao="([^"]*)"/', $html, $b);
    expect($a[1])->toBe('1')->and($a[2])->toBe('')->and($b[1])->toBe('0')->and($b[2])->toBe('faltam 1 avaliador(es)');
});
