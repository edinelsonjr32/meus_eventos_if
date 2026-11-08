<?php

use App\Mail\ApresentacaoAvaliadorAtribuidoMail;
use App\Models\ApresentacaoAvaliacao;
use App\Models\Atividade;
use App\Models\CriterioApresentacao;
use App\Models\Trabalho;
use App\Services\AvaliacaoApresentacaoService;
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
    $this->feira = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Feira de Trabalhos', 'tipo' => 'outro', 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHours(3), 'carga_horaria' => 3, 'vagas' => 100, 'token_frequencia' => uniqid()]);
    $this->trabalho = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Trabalho da feira'], 'aprovado');
    $this->trabalho->update(['atividade_id' => $this->feira->id, 'nota_final' => 8.0]);
    $this->svc = app(AvaliacaoApresentacaoService::class);
    foreach ([['Domínio', 1], ['Comunicação', 1]] as $i => [$n, $p]) {
        CriterioApresentacao::create(['evento_id' => $this->evento->id, 'nome' => $n, 'peso' => $p, 'nota_max' => 10, 'ordem' => $i]);
    }
    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => true, 'peso_apresentacao' => 25]);
});

it('gestão configura critérios, peso e ativação da avaliação da apresentação', function () {
    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => false]);
    $this->evento->criteriosApresentacao()->delete();

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.apresentacoes.index', $this->evento))->assertOk()->assertSee('Avaliação das apresentações');

    $this->actingAs($this->gestor)->put(route('gestao.trabalhos.apresentacoes.config', $this->evento), [
        'avaliacao_apresentacao_ativa' => 1, 'peso_apresentacao' => 40, 'autor_ve_nota_apresentacao' => 1,
        'criterios' => [['nome' => 'Domínio', 'peso' => 2, 'nota_max' => 10], ['nome' => 'Tempo', 'peso' => 1, 'nota_max' => 5]],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $cfg = $this->evento->trabalhoConfig->fresh();
    expect($cfg->avaliacao_apresentacao_ativa)->toBeTrue()->and($cfg->peso_apresentacao)->toBe(40)
        ->and($this->evento->criteriosApresentacao()->pluck('nome')->all())->toBe(['Domínio', 'Tempo']);

    // ativar sem nenhum critério é recusado
    $this->evento->criteriosApresentacao()->delete();
    $this->actingAs($this->gestor)->put(route('gestao.trabalhos.apresentacoes.config', $this->evento), ['avaliacao_apresentacao_ativa' => 1, 'peso_apresentacao' => 30])
        ->assertSessionHasErrors('criterios');
});

it('só gestão acessa; autor e avaliador não', function () {
    $this->actingAs($this->autor)->get(route('gestao.trabalhos.apresentacoes.index', $this->evento))->assertForbidden();
    $this->actingAs($this->av1)->get(route('gestao.trabalhos.apresentacoes.index', $this->evento))->assertForbidden();
    $this->actingAs($this->av1)->post(route('gestao.trabalhos.apresentacoes.atribuir', $this->trabalho), ['avaliadores' => [$this->av1->id]])->assertForbidden();
});

it('atribui avaliadores à apresentação de um trabalho aprovado e avisa por e-mail', function () {
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.apresentacoes.atribuir', $this->trabalho), ['avaliadores' => [$this->av1->id, $this->av2->id]])
        ->assertSessionHas('success');

    expect($this->trabalho->avaliacoesApresentacao()->count())->toBe(2);
    Mail::assertQueued(ApresentacaoAvaliadorAtribuidoMail::class, 2);

    // repetir não duplica
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.apresentacoes.atribuir', $this->trabalho), ['avaliadores' => [$this->av1->id]])->assertSessionHas('error');
    expect($this->trabalho->avaliacoesApresentacao()->count())->toBe(2);
});

