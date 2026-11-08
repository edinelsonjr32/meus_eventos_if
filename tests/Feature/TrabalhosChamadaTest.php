<?php

use App\Models\Evento;
use App\Models\TrabalhoConfig;

/** Fluxo de criação e publicação da chamada de trabalhos. */
function chamadaBase(array $extra = []): array
{
    return array_merge([
        'max_autores' => 10, 'max_arquivo_mb' => 20, 'resumo_min_palavras' => 50, 'resumo_max_palavras' => 250, 'tipos_permitidos' => ['pesquisa', 'extensao'],
        'avaliadores_por_trabalho' => 2, 'nota_minima_aprovacao' => 7, 'divergencia_maxima' => 3,
    ], $extra);
}

beforeEach(function () {
    $this->dono = mkUser('admin');
    // evento SEM chamada (não usa mkEvento, que já cria a configuração)
    $this->evento = Evento::create(['titulo' => 'Evento novo', 'slug' => 'novo-' . uniqid(), 'data_inicio' => now()->addMonth(), 'data_fim' => now()->addMonth()->addDays(2), 'local' => 'Campus', 'criado_por' => $this->dono->id]);
    $this->url = route('gestao.trabalhos.config.update', $this->evento);
});

it('descreve a situação da chamada em cada estado', function () {
    $c = fn (array $a) => new TrabalhoConfig($a);

    expect(TrabalhoConfig::situacaoDe(null)['chave'])->toBe('nao_criada')
        ->and(TrabalhoConfig::situacaoDe($c(['ativo' => false]))['chave'])->toBe('rascunho')
        ->and(TrabalhoConfig::situacaoDe($c(['ativo' => true, 'submissao_inicio' => now()->addDay()]))['chave'])->toBe('em_breve')
        ->and(TrabalhoConfig::situacaoDe($c(['ativo' => true, 'submissao_fim' => now()->subDay()]))['chave'])->toBe('encerrada')
        ->and(TrabalhoConfig::situacaoDe($c(['ativo' => true, 'submissao_inicio' => now()->subDay(), 'submissao_fim' => now()->addDay()]))['chave'])->toBe('aberta')
        ->and(TrabalhoConfig::situacaoDe($c(['ativo' => true]))['chave'])->toBe('aberta');
});

it('a página do evento mostra o card da chamada com o botão de criar quando ainda não existe', function () {
    $this->actingAs($this->dono)->get(route('admin.eventos.show', $this->evento->id))->assertOk()
        ->assertSee('Chamada de trabalhos')->assertSee('Chamada não criada')->assertSee('Criar chamada de trabalhos')
        ->assertSee(route('gestao.trabalhos.config', $this->evento->id), false)->assertDontSee('Ver trabalhos');
});

it('depois de criada, o card passa a oferecer editar e ver trabalhos, com a situação', function () {
    TrabalhoConfig::create(['evento_id' => $this->evento->id, 'ativo' => false]);

    $this->actingAs($this->dono)->get(route('admin.eventos.show', $this->evento->id))->assertOk()
        ->assertSee('Rascunho (não publicada)')->assertSee('Editar chamada')->assertSee('Ver trabalhos')->assertDontSee('Criar chamada de trabalhos');

    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['ativo' => true, 'submissao_inicio' => now()->subDay(), 'submissao_fim' => now()->addDays(5)]);
    $this->get(route('admin.eventos.show', $this->evento->id))->assertSee('Chamada aberta');
});

it('a tela de configuração se apresenta como criação e explica o passo a passo', function () {
    $this->actingAs($this->dono)->get(route('gestao.trabalhos.config', $this->evento))->assertOk()
        ->assertSee('Criar chamada de trabalhos')->assertSee('Situação:')->assertSee('Chamada não criada')
        ->assertSee('Configure')->assertSee('Publique')->assertSee('Salvar como rascunho')->assertSee('Salvar e publicar chamada')
        ->assertSee('Chamada publicada (aceita submissões');
});

it('"Salvar como rascunho" cria a chamada fechada, sem aparecer ao público', function () {
    $this->actingAs($this->dono)->put($this->url, chamadaBase(['acao' => 'salvar']))->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'Chamada criada como rascunho'));

    $c = TrabalhoConfig::where('evento_id', $this->evento->id)->first();
    expect($c)->not->toBeNull()->and($c->ativo)->toBeFalse();

    $this->get(route('evento.publico.show', $this->evento->slug))->assertOk()->assertDontSee('Submeter Trabalho');
});

it('"Salvar e publicar chamada" cria e abre a chamada de uma vez', function () {
    $this->actingAs($this->dono)->put($this->url, chamadaBase(['acao' => 'publicar', 'submissao_inicio' => now()->subDay()->format('Y-m-d\TH:i'), 'submissao_fim' => now()->addDays(10)->format('Y-m-d\TH:i')]))
        ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'Chamada criada e publicada'));

    expect(TrabalhoConfig::where('evento_id', $this->evento->id)->first()->ativo)->toBeTrue();
    $this->get(route('evento.publico.show', $this->evento->slug))->assertOk()->assertSee('Submeter Trabalho');
});

