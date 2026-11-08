<?php

use App\Models\Inscricao;
use App\Models\Participante;

beforeEach(function () {
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
});

function inscrever($ctx, string $nome, string $cpf, bool $checkin = false, ?string $email = null): Participante
{
    $p = Participante::create(['nome_completo' => $nome, 'cpf' => $cpf, 'email' => $email ?? strtolower(str_replace(' ', '.', $nome)) . '@x.com', 'tipo_vinculo' => 'externo', 'matricula' => null]);
    Inscricao::create(['evento_id' => $ctx->evento->id, 'participante_id' => $p->id, 'data_inscricao' => now(), 'checkin_at' => $checkin ? now()->subHour() : null]);

    return $p;
}

it('o check-in só vale uma vez: repetir não muda a hora, não conta de novo e avisa que já foi feito', function () {
    $p = inscrever($this, 'Ana Chegou', '111.444.777-35');
    $url = route('admin.eventos.credenciamento.checkin', [$this->evento, $p]);
    $this->actingAs($this->dono);

    $a = $this->postJson($url)->assertOk()->assertJsonPath('novo', true)->assertJsonPath('ja_realizado', false)->assertJsonPath('credenciados', 1)->assertJsonPath('pendentes', 0)->assertJsonPath('message', 'Check-in realizado!');
    $hora = Inscricao::where('participante_id', $p->id)->first()->checkin_at;

    $this->travel(30)->minutes();
    $b = $this->postJson($url)->assertOk()->assertJsonPath('novo', false)->assertJsonPath('ja_realizado', true)->assertJsonPath('credenciados', 1)->assertJsonPath('total', 1);
    expect($b->json('message'))->toContain('já havia feito check-in')->toContain('Ana Chegou')
        ->and($b->json('checkin_at'))->toBe($a->json('checkin_at'))
        ->and(Inscricao::where('participante_id', $p->id)->first()->checkin_at->equalTo($hora))->toBeTrue();   // hora original preservada

    $this->postJson($url)->assertJsonPath('credenciados', 1);   // e quantas vezes repetir
});

it('a etiqueta credencia uma vez e não altera a hora de quem já chegou', function () {
    $pendente = inscrever($this, 'Sem checkin', '529.982.247-25');
    $feito = inscrever($this, 'Já chegou', '168.995.350-09', true);
    $horaFeito = Inscricao::where('participante_id', $feito->id)->first()->checkin_at;
    $this->actingAs($this->dono);

    $this->get(route('admin.eventos.credenciamento.etiqueta', [$this->evento, $pendente]))->assertOk();
    $this->get(route('admin.eventos.credenciamento.etiqueta', [$this->evento, $feito]))->assertOk();

    expect(Inscricao::where('participante_id', $pendente->id)->first()->checkin_at)->not->toBeNull()
        ->and(Inscricao::where('participante_id', $feito->id)->first()->checkin_at->equalTo($horaFeito))->toBeTrue();
    $this->getJson(route('admin.eventos.credenciamento.stats', $this->evento))->assertOk()->assertJson(['total' => 2, 'credenciados' => 2, 'pendentes' => 0]);
});

it('participante fora do evento não faz check-in; só quem administra o evento', function () {
    $p = inscrever($this, 'Dentro', '111.444.777-35');
    $outroEvento = mkEvento($this->dono);
    $this->actingAs($this->dono)->postJson(route('admin.eventos.credenciamento.checkin', [$outroEvento, $p]))->assertNotFound();

    $this->actingAs(mkUser('admin'))->postJson(route('admin.eventos.credenciamento.checkin', [$this->evento, $p]))->assertForbidden();
    $this->getJson(route('admin.eventos.credenciamento.stats', $this->evento))->assertForbidden();
    $this->get(route('admin.eventos.credenciamento.lista', $this->evento))->assertForbidden();
    expect(Inscricao::where('participante_id', $p->id)->first()->checkin_at)->toBeNull();
});

it('a tela da recepção não conta sozinha: contadores vêm do servidor e o botão fica protegido contra duplo clique', function () {
    inscrever($this, 'Ana', '111.444.777-35', true);
    $html = $this->actingAs($this->dono)->get(route('admin.eventos.credenciamento.index', $this->evento))->assertOk()->getContent();

    expect($html)->toContain('atualizarStats')->toContain('item.carregando')->toContain('data.credenciados')->toContain(route('admin.eventos.credenciamento.stats', $this->evento))
        ->not->toContain('this.stats.credenciados++');
});

