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
