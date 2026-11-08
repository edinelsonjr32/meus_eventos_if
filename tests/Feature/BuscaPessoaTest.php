<?php

use App\Models\Equipe;
use App\Models\Participante;

beforeEach(function () {
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->url = fn (array $q) => route('admin.eventos.buscar-pessoas', $this->evento->id) . '?' . http_build_query($q);
});

it('busca usuários por nome ou e-mail no formato do Select2', function () {
    mkUser('user', 'maria.silva@exemplo.com')->update(['name' => 'Maria Silva']);
    mkUser('user', 'joao@exemplo.com')->update(['name' => 'João Souza']);

    $r = $this->actingAs($this->dono)->getJson(($this->url)(['tipo' => 'usuario', 'q' => 'maria']))->assertOk();
    expect($r->json('results'))->toHaveCount(1)->and($r->json('results.0.id'))->toBe('maria.silva@exemplo.com')
        ->and($r->json('results.0.text'))->toContain('Maria Silva')->and($r->json('pagination.more'))->toBeFalse();

    $this->actingAs($this->dono)->getJson(($this->url)(['tipo' => 'usuario', 'q' => 'souza']))->assertJsonCount(1, 'results');
});

it('busca participantes por nome, CPF (com ou sem máscara) e esconde quem já está na equipe', function () {
    $a = Participante::create(['nome_completo' => 'Ana Lima', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_vinculo' => 'externo']);
    $b = Participante::create(['nome_completo' => 'Bruno Lima', 'cpf' => '529.982.247-25', 'email' => 'bruno@x.com', 'tipo_vinculo' => 'externo']);

    foreach (['lima' => 2, '11144477735' => 1, '444.777' => 1, 'bruno@' => 1] as $q => $n) {
        $this->actingAs($this->dono)->getJson(($this->url)(['tipo' => 'participante', 'q' => $q]))->assertJsonCount($n, 'results');
    }
    expect($this->actingAs($this->dono)->getJson(($this->url)(['tipo' => 'participante', 'q' => 'ana']))->json('results.0.id'))->toBe('111.444.777-35');

    Equipe::create(['evento_id' => $this->evento->id, 'participante_id' => $a->id, 'funcao' => 'Monitor', 'carga_horaria_trabalho' => 10]);
    $this->actingAs($this->dono)->getJson(($this->url)(['tipo' => 'participante', 'q' => 'lima']))->assertJsonCount(1, 'results');
});

it('não lista pessoas anonimizadas e pagina os resultados', function () {
    foreach (range(1, 25) as $i) {
        Participante::create(['nome_completo' => sprintf('Pessoa %02d', $i), 'cpf' => 'X' . $i, 'email' => "p{$i}@x.com", 'tipo_vinculo' => 'externo']);
    }
    Participante::create(['nome_completo' => 'Titular anonimizado', 'cpf' => 'ANON-1', 'email' => 'a@anonimizado.invalid', 'tipo_vinculo' => 'externo']);

    $p1 = $this->actingAs($this->dono)->getJson(($this->url)(['tipo' => 'participante', 'q' => 'pessoa']))->json();
    expect($p1['results'])->toHaveCount(20)->and($p1['pagination']['more'])->toBeTrue();
    $p2 = $this->actingAs($this->dono)->getJson(($this->url)(['tipo' => 'participante', 'q' => 'pessoa', 'page' => 2]))->json();
    expect($p2['results'])->toHaveCount(5)->and($p2['pagination']['more'])->toBeFalse();
    $this->actingAs($this->dono)->getJson(($this->url)(['tipo' => 'participante', 'q' => 'anonimizado']))->assertJsonCount(0, 'results');
});

it('só quem administra o evento pode buscar pessoas', function () {
    $this->getJson(($this->url)(['tipo' => 'usuario']))->assertUnauthorized();
    $this->actingAs(mkUser())->getJson(($this->url)(['tipo' => 'usuario']))->assertRedirect();  // perfil comum não entra na área administrativa
    $this->actingAs($this->dono)->getJson(($this->url)(['tipo' => 'outro']))->assertStatus(422);
});

it('as telas de equipe e de papéis usam o campo com busca', function () {
    $this->actingAs($this->dono)->get(route('admin.eventos.equipe.index', $this->evento->id))->assertOk()->assertSee('js-select-pessoa', false)->assertSee('data-tipo="participante"', false);
    $this->actingAs($this->dono)->get(route('admin.eventos.papeis.index', $this->evento->id))->assertOk()->assertSee('data-tipo="usuario"', false);
});