it('a lista mostra TODOS os inscritos com o status (check-in ou pendente) e os totais', function () {
    inscrever($this, 'Ana Feita', '111.444.777-35', true);
    inscrever($this, 'Bia Pendente', '529.982.247-25');
    inscrever($this, 'Caio Feito', '168.995.350-09', true);
    $outro = mkEvento($this->dono);
    Inscricao::create(['evento_id' => $outro->id, 'participante_id' => Participante::create(['nome_completo' => 'De outro evento', 'cpf' => '390.533.447-05', 'email' => 'o@x.com', 'tipo_vinculo' => 'externo'])->id, 'data_inscricao' => now()]);

    $html = $this->actingAs($this->dono)->get(route('admin.eventos.credenciamento.lista', $this->evento))->assertOk()
        ->assertSee('Lista de inscritos')->assertSee('Ana Feita')->assertSee('Bia Pendente')->assertSee('Caio Feito')->assertDontSee('De outro evento')
        ->assertSee('Check-in feito')->assertSee('Pendente')->getContent();
    expect($html)->toContain('credenciados: 2')->toContain('pendentes: 1')->toContain('total: 3')->toContain('Fazer check-in');
    // ordem alfabética
    expect(strpos($html, 'Ana Feita'))->toBeLessThan(strpos($html, 'Bia Pendente'))->and(strpos($html, 'Bia Pendente'))->toBeLessThan(strpos($html, 'Caio Feito'));
});

it('a lista filtra por status e busca por nome, e-mail e CPF (com ou sem máscara)', function () {
    inscrever($this, 'Ana Feita', '111.444.777-35', true, 'ana@feita.com');
    inscrever($this, 'Bia Pendente', '529.982.247-25');
    $this->actingAs($this->dono);

    $this->get(route('admin.eventos.credenciamento.lista', [$this->evento, 'status' => 'checkin']))->assertSee('Ana Feita')->assertDontSee('Bia Pendente');
    $this->get(route('admin.eventos.credenciamento.lista', [$this->evento, 'status' => 'pendente']))->assertSee('Bia Pendente')->assertDontSee('Ana Feita');
    $this->get(route('admin.eventos.credenciamento.lista', [$this->evento, 'status' => 'qualquer']))->assertSee('Ana Feita')->assertSee('Bia Pendente');   // valor inválido = todos

    foreach (['bia', 'bia.pendente@x.com', '529.982.247-25', '52998224725', '%_'] as $q) {
        $r = $this->get(route('admin.eventos.credenciamento.lista', [$this->evento, 'q' => $q]));
        $q === '%_' ? $r->assertDontSee('Bia Pendente')->assertDontSee('Ana Feita') : $r->assertSee('Bia Pendente')->assertDontSee('Ana Feita');
    }
    $this->get(route('admin.eventos.credenciamento.lista', [$this->evento, 'status' => 'checkin', 'q' => 'bia']))->assertDontSee('Bia Pendente')->assertSee('Nenhum inscrito');
});

it('a lista pagina em Bootstrap (50 por página) e mantém filtros nos links', function () {
    foreach (range(1, 53) as $n) {
        inscrever($this, sprintf('Pessoa %02d', $n), sprintf('%03d.%03d.%03d-%02d', $n, $n, $n, $n), $n % 2 === 0, "p{$n}@x.com");
    }
    $html = $this->actingAs($this->dono)->get(route('admin.eventos.credenciamento.lista', $this->evento))->assertOk()->getContent();
    expect($html)->toContain('class="pagination"')->toContain('Mostrando')->toContain('Pessoa 50')->not->toContain('Pessoa 51');

    $p2 = $this->get(route('admin.eventos.credenciamento.lista', [$this->evento, 'page' => 2]))->assertOk()->getContent();
    expect($p2)->toContain('Pessoa 51')->toContain('Pessoa 53')->not->toContain('Pessoa 01');
    $this->get(route('admin.eventos.credenciamento.lista', [$this->evento, 'q' => 'Pessoa']))->assertSee('page=2', false)->assertSee('q=Pessoa', false);   // filtros preservados na paginação
});

