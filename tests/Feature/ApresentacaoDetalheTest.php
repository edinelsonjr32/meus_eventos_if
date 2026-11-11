<?php

use App\Models\CriterioApresentacao;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    foreach (['av1', 'av2'] as $n) {
        $this->$n = mkUser('user', "{$n}@x.com");
        mkPapel($this->$n, $this->evento, 'avaliador');
    }
    $this->autor = mkUser('user', 'autor@x.com');
    CriterioApresentacao::create(['evento_id' => $this->evento->id, 'nome' => 'Domínio', 'peso' => 1, 'nota_max' => 10, 'ordem' => 0]);
});

function aprovado($ctx, string $titulo = 'Aprovado', ?array $autores = null)
{
    return mkTrabalho($ctx->evento, $ctx->autor, ['titulo' => $titulo, 'autores' => $autores ?? [
        ['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => true],
        ['nome_completo' => 'Caio', 'cpf' => '529.982.247-25', 'email' => 'caio@x.com', 'tipo_autor' => 'coautor'],
    ]], 'aprovado');
}

it('seleção de apresentador: lista TODOS os autores, com botão de tornar ou remover conforme o caso', function () {
    $t = aprovado($this);
    $html = $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $t))->assertOk()->getContent();

    expect($html)->toContain('escolha quem apresenta')->toContain('Tornar apresentador')->toContain('Remover')
        ->and($html)->toContain('Ana')->toContain('Caio');

    // trabalho com um único autor (já apresentador): ainda dá para gerir (antes a lista ficava vazia)
    $solo = aprovado($this, 'Solo', [['nome_completo' => 'Autor Teste', 'cpf' => '111.444.777-35', 'email' => 'autor@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => true]]);
    $this->get(route('gestao.trabalhos.show', $solo))->assertOk()->assertSee('Autor Teste')->assertSee('Remover');
});

it('define e remove apresentador autor a autor', function () {
    $t = aprovado($this);
    $caio = $t->autores()->where('nome_completo', 'Caio')->first();
    $ana = $t->autores()->where('nome_completo', 'Ana')->first();

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.apresentador', $t), ['autor_id' => $caio->id, 'acao' => 'definir'])->assertSessionHas('success');
    expect($caio->fresh()->apresentador)->toBeTrue()->and($ana->fresh()->apresentador)->toBeTrue();

    $this->post(route('gestao.trabalhos.apresentador', $t), ['autor_id' => $ana->id, 'acao' => 'remover'])->assertSessionHas('success');
    expect($ana->fresh()->apresentador)->toBeFalse()->and($caio->fresh()->apresentador)->toBeTrue()
        ->and($t->fresh()->historico->pluck('justificativa')->implode('|'))->toContain('Apresentador removido: Ana');

    $this->post(route('gestao.trabalhos.apresentador', $t), ['autor_id' => $ana->id, 'acao' => 'invalida'])->assertSessionHasErrors('acao');
});

it('avaliadores da apresentação aparecem no detalhe só quando aprovado na parte escrita e a avaliação está ativa', function () {
    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => true]);

    $aprovado = aprovado($this);
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $aprovado))->assertOk()
        ->assertSee('Avaliadores da apresentação')->assertSee('Atribuir à apresentação')->assertSee(route('gestao.trabalhos.apresentacoes.atribuir', $aprovado), false)->assertSee('selAvaliadoresAp', false);

    foreach (['submetido', 'em_triagem', 'em_avaliacao', 'avaliado', 'aguardando_correcao', 'resubmetido', 'reprovado'] as $status) {
        $t = mkTrabalho($this->evento, $this->autor, ['titulo' => "T {$status}"], $status);
        $this->get(route('gestao.trabalhos.show', $t))->assertOk()->assertDontSee('Avaliadores da apresentação')->assertDontSee('Atribuir à apresentação');
    }
});

it('com a avaliação de apresentações desativada, o detalhe só avisa e aponta para a configuração', function () {
    $t = aprovado($this);
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $t))->assertOk()
        ->assertSee('não está ativada neste evento')->assertSee('Ativar e configurar')->assertDontSee('Atribuir à apresentação');
});

it('atribui e remove avaliadores da apresentação pelo detalhe, com impedimentos informados', function () {
    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => true]);
    $t = aprovado($this);
    mkPapel($this->autor, $this->evento, 'avaliador');   // o responsável também é avaliador do evento: conflito

    $html = $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $t))->getContent();
    expect($html)->toContain('seletor-avaliadores')
        ->toMatch('/value="' . $this->autor->id . '"\s+disabled\s+data-tipo="conflito".*?é o responsável pela submissão/su');

    $this->post(route('gestao.trabalhos.apresentacoes.atribuir', $t), ['avaliadores' => [$this->av1->id, $this->autor->id]])->assertSessionHas('success')->assertSessionHas('erros_lote');
    expect($t->avaliacoesApresentacao()->pluck('avaliador_id')->all())->toBe([$this->av1->id]);

    // depois de atribuído, o avaliador vai para o grupo "já atribuídos" e pode ser removido
    $this->get(route('gestao.trabalhos.show', $t))->assertSee('já atribuído')->assertSee('Remover');
    $a = $t->avaliacoesApresentacao()->first();
    $this->delete(route('gestao.trabalhos.apresentacoes.remover', $a))->assertSessionHas('success');
    expect($t->avaliacoesApresentacao()->count())->toBe(0);
});

it('só a gestão atribui avaliadores da apresentação pelo detalhe', function () {
    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => true]);
    $t = aprovado($this);
    $this->actingAs($this->autor)->post(route('gestao.trabalhos.apresentacoes.atribuir', $t), ['avaliadores' => [$this->av1->id]])->assertForbidden();
    $this->actingAs($this->av1)->post(route('gestao.trabalhos.apresentacoes.atribuir', $t), ['avaliadores' => [$this->av1->id]])->assertForbidden();
});