it('não atribui trabalho não aprovado, avaliação desativada, não avaliador ou conflito de interesse', function () {
    $nao = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Em avaliação'], 'em_avaliacao');
    expect(fn () => $this->svc->atribuir($nao, $this->av1, $this->gestor))->toThrow(DomainException::class, 'só trabalhos aprovados');

    $comum = mkUser();
    expect(fn () => $this->svc->atribuir($this->trabalho, $comum, $this->gestor))->toThrow(DomainException::class, 'não é avaliador');

    mkPapel($this->autor, $this->evento, 'avaliador');
    expect(fn () => $this->svc->atribuir($this->trabalho, $this->autor, $this->gestor))->toThrow(DomainException::class, 'Conflito de interesse');

    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => false]);
    expect(fn () => $this->svc->atribuir($this->trabalho->fresh(), $this->av1, $this->gestor))->toThrow(DomainException::class, 'não está ativada');
});

it('atribui em lote a todos os aprovados de uma sessão, ignorando conflitos', function () {
    $outro = mkTrabalho($this->evento, $this->av1, ['titulo' => 'Do próprio avaliador'], 'aprovado');   // av1 é o responsável: conflito
    $outro->update(['atividade_id' => $this->feira->id]);

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.apresentacoes.lote', $this->evento), ['avaliadores' => [$this->av1->id, $this->av2->id], 'atividade' => $this->feira->id])
        ->assertSessionHas('success')->assertSessionHas('warning');

    expect(ApresentacaoAvaliacao::count())->toBe(3)   // 2 no trabalho da feira + 1 (av2) no do av1
        ->and($outro->avaliacoesApresentacao()->pluck('avaliador_id')->all())->toBe([$this->av2->id]);
});

it('o avaliador avalia, conclui e a nota da apresentação e a geral são calculadas', function () {
    $this->svc->atribuir($this->trabalho, $this->av1, $this->gestor);
    $this->svc->atribuir($this->trabalho, $this->av2, $this->gestor);
    $a1 = $this->trabalho->avaliacoesApresentacao()->where('avaliador_id', $this->av1->id)->first();
    $a2 = $this->trabalho->avaliacoesApresentacao()->where('avaliador_id', $this->av2->id)->first();
    $crits = $this->evento->criteriosApresentacao()->pluck('id');

    $this->actingAs($this->av1)->get(route('avaliador.apresentacoes.index'))->assertOk()->assertSee('Trabalho da feira')->assertSee('Feira de Trabalhos');
    $this->actingAs($this->av1)->get(route('avaliador.apresentacoes.show', $a1))->assertOk()->assertSee('Critérios da apresentação');

    // concluir incompleto é recusado
    $this->actingAs($this->av1)->post(route('avaliador.apresentacoes.concluir', $a1), ['notas' => [$crits[0] => '9'], 'parecer' => 'Boa apresentação, clara.'])->assertSessionHas('error');
    expect($a1->fresh()->status)->toBe('em_andamento');

    $this->actingAs($this->av1)->post(route('avaliador.apresentacoes.concluir', $a1), ['notas' => [$crits[0] => '9', $crits[1] => '8,0'], 'parecer' => 'Boa apresentação, clara.'])->assertRedirect(route('avaliador.apresentacoes.index'));
    $this->actingAs($this->av2)->post(route('avaliador.apresentacoes.concluir', $a2), ['notas' => [$crits[0] => '7', $crits[1] => '7'], 'parecer' => 'Cumpriu o tempo.'])->assertRedirect();

    $t = $this->trabalho->fresh();
    expect($a1->fresh()->nota_final)->toBe(8.5)->and($a2->fresh()->nota_final)->toBe(7.0)
        ->and($t->nota_apresentacao)->toBe(7.75)
        ->and($t->notaGeral())->toBe(round(8.0 * 0.75 + 7.75 * 0.25, 2));   // peso 25%

    // concluída não edita
    $this->actingAs($this->av1)->post(route('avaliador.apresentacoes.salvar', $a1), ['notas' => [$crits[0] => '1']])->assertForbidden();
});

it('um avaliador não acessa a avaliação de outro nem baixa o PDF alheio', function () {
    $this->svc->atribuir($this->trabalho, $this->av1, $this->gestor);
    $a1 = $this->trabalho->avaliacoesApresentacao()->first();

    $this->actingAs($this->av2)->get(route('avaliador.apresentacoes.show', $a1))->assertForbidden();
    $this->actingAs($this->av2)->get(route('avaliador.apresentacoes.arquivo', $a1))->assertForbidden();
    $this->actingAs($this->autor)->get(route('avaliador.apresentacoes.show', $a1))->assertForbidden();
});