it('exporta a lista em CSV com o status, respeitando o filtro', function () {
    inscrever($this, 'Ana Feita', '111.444.777-35', true);
    inscrever($this, 'Bia Pendente', '529.982.247-25');
    $this->actingAs($this->dono);

    $todos = $this->get(route('admin.eventos.credenciamento.lista.exportar', $this->evento))->assertOk();
    expect($todos->headers->get('content-type'))->toContain('text/csv')->and($todos->streamedContent())->toContain('Ana Feita')->toContain('Bia Pendente')->toContain('Check-in')->toContain('Pendente');

    $so = $this->get(route('admin.eventos.credenciamento.lista.exportar', [$this->evento, 'status' => 'pendente']))->streamedContent();
    expect($so)->toContain('Bia Pendente')->not->toContain('Ana Feita');
});

it('a recepção tem o atalho para a lista de inscritos', function () {
    $this->actingAs($this->dono)->get(route('admin.eventos.credenciamento.index', $this->evento))->assertOk()->assertSee('Lista de inscritos')->assertSee(route('admin.eventos.credenciamento.lista', $this->evento), false);
});

// ---------- Papel "Recepção" ----------
function recepcionista($ctx, $evento = null)
{
    $u = mkUser('user', 'recep' . uniqid() . '@x.com');
    mkPapel($u, $evento ?? $ctx->evento, 'recepcao');

    return $u;
}

it('o dono designa o papel Recepção a um usuário comum pela tela de papéis', function () {
    $u = mkUser('user', 'nova.recepcao@x.com');

    $this->actingAs($this->dono)->get(route('admin.eventos.papeis.index', $this->evento->id))->assertOk()->assertSee('Recepção (check-in e credenciamento)');
    $this->post(route('admin.eventos.papeis.store', $this->evento->id), ['email' => 'nova.recepcao@x.com', 'papel' => 'recepcao'])->assertSessionHasNoErrors()->assertSessionHas('success');

    expect(\Illuminate\Support\Facades\DB::table('evento_user')->where(['evento_id' => $this->evento->id, 'user_id' => $u->id, 'role' => 'recepcao'])->exists())->toBeTrue();
    $this->get(route('admin.eventos.papeis.index', $this->evento->id))->assertSee('nova.recepcao@x.com')->assertSee('Recepção');
});

it('quem tem o papel Recepção faz check-in, credenciamento, etiqueta, cadastro na hora e usa a lista de inscritos', function () {
    $p = inscrever($this, 'Pendente', '111.444.777-35');
    $r = recepcionista($this);
    $this->actingAs($r);

    $this->get(route('admin.eventos.credenciamento.index', $this->evento))->assertOk()->assertSee('Credenciamento');
    $this->getJson(route('admin.eventos.credenciamento.search', [$this->evento, 'q' => 'Pend']))->assertOk()->assertJsonCount(1);
    $this->getJson(route('admin.eventos.credenciamento.stats', $this->evento))->assertOk()->assertJson(['total' => 1, 'credenciados' => 0]);
    $this->postJson(route('admin.eventos.credenciamento.checkin', [$this->evento, $p]))->assertOk()->assertJsonPath('novo', true);
    $this->get(route('admin.eventos.credenciamento.etiqueta', [$this->evento, $p]))->assertOk();
    $this->get(route('admin.eventos.credenciamento.lista', $this->evento))->assertOk()->assertSee('Pendente')->assertSee('Check-in feito');
    $this->get(route('admin.eventos.credenciamento.lista.exportar', $this->evento))->assertOk();
    $this->get(route('admin.eventos.credenciamento.create', $this->evento))->assertOk();
    $this->post(route('admin.eventos.credenciamento.storeManual', $this->evento), ['nome_completo' => 'Na hora', 'cpf' => '529.982.247-25', 'email' => 'nh@x.com', 'tipo_vinculo' => 'externo'])->assertRedirect();
    expect(Inscricao::where('evento_id', $this->evento->id)->whereNotNull('checkin_at')->count())->toBe(2);
});

