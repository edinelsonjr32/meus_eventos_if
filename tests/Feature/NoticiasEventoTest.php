<?php

use App\Models\ChamadaVoluntario;
use App\Models\NoticiaEvento;

beforeEach(function () {
    $this->admin = mkUser('admin', 'dono@x.com');
    $this->evento = mkEvento($this->admin);
});

it('a organização publica, edita, agenda e exclui notícias; o site mostra só as que estão no ar', function () {
    $this->actingAs($this->admin);
    $this->get(route('admin.noticias.index', $this->evento))->assertOk()->assertSee('Nenhuma notícia ainda');
    $this->get(route('admin.eventos.show', $this->evento))->assertSee(route('admin.noticias.index', $this->evento->id), false);

    $this->post(route('admin.noticias.store', $this->evento), ['titulo' => 'Credenciamento aberto', 'conteudo' => '<p>Das 8h às 12h<script>alert(1)</script></p>', 'publicada' => '1', 'fixada' => '1'])->assertSessionHas('success');
    $this->post(route('admin.noticias.store', $this->evento), ['titulo' => 'Novidade agendada', 'conteudo' => '<p>depois</p>', 'publicada' => '1', 'publicado_em' => now()->addDays(2)->format('Y-m-d\TH:i')]);
    $this->post(route('admin.noticias.store', $this->evento), ['titulo' => 'Texto em rascunho', 'conteudo' => '<p>nao</p>']);
    $this->post(route('admin.noticias.store', $this->evento), ['titulo' => '', 'conteudo' => '<p>&nbsp;</p>'])->assertSessionHasErrors('titulo');
    $this->post(route('admin.noticias.store', $this->evento), ['titulo' => 'Vazia', 'conteudo' => '<p>&nbsp;</p>'])->assertSessionHasErrors('conteudo');

    $noticia = NoticiaEvento::firstWhere('titulo', 'Credenciamento aberto');
    expect($noticia->conteudo)->not->toContain('script')->and(NoticiaEvento::count())->toBe(3);

    auth()->logout();
    $this->get(route('evento.publico.show', $this->evento->slug))->assertOk()
        ->assertSee('Notícias e informações')->assertSee('Credenciamento aberto')->assertSee('Das 8h')
        ->assertDontSee('Novidade agendada')->assertDontSee('Texto em rascunho')->assertDontSee('alert(1)', false);
    $this->travel(3)->days();
    $this->get(route('evento.publico.show', $this->evento->slug))->assertSee('Novidade agendada');

    $this->actingAs($this->admin);
    $this->get(route('admin.noticias.edit', [$this->evento, $noticia]))->assertOk()->assertSee('Credenciamento aberto');
    $this->put(route('admin.noticias.update', [$this->evento, $noticia]), ['titulo' => 'Credenciamento mudou', 'conteudo' => '<p>novo horário</p>', 'publicada' => '1'])->assertSessionHas('success');
    expect($noticia->fresh()->titulo)->toBe('Credenciamento mudou')->and($noticia->fresh()->fixada)->toBeFalse();
    $this->delete(route('admin.noticias.destroy', [$this->evento, $noticia]))->assertSessionHas('success');
    expect(NoticiaEvento::count())->toBe(2);
});

it('quem não gerencia o evento não mexe nas notícias, e não se edita notícia de outro evento', function () {
    $noticia = NoticiaEvento::create(['evento_id' => $this->evento->id, 'titulo' => 'A', 'conteudo' => '<p>a</p>', 'publicado_em' => now()]);

    $this->actingAs(mkUser('user', 'curioso@x.com'));
    $this->post(route('admin.noticias.store', $this->evento), ['titulo' => 'X', 'conteudo' => '<p>x</p>']);
    $this->delete(route('admin.noticias.destroy', [$this->evento, $noticia]));
    expect(NoticiaEvento::count())->toBe(1);

    $outro = mkEvento($this->admin);
    $this->actingAs($this->admin)->delete(route('admin.noticias.destroy', [$outro, $noticia]))->assertNotFound();
});

it('a página pública mostra a seção de voluntariado com o botão só quando a chamada está aberta', function () {
    $url = route('evento.publico.show', $this->evento->slug);
    $this->get($url)->assertOk()->assertDontSee('id="voluntariado"', false)->assertDontSee('id="noticias"', false);

    $chamada = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'Apoio na recepção', 'ativa' => true, 'inicio' => now()->addDay(), 'fim' => now()->addDays(5)]);
    $this->get($url)->assertSee('id="voluntariado"', false)->assertSee('Apoio na recep')->assertSee('Abre em')->assertDontSee('Quero ser voluntário(a)');

    $chamada->update(['inicio' => now()->subDay()]);
    $this->get($url)->assertSee('Quero ser voluntário(a)')->assertSee(route('voluntariado.create', $chamada), false)->assertSee('href="#voluntariado"', false);

    $chamada->update(['ativa' => false]);
    $this->get($url)->assertDontSee('id="voluntariado"', false);
});
