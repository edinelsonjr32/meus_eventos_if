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
    // sem chamada: o botão e a seção aparecem avisando que ainda não abriu; só a organização vê o atalho para criar
    $this->get($url)->assertOk()->assertSee('Seja Voluntário(a)')->assertSee('href="#voluntariado"', false)->assertSee('ainda não foram abertas')
        ->assertDontSee('Quero ser voluntário(a)')->assertDontSee('crie a chamada de voluntários')->assertDontSee('id="noticias"', false);
    $this->actingAs($this->admin)->get($url)->assertSee('crie a chamada de voluntários')->assertSee(route('admin.voluntarios.index', $this->evento->id), false);
    auth()->logout();

    $chamada = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'Apoio na recepção', 'ativa' => true, 'inicio' => now()->addDay(), 'fim' => now()->addDays(5)]);
    $this->get($url)->assertSee('id="voluntariado"', false)->assertSee('Apoio na recep')->assertSee('Abre em')->assertDontSee('Quero ser voluntário(a)');

    $chamada->update(['inicio' => now()->subDay()]);
    $this->get($url)->assertSee('Quero ser voluntário(a)')->assertSee(route('voluntariado.create', $chamada), false)->assertSee('href="#voluntariado"', false);

    // evento encerrado e sem chamada ativa: não aparece
    $chamada->update(['ativa' => false]);
    $this->evento->update(['data_inicio' => now()->subDays(5), 'data_fim' => now()->subDay()]);
    $this->get($url)->assertDontSee('id="voluntariado"', false)->assertDontSee('Seja Voluntário(a)');
});

function inscreverNoEvento($evento, string $email, string $cpf): void
{
    $u = mkUser('user', $email);
    $p = App\Models\Participante::create(['user_id' => $u->id, 'nome_completo' => $email, 'cpf' => $cpf, 'email' => $email, 'tipo_vinculo' => 'externo']);
    App\Models\Inscricao::create(['evento_id' => $evento->id, 'participante_id' => $p->id, 'data_inscricao' => now()]);
}

it('avisa os inscritos por e-mail uma única vez, só quando marcado e com a notícia no ar', function () {
    Illuminate\Support\Facades\Mail::fake();
    inscreverNoEvento($this->evento, 'um@x.com', '111.444.777-35');
    inscreverNoEvento($this->evento, 'dois@x.com', '529.982.247-25');
    inscreverNoEvento(mkEvento($this->admin), 'outroevento@x.com', '390.533.447-05');
    $this->actingAs($this->admin);
    $this->get(route('admin.noticias.index', $this->evento))->assertSee('Avisar os inscritos por e-mail (2)');
    $dados = ['titulo' => 'Sem aviso', 'conteudo' => '<p>x</p>', 'publicada' => '1'];

    $this->post(route('admin.noticias.store', $this->evento), $dados);
    $this->post(route('admin.noticias.store', $this->evento), ['titulo' => 'Rascunho', 'publicada' => '0', 'notificar_inscritos' => '1'] + $dados);
    Illuminate\Support\Facades\Mail::assertNothingQueued();

    $this->post(route('admin.noticias.store', $this->evento), ['titulo' => 'Com aviso', 'notificar_inscritos' => '1'] + $dados)->assertSessionHas('success', 'Notícia salva. 2 e-mail(s) aos inscritos colocado(s) na fila de envio.');
    Illuminate\Support\Facades\Mail::assertQueued(App\Mail\NoticiaEventoMail::class, 2);
    Illuminate\Support\Facades\Mail::assertQueued(App\Mail\NoticiaEventoMail::class, fn ($m) => $m->hasTo('um@x.com'));
    Illuminate\Support\Facades\Mail::assertNotQueued(App\Mail\NoticiaEventoMail::class, fn ($m) => $m->hasTo('outroevento@x.com'));

    // editar não reenvia
    $n = NoticiaEvento::firstWhere('titulo', 'Com aviso');
    expect($n->notificados_total)->toBe(2);
    $this->put(route('admin.noticias.update', [$this->evento, $n]), ['titulo' => 'Com aviso (corrigido)', 'notificar_inscritos' => '1'] + $dados);
    $this->artisan('noticias:notificar')->assertSuccessful();
    Illuminate\Support\Facades\Mail::assertQueued(App\Mail\NoticiaEventoMail::class, 2);

    $html = (new App\Mail\NoticiaEventoMail($n->fresh()))->render();
    expect($html)->toContain('Ler no site')->toContain('#noticias');
});

it('notícia agendada avisa pelo agendador quando entra no ar', function () {
    Illuminate\Support\Facades\Mail::fake();
    inscreverNoEvento($this->evento, 'um@x.com', '111.444.777-35');
    $this->actingAs($this->admin)->post(route('admin.noticias.store', $this->evento), ['titulo' => 'Depois', 'conteudo' => '<p>x</p>', 'publicada' => '1', 'notificar_inscritos' => '1', 'publicado_em' => now()->addDay()->format('Y-m-d\TH:i')]);

    $this->artisan('noticias:notificar');
    Illuminate\Support\Facades\Mail::assertNothingQueued();

    $this->travel(2)->days();
    $this->artisan('noticias:notificar');
    $this->artisan('noticias:notificar');
    Illuminate\Support\Facades\Mail::assertQueued(App\Mail\NoticiaEventoMail::class, 1);
});

it('avisa na tela quando a notícia é salva sem notificar os inscritos', function () {
    Illuminate\Support\Facades\Mail::fake();
    $dono = mkUser('admin', 'chefe-aviso@ifpa.edu.br');
    $evento = mkEvento($dono);

    $this->actingAs($dono)->post(route('admin.noticias.store', $evento), ['titulo' => 'Sem aviso', 'conteudo' => '<p>Texto</p>', 'publicada' => 1])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'Nenhum e-mail foi enviado'));
    $this->get(route('admin.noticias.index', $evento))->assertSee('sem aviso por e-mail');
    Illuminate\Support\Facades\Mail::assertNothingOutgoing();
});
