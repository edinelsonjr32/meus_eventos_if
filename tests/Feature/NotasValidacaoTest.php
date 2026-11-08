<?php

use App\Models\Atividade;
use App\Models\CriterioApresentacao;
use App\Services\AvaliacaoApresentacaoService;
use App\Services\TrabalhoWorkflow;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);   // critérios do texto: Conteúdo (peso 1) e Metodologia (peso 2), nota máx. 10
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->av = mkUser('user', 'av@x.com');
    mkPapel($this->av, $this->evento, 'avaliador');
    $this->autor = mkUser('user', 'autor@x.com');
    $this->criterios = $this->evento->criterios()->pluck('id');
});

function notasTexto($ctx): array
{
    $t = mkTrabalho($ctx->evento, $ctx->autor, ['titulo' => 'Notas'], 'em_triagem');
    app(TrabalhoWorkflow::class)->atribuirAvaliador($t, $ctx->av, $ctx->gestor);
    $ctx->evento->trabalhoConfig->update(['avaliadores_por_trabalho' => 1]);
    app(TrabalhoWorkflow::class)->transicionar($t->fresh(), $ctx->gestor, 'em_avaliacao');

    return [$t, $t->avaliacoes()->first()];
}

it('avaliação do texto: só aceita notas de 0 ao máximo do critério, com até 2 casas', function (string $nota, bool $valida) {
    [, $a] = notasTexto($this);
    $resp = $this->actingAs($this->av)->post(route('avaliador.avaliacoes.salvar', $a), ['notas' => [$this->criterios[0] => $nota]]);

    $valida ? $resp->assertSessionHasNoErrors() : $resp->assertSessionHasErrors("notas.{$this->criterios[0]}");
    expect($a->notas()->count())->toBe($valida ? 1 : 0);
})->with([
    'zero' => ['0', true], 'dez' => ['10', true], 'com vírgula' => ['7,5', true], 'duas casas' => ['8.25', true],
    'acima do máximo' => ['10.5', false], 'onze' => ['11', false], 'muito alto' => ['100', false], 'negativa' => ['-1', false],
    'letras' => ['abc', false], 'três casas' => ['7.555', false], 'notação científica' => ['1e1', false],
]);

it('avaliação da apresentação: mesmas regras (0 ao máximo, 2 casas)', function (string $nota, bool $valida) {
    CriterioApresentacao::create(['evento_id' => $this->evento->id, 'nome' => 'Domínio', 'peso' => 1, 'nota_max' => 10, 'ordem' => 0]);
    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => true]);
    $t = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Apres'], 'aprovado');
    $s = app(AvaliacaoApresentacaoService::class);
    $s->atribuir($t, $this->av, $this->gestor);
    $a = $t->avaliacoesApresentacao()->first();
    $crit = $this->evento->criteriosApresentacao()->first()->id;

    $resp = $this->actingAs($this->av)->post(route('avaliador.apresentacoes.salvar', $a), ['notas' => [$crit => $nota]]);
    $valida ? $resp->assertSessionHasNoErrors() : $resp->assertSessionHasErrors("notas.{$crit}");
})->with([
    'ok' => ['9,5', true], 'zero' => ['0', true], 'onze' => ['11', false], 'negativa' => ['-0.1', false], 'letras' => ['dez', false], 'três casas' => ['1.234', false],
]);

it('critério com escala diferente (0 a 5) respeita o próprio máximo', function () {
    $this->evento->criterios()->update(['nota_max' => 5]);
    [, $a] = notasTexto($this);
    $this->actingAs($this->av)->post(route('avaliador.avaliacoes.salvar', $a), ['notas' => [$this->criterios[0] => '5']])->assertSessionHasNoErrors();
    $this->actingAs($this->av)->post(route('avaliador.avaliacoes.salvar', $a), ['notas' => [$this->criterios[1] => '5.1']])->assertSessionHasErrors("notas.{$this->criterios[1]}");
});

it('os campos de nota trazem a máscara com o máximo do critério', function () {
    [, $a] = notasTexto($this);
    $html = $this->actingAs($this->av)->get(route('avaliador.avaliacoes.show', $a))->assertOk()->getContent();

    expect(substr_count($html, 'data-mascara="nota"'))->toBe(2)->and($html)->toContain('data-max="10"')->toContain('data-nome="Conteúdo"');
});