it('a Recepção não acessa o resto da administração nem outros eventos', function () {
    $p = inscrever($this, 'Alguém', '111.444.777-35');
    $outro = mkEvento($this->dono);
    $r = recepcionista($this);
    $this->actingAs($r);

    // administração em geral: não
    $this->get(route('admin.eventos.show', $this->evento->id))->assertRedirect(route('inscrito.dashboard'));
    $this->get(route('admin.eventos.papeis.index', $this->evento->id))->assertRedirect(route('inscrito.dashboard'));
    $this->get(route('admin.dashboard'))->assertRedirect(route('inscrito.dashboard'));
    $this->get(route('gestao.trabalhos.index', $this->evento))->assertForbidden();

    // outro evento: não (check-in, lista, busca)
    $this->get(route('admin.eventos.credenciamento.index', $outro))->assertForbidden();
    $this->get(route('admin.eventos.credenciamento.lista', $outro))->assertForbidden();
    $this->getJson(route('admin.eventos.credenciamento.search', [$outro, 'q' => 'a']))->assertForbidden();
    $this->postJson(route('admin.eventos.credenciamento.checkin', [$outro, $p]))->assertForbidden();
});

it('usuário comum, avaliador e gestor de trabalhos NÃO têm acesso à recepção; revogar o papel tira o acesso', function () {
    $comum = mkUser('user', 'comum@x.com');
    $avaliador = mkUser('user', 'av@x.com'); mkPapel($avaliador, $this->evento, 'avaliador');
    $gestor = mkUser('user', 'gt@x.com'); mkPapel($gestor, $this->evento, 'gestor_trabalhos');
    foreach ([$comum, $avaliador, $gestor] as $u) {
        $this->actingAs($u)->get(route('admin.eventos.credenciamento.index', $this->evento))->assertForbidden();
        $this->getJson(route('admin.eventos.credenciamento.stats', $this->evento))->assertForbidden();
    }

    $r = recepcionista($this);
    $this->actingAs($r)->get(route('admin.eventos.credenciamento.index', $this->evento))->assertOk();
    \Illuminate\Support\Facades\DB::table('evento_user')->where(['evento_id' => $this->evento->id, 'user_id' => $r->id])->delete();
    $this->get(route('admin.eventos.credenciamento.index', $this->evento))->assertForbidden();
});

it('a Recepção tem a página inicial com os seus eventos e o item de menu; administrador não precisa dele', function () {
    $p = inscrever($this, 'Ana', '111.444.777-35', true);
    inscrever($this, 'Bia', '529.982.247-25');
    $outro = mkEvento($this->dono);   // evento em que ela NÃO é da recepção
    $r = recepcionista($this);

    $html = $this->actingAs($r)->get(route('recepcao.index'))->assertOk()->assertSee('Recepção')->assertSee($this->evento->titulo)->assertDontSee($outro->titulo)
        ->assertSee('1 check-in')->assertSee('1 pendentes')->assertSee(route('admin.eventos.credenciamento.index', $this->evento), false)->assertSee(route('admin.eventos.credenciamento.lista', $this->evento), false)->getContent();
    expect($html)->toContain(route('recepcao.index'));   // item de menu

    // o botão de voltar da recepção leva à página da recepção (não à administração)
    $this->get(route('admin.eventos.credenciamento.index', $this->evento))->assertSee('href="' . route('recepcao.index') . '"', false)->assertDontSee('href="' . route('admin.eventos.show', $this->evento->id) . '"', false);

    // sem papel nenhum: página vazia com a orientação
    $this->actingAs(mkUser('user', 'sem@x.com'))->get(route('recepcao.index'))->assertOk()->assertSee('ainda não foi designado');
    // o administrador volta ao evento, como antes
    $this->actingAs($this->dono)->get(route('admin.eventos.credenciamento.index', $this->evento))->assertSee('href="' . route('admin.eventos.show', $this->evento->id) . '"', false);
});

it('co-administrador continua credenciando e o admin de outro evento não', function () {
    $co = mkUser('admin', 'co@x.com'); mkPapel($co, $this->evento, 'editor');
    $this->actingAs($co)->get(route('admin.eventos.credenciamento.index', $this->evento))->assertOk();
    $this->actingAs(mkUser('admin', 'outro@x.com'))->get(route('admin.eventos.credenciamento.index', $this->evento))->assertForbidden();
});
