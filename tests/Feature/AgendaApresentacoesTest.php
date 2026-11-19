<?php

use App\Mail\ApresentacaoAgendadaMail;
use App\Models\ApresentacaoAvaliacao;
use App\Models\SalaApresentacao;
use App\Models\Trabalho;
use App\Services\AgendaApresentacoes;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->av = mkUser('user', 'av@x.com');
    mkPapel($this->av, $this->evento, 'avaliador');
    $this->avOutro = mkUser('user', 'avoutro@x.com');
    mkPapel($this->avOutro, $this->evento, 'avaliador');
    $this->autor = mkUser('user', 'autora@x.com');
    $this->estranho = mkUser('user', 'estranho@x.com');

    $this->mk = function (string $titulo, string $modalidade = 'oral') {
        $t = mkTrabalho($this->evento, $this->autor, ['titulo' => $titulo], 'aprovado');
        $t->update(['modalidade_apresentacao' => $modalidade]);

        return $t->fresh();
    };
    $this->t1 = ($this->mk)('Primeiro trabalho');
    $this->t2 = ($this->mk)('Segundo trabalho');
    $this->t3 = ($this->mk)('Terceiro trabalho');
    $this->poster = ($this->mk)('Um pôster', 'poster');

    ApresentacaoAvaliacao::create(['trabalho_id' => $this->t1->id, 'avaliador_id' => $this->av->id, 'status' => 'pendente', 'atribuido_por' => $this->gestor->id]);
    $this->sala = SalaApresentacao::create(['evento_id' => $this->evento->id, 'nome' => 'Sala 1', 'link' => 'https://meet.google.com/abc-defg-hij', 'instrucoes' => 'Entre 10 min antes']);
    $this->agenda = app(AgendaApresentacoes::class);
    $this->programar = fn (array $ids, $sala = null, string $inicio = '2026-12-10T09:00', int $dur = 15, int $int = 5, array $extra = []) => $this->actingAs($this->gestor)->post(
        route('gestao.trabalhos.agenda.programar', $this->evento),
        ['trabalhos' => $ids, 'sala_id' => ($sala ?? $this->sala)->id, 'inicio' => $inicio, 'duracao' => $dur, 'intervalo' => $int] + $extra
    );
});

it('gestão cadastra, edita e exclui salas; só links http(s); nome único; sala em uso não exclui', function () {
    $r = fn (array $d) => $this->actingAs($this->gestor)->post(route('gestao.trabalhos.agenda.salas.store', $this->evento), $d);

    $r(['nome' => 'Sala 2', 'link' => 'https://zoom.us/j/123'])->assertSessionHas('success');
    $r(['nome' => 'sala  2', 'link' => 'https://zoom.us/j/999'])->assertSessionHasErrors('nome');
    $r(['nome' => 'Sala 3', 'link' => 'javascript:alert(1)'])->assertSessionHasErrors('link');
    $r(['nome' => 'Sala 4', 'link' => 'ftp://x.com/y'])->assertSessionHasErrors('link');
    expect(SalaApresentacao::count())->toBe(2);

    $s2 = SalaApresentacao::where('nome', 'Sala 2')->first();
    $this->actingAs($this->gestor)->put(route('gestao.trabalhos.agenda.salas.update', [$this->evento, $s2]), ['nome' => 'Sala Dois', 'link' => 'https://zoom.us/j/456', 'ativo' => 0])->assertSessionHas('success');
    expect($s2->fresh()->nome)->toBe('Sala Dois')->and($s2->fresh()->ativo)->toBeFalse();

    ($this->programar)([$this->t1->id])->assertSessionHas('success');
    $this->actingAs($this->gestor)->delete(route('gestao.trabalhos.agenda.salas.destroy', [$this->evento, $this->sala]))->assertSessionHas('error');
    $this->actingAs($this->gestor)->delete(route('gestao.trabalhos.agenda.salas.destroy', [$this->evento, $s2]))->assertSessionHas('success');
    expect(SalaApresentacao::count())->toBe(1);

    // quem não gere trabalhos não acessa
    $this->actingAs($this->av)->get(route('gestao.trabalhos.agenda.index', $this->evento))->assertForbidden();
    $this->actingAs($this->autor)->post(route('gestao.trabalhos.agenda.salas.store', $this->evento), ['nome' => 'X1', 'link' => 'https://a.com'])->assertForbidden();
});

