<?php

use App\Models\Anais;
use App\Models\Evento;
use App\Models\Inscricao;
use App\Models\Participante;

/** A paginação precisa sair no estilo Bootstrap (sem as setas SVG gigantes do estilo Tailwind) e em português. */
function paginacaoOk(string $html): void
{
    expect($html)->toContain('class="pagination"')
        ->and($html)->not->toContain('<svg class="w-5 h-5"')          // marcação Tailwind padrão do Laravel
        ->and($html)->not->toContain('Pagination Navigation')          // texto em inglês do estilo Tailwind
        ->and($html)->toContain('Mostrando')->toContain('resultados');
}

beforeEach(function () {
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->evento->update(['slug' => 'pag']);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->autor = mkUser();
});

it('o locale padrão tem tradução da paginação', function () {
    expect(config('app.locale'))->toBe('pt-br')->and(__('pagination.next'))->toContain('Próxima')->and(__('Showing'))->toBe('Mostrando');
});

it('fila de trabalhos da gestão pagina em Bootstrap', function () {
    foreach (range(1, 22) as $i) { mkTrabalho($this->evento, $this->autor, ['titulo' => "Trabalho {$i}"]); }

    $html = $this->actingAs($this->gestor)->get(route('gestao.trabalhos.index', $this->evento))->assertOk()->getContent();
    paginacaoOk($html);
    expect($html)->toContain('page=2');
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.index', [$this->evento, 'page' => 2]))->assertOk();
});

it('lista de certificados pagina em Bootstrap', function () {
    foreach (range(1, 32) as $i) { mkTrabalho($this->evento, $this->autor, ['titulo' => "Cert {$i}"], 'aprovado'); }

    paginacaoOk($this->actingAs($this->gestor)->get(route('gestao.trabalhos.certificados.index', $this->evento))->assertOk()->getContent());
});

it('Anais públicos paginam em Bootstrap', function () {
    Anais::create(['evento_id' => $this->evento->id, 'titulo' => 'Anais', 'ano' => 2026, 'status' => 'publicado', 'publicado_em' => now(), 'dados_publicacao_visiveis' => false]);
    foreach (range(1, 17) as $i) {
        mkTrabalho($this->evento, $this->autor, ['titulo' => "Publicado {$i}"], 'aprovado')->update(['apresentado' => true, 'autoriza_publicacao' => true, 'resultado_apresentacao' => 'aprovado']);
    }
    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => false]);

    paginacaoOk($this->get(route('anais.index', 'pag'))->assertOk()->getContent());
});

it('lista de eventos e de inscritos (admin) paginam em Bootstrap', function () {
    foreach (range(1, 11) as $i) { mkEvento($this->dono); }
    paginacaoOk($this->actingAs($this->dono)->get(route('admin.eventos.index'))->assertOk()->getContent());

    foreach (range(1, 21) as $i) {
        $p = Participante::create(['nome_completo' => "Inscrito {$i}", 'cpf' => sprintf('%03d.%03d.%03d-%02d', $i, $i, $i, $i), 'email' => "i{$i}@x.com", 'tipo_vinculo' => 'externo']);
        Inscricao::create(['evento_id' => $this->evento->id, 'participante_id' => $p->id, 'data_inscricao' => now()]);
    }
    paginacaoOk($this->actingAs($this->dono)->get(route('admin.eventos.inscritos', $this->evento->id))->assertOk()->getContent());
});