it('recusar libera a gestão para reatribuir o mesmo avaliador', function () {
    $this->svc->atribuir($this->trabalho, $this->av1, $this->gestor);
    $a = $this->trabalho->avaliacoesApresentacao()->first();

    $this->actingAs($this->av1)->post(route('avaliador.apresentacoes.recusar', $a), ['motivo' => 'Conflito de agenda'])->assertRedirect();
    expect($a->fresh()->status)->toBe('recusada');

    $this->svc->atribuir($this->trabalho, $this->av1, $this->gestor);
    expect($a->fresh()->status)->toBe('pendente')->and($this->trabalho->avaliacoesApresentacao()->count())->toBe(1);
});

it('a gestão remove avaliador pendente mas não o que já concluiu; a nota é recalculada', function () {
    $this->svc->atribuir($this->trabalho, $this->av1, $this->gestor);
    $this->svc->atribuir($this->trabalho, $this->av2, $this->gestor);
    [$a1, $a2] = $this->trabalho->avaliacoesApresentacao()->get()->all();
    $crits = $this->evento->criteriosApresentacao()->pluck('id');
    $this->svc->concluir($a1, ['notas' => [$crits[0] => 10, $crits[1] => 10], 'parecer' => 'Excelente apresentação!']);
    expect($this->trabalho->fresh()->nota_apresentacao)->toBe(10.0);

    $this->actingAs($this->gestor)->delete(route('gestao.trabalhos.apresentacoes.remover', $a1))->assertSessionHas('error');
    $this->actingAs($this->gestor)->delete(route('gestao.trabalhos.apresentacoes.remover', $a2))->assertSessionHas('success');
    expect($this->trabalho->avaliacoesApresentacao()->count())->toBe(1);
});

it('o autor vê a nota e o parecer da apresentação conforme a configuração; o confidencial nunca', function () {
    $this->svc->atribuir($this->trabalho, $this->av1, $this->gestor);
    $a = $this->trabalho->avaliacoesApresentacao()->first();
    $crits = $this->evento->criteriosApresentacao()->pluck('id');
    $this->svc->concluir($a, ['notas' => [$crits[0] => 9, $crits[1] => 9], 'parecer' => 'Parabéns pela clareza.', 'parecer_confidencial' => 'Segredo da banca']);

    $this->actingAs($this->autor)->get(route('trabalhos.show', $this->trabalho))->assertOk()
        ->assertSee('Avaliação da apresentação')->assertSee('9,00')->assertSee('Parabéns pela clareza.')->assertDontSee('Segredo da banca')->assertDontSee('av1@x.com');

    $this->evento->trabalhoConfig->update(['autor_ve_nota_apresentacao' => false]);
    $this->actingAs($this->autor)->get(route('trabalhos.show', $this->trabalho))->assertDontSee('Parabéns pela clareza.');
});

it('o ranking mostra a nota da apresentação e a geral', function () {
    $this->svc->atribuir($this->trabalho, $this->av1, $this->gestor);
    $a = $this->trabalho->avaliacoesApresentacao()->first();
    $crits = $this->evento->criteriosApresentacao()->pluck('id');
    $this->svc->concluir($a, ['notas' => [$crits[0] => 6, $crits[1] => 6], 'parecer' => 'Apresentação mediana.']);

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.ranking', $this->evento))->assertOk()->assertSee('Apresentação')->assertSee('7,50');   // 8*0,75 + 6*0,25
    $linha = app(\App\Services\RelatorioNotas::class)->resumo($this->evento)->first();
    expect($linha['nota_apresentacao'])->toBe(6.0)->and($linha['nota_geral'])->toBe(7.5);
});

it('workflow de correção: com ressalvas, só entra na avaliação da apresentação depois de aprovado', function () {
    $t = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Com ressalvas'], 'aguardando_correcao');
    expect(fn () => $this->svc->atribuir($t, $this->av1, $this->gestor))->toThrow(DomainException::class);
    $t->update(['status' => 'aprovado']);
    expect($this->svc->atribuir($t->fresh(), $this->av1, $this->gestor))->toBeInstanceOf(ApresentacaoAvaliacao::class);
});