it('programa vários trabalhos na mesma sala em sequência (duração + intervalo, ordem do protocolo)', function () {
    ($this->programar)([$this->t3->id, $this->t1->id, $this->t2->id], null, '2026-12-10T09:00', 15, 5)->assertSessionHas('success');

    $ordenados = Trabalho::whereIn('id', [$this->t1->id, $this->t2->id, $this->t3->id])->orderBy('codigo')->get();
    expect($ordenados->map(fn ($t) => $t->apresentacao_inicio->format('H:i') . '-' . $t->apresentacao_fim->format('H:i'))->all())->toBe(['09:00-09:15', '09:20-09:35', '09:40-09:55'])
        ->and($ordenados->pluck('sala_apresentacao_id')->unique()->all())->toBe([$this->sala->id]);
});

it('barra sobreposição na mesma sala (com opção de ignorar) e marca conflito na tela', function () {
    ($this->programar)([$this->t1->id], null, '2026-12-10T09:00')->assertSessionHas('success');
    ($this->programar)([$this->t2->id], null, '2026-12-10T09:10')->assertSessionHas('error');
    expect($this->t2->fresh()->apresentacao_inicio)->toBeNull();

    ($this->programar)([$this->t2->id], null, '2026-12-10T09:10', 15, 5, ['ignorar_conflito' => 1])->assertSessionHas('success');
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.agenda.index', $this->evento))->assertOk()->assertSee('conflito')->assertSee('Sala 1');

    // mesmo horário em OUTRA sala não conflita
    $s2 = SalaApresentacao::create(['evento_id' => $this->evento->id, 'nome' => 'Sala 2', 'link' => 'https://meet.google.com/zzz']);
    ($this->programar)([$this->t3->id], $s2, '2026-12-10T09:05')->assertSessionHas('success');
});

it('valida a programação: sala de outro evento, sala inativa, sem trabalhos, duração inválida', function () {
    $outro = mkEvento($this->dono);
    $salaOutro = SalaApresentacao::create(['evento_id' => $outro->id, 'nome' => 'Alheia', 'link' => 'https://meet.google.com/x']);
    ($this->programar)([$this->t1->id], $salaOutro)->assertSessionHasErrors('sala_id');
    $this->sala->update(['ativo' => false]);
    ($this->programar)([$this->t1->id])->assertSessionHasErrors('sala_id');
    $this->sala->update(['ativo' => true]);
    ($this->programar)([])->assertSessionHasErrors('trabalhos');
    ($this->programar)([$this->t1->id], null, '2026-12-10T09:00', 0)->assertSessionHasErrors('duracao');
    expect(Trabalho::whereNotNull('apresentacao_inicio')->count())->toBe(0);
});

it('avisa autores e avaliadores por e-mail (sem nome de autor no do avaliador), e só os pendentes no reenvio', function () {
    ($this->programar)([$this->t1->id, $this->t2->id])->assertSessionHas('success');
    expect($this->t1->fresh()->agendaPendenteDeAviso())->toBeTrue();

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.agenda.notificar', $this->evento))->assertSessionHas('success');

    // t1: autora + avaliador; t2: autora (sem avaliador)
    Mail::assertQueued(ApresentacaoAgendadaMail::class, 3);
    Mail::assertQueued(ApresentacaoAgendadaMail::class, fn ($m) => $m->hasTo('autora@x.com') && $m->papel === 'autor' && $m->tipo === 'convite');
    Mail::assertQueued(ApresentacaoAgendadaMail::class, fn ($m) => $m->hasTo('av@x.com') && $m->papel === 'avaliador');
    expect($this->t1->fresh()->agendaPendenteDeAviso())->toBeFalse();

    $html = (new ApresentacaoAgendadaMail($this->t1->fresh(['sala', 'evento']), 'avaliador'))->render();
    expect($html)->toContain('Entrar na sala')->toContain('https://meet.google.com/abc-defg-hij')->toContain($this->t1->codigo)->toContain('Entre 10 min antes')
        ->not->toContain('autora@x.com');

    // nada pendente agora
    Mail::fake();
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.agenda.notificar', $this->evento))->assertSessionHas('error');
    Mail::assertNothingQueued();

    // muda o horário de t1: vira "atualização" e o lembrete é refeito
    ($this->programar)([$this->t1->id], null, '2026-12-11T10:00')->assertSessionHas('success');
    expect($this->t1->fresh()->agendaPendenteDeAviso())->toBeTrue()->and($this->t2->fresh()->agendaPendenteDeAviso())->toBeFalse();
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.agenda.notificar', $this->evento))->assertSessionHas('success');
    Mail::assertQueued(ApresentacaoAgendadaMail::class, fn ($m) => $m->tipo === 'atualizacao' && $m->trabalho->id === $this->t1->id);
    Mail::assertQueued(ApresentacaoAgendadaMail::class, 2);   // autora + avaliador de t1, nada para t2
});