it('publicar um rascunho existente abre a chamada; salvar sem publicar respeita a chave', function () {
    TrabalhoConfig::create(['evento_id' => $this->evento->id, 'ativo' => false]);
    $this->actingAs($this->dono);

    $this->put($this->url, chamadaBase(['acao' => 'salvar']))->assertSessionHas('success', fn ($m) => str_contains($m, 'Configuração da chamada salva'));
    expect(TrabalhoConfig::where('evento_id', $this->evento->id)->first()->ativo)->toBeFalse();

    $this->put($this->url, chamadaBase(['acao' => 'publicar']))->assertSessionHas('success', fn ($m) => str_contains($m, 'Chamada publicada'));
    expect(TrabalhoConfig::where('evento_id', $this->evento->id)->first()->ativo)->toBeTrue();

    // chave ligada + "Salvar" mantém aberta; chave desligada fecha
    $this->put($this->url, chamadaBase(['acao' => 'salvar', 'ativo' => 1]));
    expect(TrabalhoConfig::where('evento_id', $this->evento->id)->first()->ativo)->toBeTrue();
    $this->put($this->url, chamadaBase(['acao' => 'salvar', 'ativo' => 0]));
    expect(TrabalhoConfig::where('evento_id', $this->evento->id)->first()->ativo)->toBeFalse();
});

it('chamada já publicada não oferece publicar de novo e mostra os links', function () {
    TrabalhoConfig::create(['evento_id' => $this->evento->id, 'ativo' => true]);

    $this->actingAs($this->dono)->get(route('gestao.trabalhos.config', $this->evento))->assertOk()
        ->assertSee('Editar chamada de trabalhos')->assertDontSee('Salvar e publicar chamada')
        ->assertSee(route('trabalhos.create', $this->evento->slug), false)->assertSee(route('evento.publico.show', $this->evento->slug), false);
});

it('a fila de trabalhos e a lista de eventos da gestão indicam como criar a chamada', function () {
    $gestor = mkUser();
    mkPapel($gestor, $this->evento, 'gestor_trabalhos');
    $this->actingAs($gestor);

    $this->get(route('gestao.trabalhos.index', $this->evento))->assertOk()->assertSee('Chamada não criada')->assertSee('Criar chamada de trabalhos');
    $this->get(route('gestao.trabalhos.eventos'))->assertOk()->assertSee('Evento novo')->assertSee('Chamada não criada')->assertSee('Criar chamada de trabalhos');

    TrabalhoConfig::create(['evento_id' => $this->evento->id, 'ativo' => false]);
    $this->get(route('gestao.trabalhos.index', $this->evento))->assertSee('Rascunho (não publicada)')->assertSee('Revisar e publicar');
    $this->get(route('gestao.trabalhos.eventos'))->assertSee('Editar chamada')->assertDontSee('Criar chamada de trabalhos');
});

it('somente quem gere trabalhos cria a chamada', function () {
    $this->actingAs(mkUser('admin'))->put($this->url, chamadaBase(['acao' => 'publicar']))->assertForbidden();
    $this->actingAs(mkUser())->get(route('gestao.trabalhos.config', $this->evento))->assertForbidden();

    expect(TrabalhoConfig::where('evento_id', $this->evento->id)->exists())->toBeFalse();
});

it('rejeita ação desconhecida ao salvar', function () {
    $this->actingAs($this->dono)->put($this->url, chamadaBase(['acao' => 'apagar-tudo']))->assertSessionHasErrors('acao');
});

it('sem o campo, a exigência de apresentação para certificado nunca vira "não exigir"', function () {
    $url = route('gestao.trabalhos.config.update', $this->evento);
    $this->actingAs($this->dono);

    $this->put($url, chamadaBase(['acao' => 'salvar']))->assertSessionHasNoErrors();   // chamada nova, campo ausente
    expect(TrabalhoConfig::where('evento_id', $this->evento->id)->first()->exige_apresentacao_para_certificado)->toBeTrue();

    $this->put($url, chamadaBase(['acao' => 'salvar', 'exige_apresentacao_para_certificado' => 0]));   // desligado de propósito
    expect(TrabalhoConfig::where('evento_id', $this->evento->id)->first()->exige_apresentacao_para_certificado)->toBeFalse();

    $this->put($url, chamadaBase(['acao' => 'salvar']));   // campo ausente: mantém o valor atual
    expect(TrabalhoConfig::where('evento_id', $this->evento->id)->first()->exige_apresentacao_para_certificado)->toBeFalse();
});

it('a tela da chamada carrega o feedback de validação e devolve os erros do servidor por campo', function () {
    $dono = mkUser('admin');
    $evento = mkEvento($dono);

    $this->actingAs($dono)->get(route('gestao.trabalhos.config', $evento))
        ->assertOk()->assertSee('data-validar', false)->assertSee('id="fb-toasts"', false)->assertSee('regrasChamada', false);

    // O servidor continua sendo a palavra final: a página reexibida leva as mensagens para o script marcar os campos
    $this->actingAs($dono)->from(route('gestao.trabalhos.config', $evento))
        ->put(route('gestao.trabalhos.config.update', $evento), ['avaliadores_por_trabalho' => '', 'tipos_permitidos' => []])
        ->assertRedirect(route('gestao.trabalhos.config', $evento))->assertSessionHasErrors(['avaliadores_por_trabalho', 'tipos_permitidos']);

    $this->actingAs($dono)->followingRedirects()->from(route('gestao.trabalhos.config', $evento))
        ->put(route('gestao.trabalhos.config.update', $evento), ['avaliadores_por_trabalho' => '', 'tipos_permitidos' => []])
        ->assertSee('Selecione ao menos uma categoria de trabalho.');
});
