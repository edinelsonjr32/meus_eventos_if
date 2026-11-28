<?php

use App\Models\Atividade;
use App\Models\ChamadaProposta;
use App\Models\Proposta;
use App\Services\PropostaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__ . '/../Helpers/TrabalhosHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->dono = mkUser('admin', 'chefe@ifpa.edu.br');
    $this->evento = mkEvento($this->dono);
    $autor = mkUser('user', 'autor@gmail.com');
    $autor->update(['name' => 'Ana Proponente']);
    $this->atividade = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Oficina', 'tipo' => 'oficina', 'responsavel' => 'Ana Proponente, Convidado Externo',
        'token_frequencia' => \Illuminate\Support\Str::random(10), 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHours(2), 'carga_horaria' => 2]);
    $chamada = ChamadaProposta::create(['evento_id' => $this->evento->id, 'titulo' => 'Chamada', 'slug' => 'chamada-x', 'ativa' => true, 'inicio' => now()->subDay(), 'fim' => now()->addDay(), 'tipos' => ['oficina']]);
    $this->proposta = Proposta::create(['chamada_id' => $chamada->id, 'evento_id' => $this->evento->id, 'user_id' => $autor->id, 'tipo' => 'oficina', 'titulo' => 'Oficina',
        'resumo' => 'Resumo', 'carga_horaria_prevista' => 2, 'vagas_sugeridas' => 20, 'status' => 'aprovada', 'atividade_id' => $this->atividade->id]);
    $this->beto = $this->proposta->apoios()->create(['nome' => 'Beto Ajudante', 'email' => 'beto@gmail.com', 'cpf' => '111.444.777-35', 'funcao' => 'ajudante']);
});

it('mostra a equipe da proposta na edição da atividade e na programação do painel', function () {
    $this->actingAs($this->dono)->get(route('admin.atividades.edit', $this->atividade))->assertOk()
        ->assertSee('Outros ministrantes e ajudantes')->assertSee('Beto Ajudante')
        ->assertSee(route('admin.propostas.apoio-funcao', [$this->evento->id, $this->beto->id]), false);

    $this->get(route('admin.eventos.show', $this->evento->id))->assertOk()->assertSee('Ajudantes: Beto Ajudante');
});

it('muda a atribuição sem apagar os nomes digitados na atividade', function () {
    $url = route('admin.propostas.apoio-funcao', [$this->evento->id, $this->beto->id]);

    $this->actingAs($this->dono)->post($url, ['funcao' => 'ministrante'])->assertRedirect();
    expect($this->atividade->refresh()->responsavel)->toBe('Ana Proponente, Convidado Externo, Beto Ajudante');

    $this->post($url, ['funcao' => 'ajudante'])->assertRedirect();
    expect($this->atividade->refresh()->responsavel)->toBe('Ana Proponente, Convidado Externo');
});

it('não mostra o bloco em atividade criada sem proposta', function () {
    $this->proposta->update(['atividade_id' => null]);

    $this->actingAs($this->dono)->get(route('admin.atividades.edit', $this->atividade))->assertOk()->assertDontSee('Outros ministrantes e ajudantes');
});