it('lembrete automático sai uma vez, só para quem começa nos próximos 30 min', function () {
    ($this->programar)([$this->t1->id], null, now()->addMinutes(20)->format('Y-m-d\TH:i'))->assertSessionHas('success');
    ($this->programar)([$this->t2->id], null, now()->addHours(5)->format('Y-m-d\TH:i'))->assertSessionHas('success');

    expect($this->agenda->enviarLembretes())->toBe(2);   // autora + avaliador de t1
    Mail::assertQueued(ApresentacaoAgendadaMail::class, fn ($m) => $m->tipo === 'lembrete' && $m->trabalho->id === $this->t1->id);
    Mail::assertNotQueued(ApresentacaoAgendadaMail::class, fn ($m) => $m->trabalho->id === $this->t2->id);

    expect($this->agenda->enviarLembretes())->toBe(0);   // não repete
    $this->artisan('apresentacoes:lembrar')->assertSuccessful();
});

it('botão Entrar na sala: autor, avaliador da apresentação e gestão chegam ao link; estranho e avaliador de outro trabalho não', function () {
    ($this->programar)([$this->t1->id, $this->t2->id], null, now()->addMinutes(10)->format('Y-m-d\TH:i'))->assertSessionHas('success');
    $rota = route('apresentacao.sala', $this->t1);

    foreach ([$this->autor, $this->av, $this->gestor] as $u) {
        $this->actingAs($u)->get($rota)->assertRedirect('https://meet.google.com/abc-defg-hij');
    }
    $this->actingAs($this->estranho)->get($rota)->assertForbidden();
    $this->actingAs($this->avOutro)->get($rota)->assertForbidden();
    $this->actingAs($this->av)->get(route('apresentacao.sala', $this->t2))->assertForbidden();   // avaliador de t1 não de t2
    auth()->logout();
    $this->get($rota)->assertRedirect(route('login'));

    // sem horário ou sala inativa: avisa em vez de erro
    $this->actingAs($this->autor)->from(route('trabalhos.index'))->get(route('apresentacao.sala', $this->t3))->assertRedirect(route('trabalhos.index'))->assertSessionHas('error');
    $this->sala->update(['ativo' => false]);
    $this->actingAs($this->autor)->from(route('trabalhos.index'))->get($rota)->assertSessionHas('error');
});

it('plataforma mostra o botão ao autor (show e Meus Trabalhos) e ao avaliador, sem expor o link nem autores', function () {
    ($this->programar)([$this->t1->id], null, now()->addMinutes(10)->format('Y-m-d\TH:i'))->assertSessionHas('success');
    $rota = route('apresentacao.sala', $this->t1);

    $this->actingAs($this->autor)->get(route('trabalhos.show', $this->t1))->assertOk()->assertSee('Entrar na sala')->assertSee($rota, false)->assertDontSee('meet.google.com');
    $this->actingAs($this->autor)->get(route('trabalhos.index'))->assertOk()->assertSee('Minhas apresentações orais')->assertSee($rota, false);

    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => true, 'avaliacao_cega' => true]);
    $a = ApresentacaoAvaliacao::where('trabalho_id', $this->t1->id)->first();
    $this->actingAs($this->av)->get(route('avaliador.apresentacoes.index'))->assertOk()->assertSee('Entrar na sala')->assertDontSee('meet.google.com');
    $this->actingAs($this->av)->get(route('avaliador.apresentacoes.show', $a))->assertOk()->assertSee('Entrar na sala')->assertSee('Sala 1');

    // trabalho sem horário não mostra nada
    $this->actingAs($this->autor)->get(route('trabalhos.show', $this->t2))->assertOk()->assertDontSee('Entrar na sala');

    // após o fim da apresentação o botão some
    $this->t1->update(['apresentacao_inicio' => now()->subHours(2), 'apresentacao_fim' => now()->subHour()]);
    $this->actingAs($this->autor)->get(route('trabalhos.show', $this->t1))->assertOk()->assertDontSee('Entrar na sala')->assertSee('Encerrada');
});

it('limpar o horário remove sala e horário, e a agenda lista só as orais por padrão', function () {
    ($this->programar)([$this->t1->id])->assertSessionHas('success');
    $this->actingAs($this->gestor)->delete(route('gestao.trabalhos.agenda.limpar', [$this->evento, $this->t1]))->assertSessionHas('success');
    expect($this->t1->fresh()->agendado())->toBeFalsy();

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.agenda.index', $this->evento))->assertOk()->assertSee('Primeiro trabalho')->assertDontSee('Um pôster');
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.agenda.index', [$this->evento, 'todas' => 1]))->assertSee('Um pôster');
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.index', $this->evento))->assertOk()->assertSee('Agenda das orais');
});
