<?php

use App\Models\Atividade;
use App\Models\DemandaVoluntario;
use App\Models\Local;

beforeEach(function () {
    $this->admin = mkUser('admin');
    $this->evento = mkEvento($this->admin);
    $this->actingAs($this->admin);
});

function novoLocal($t, array $extra = [])
{
    return $t->post(route('admin.locais.store', $t->evento), $extra + ['nome' => 'Laboratório 1', 'tipo' => 'laboratorio']);
}

it('cadastra local e barra duplicados sem diferenciar maiúsculas, acentos ou espaços', function () {
    novoLocal($this)->assertSessionHas('success');
    expect(Local::first()->nome_normalizado)->toBe('laboratorio 1');

    foreach (['LABORATÓRIO 1', 'laboratorio   1', ' Laboratorio 1 '] as $nome) {
        novoLocal($this, ['nome' => $nome])->assertSessionHasErrors('nome');
    }
    expect(Local::count())->toBe(1);

    // outro evento pode ter o mesmo nome
    $outro = mkEvento($this->admin);
    $this->post(route('admin.locais.store', $outro), ['nome' => 'Laboratório 1', 'tipo' => 'sala'])->assertSessionHas('success');
    expect(Local::count())->toBe(2);
});

it('valida os campos do local', function () {
    novoLocal($this, ['nome' => ''])->assertSessionHasErrors('nome');
    novoLocal($this, ['nome' => 'x'])->assertSessionHasErrors('nome');
    novoLocal($this, ['nome' => 'Sala', 'tipo' => 'castelo'])->assertSessionHasErrors('tipo');
    novoLocal($this, ['nome' => 'Sala', 'capacidade' => 0])->assertSessionHasErrors('capacidade');
    expect(Local::count())->toBe(0);
});

it('renomear o local atualiza o texto exibido nas atividades; em uso não pode ser excluído', function () {
    novoLocal($this);
    $l = Local::first();
    $a = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'A', 'tipo' => 'palestra', 'local_id' => $l->id, 'carga_horaria' => 2, 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHours(2), 'token_frequencia' => 'tok1234567']);
    expect($a->fresh()->local)->toBe('Laboratório 1');

    $this->put(route('admin.locais.update', [$this->evento, $l]), ['nome' => 'Lab Maker', 'bloco' => 'Bloco C', 'tipo' => 'laboratorio'])->assertSessionHas('success');
    expect($a->fresh()->local)->toBe('Lab Maker (Bloco C)');

    $this->delete(route('admin.locais.destroy', [$this->evento, $l]))->assertSessionHas('error');
    expect(Local::count())->toBe(1);

    $a->delete();
    $this->delete(route('admin.locais.destroy', [$this->evento, $l]))->assertSessionHas('success');
    expect(Local::count())->toBe(0);
});

it('atividade usa o local escolhido, barra conflito de horário e capacidade, e permite ignorar', function () {
    novoLocal($this, ['capacidade' => 20]);
    $l = Local::first();
    $inicio = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
    $post = fn (array $d) => $this->post(route('admin.eventos.atividades.store', $this->evento), $d + ['titulo' => 'Oficina', 'tipo' => 'oficina', 'local_id' => $l->id, 'data_inicio' => $inicio, 'carga_horaria' => 2, 'vagas' => 15]);

    $post([])->assertSessionHas('success');
    $a = Atividade::first();
    expect($a->local_id)->toBe($l->id)->and($a->local)->toBe('Laboratório 1');

    $post(['titulo' => 'Outra'])->assertSessionHasErrors('local_id');
    expect(Atividade::count())->toBe(1);

    $post(['titulo' => 'Grande', 'data_inicio' => now()->addDays(2)->setTime(9, 0)->format('Y-m-d\TH:i'), 'vagas' => 50])->assertSessionHasErrors('local_id');
    $post(['titulo' => 'Forçada', 'ignorar_conflito' => 1])->assertSessionHas('success');
    expect(Atividade::count())->toBe(2);

    // local de outro evento é recusado; edição preserva o próprio horário sem acusar conflito
    $outroLocal = Local::create(['evento_id' => mkEvento($this->admin)->id, 'nome' => 'Alheio', 'tipo' => 'sala']);
    $post(['titulo' => 'X', 'local_id' => $outroLocal->id])->assertSessionHasErrors('local_id');
    Atividade::where('titulo', 'Forçada')->delete();
    $this->put(route('admin.atividades.update', $a), ['titulo' => 'Oficina editada', 'tipo' => 'oficina', 'local_id' => $l->id, 'data_inicio' => $inicio, 'carga_horaria' => 2, 'vagas' => 15])->assertSessionHasNoErrors();
});

it('telas de locais e mapa abrem, mostram conflito e atividades sem local; usuário comum é barrado', function () {
    novoLocal($this);
    $l = Local::first();
    $base = ['evento_id' => $this->evento->id, 'tipo' => 'palestra', 'carga_horaria' => 2, 'local_id' => $l->id];
    $dia = now()->addDay()->setTime(9, 0);
    Atividade::create($base + ['titulo' => 'Palestra A', 'data_inicio' => $dia, 'data_fim' => $dia->copy()->addHours(2), 'token_frequencia' => 'tokA123456']);
    Atividade::create($base + ['titulo' => 'Palestra B', 'data_inicio' => $dia->copy()->addHour(), 'data_fim' => $dia->copy()->addHours(3), 'token_frequencia' => 'tokB123456']);
    Atividade::create(['titulo' => 'Sem local', 'evento_id' => $this->evento->id, 'tipo' => 'palestra', 'carga_horaria' => 1, 'data_inicio' => $dia, 'data_fim' => $dia->copy()->addHour(), 'token_frequencia' => 'tokC123456']);

    $r = $this->get(route('admin.locais.index', $this->evento))->assertOk();
    $r->assertSee('Palestra A')->assertSee('Palestra B')->assertSee('mapa-barra conflito', false)->assertSee('Sem local definido')->assertSee('sem local cadastrado');

    $this->get(route('admin.eventos.show', $this->evento->id))->assertOk()->assertSee('Locais e Mapa');

    $this->actingAs(mkUser('user'))->get(route('admin.locais.index', $this->evento))->assertRedirect();
});

it('demanda de voluntários escolhe local cadastrado', function () {
    novoLocal($this);
    $l = Local::first();
    $this->post(route('admin.voluntarios.demandas.store', $this->evento), ['titulo' => 'Recepção', 'carga_horaria' => 2, 'local_id' => $l->id])->assertSessionHasNoErrors();
    expect(DemandaVoluntario::first()->local)->toBe('Laboratório 1');
    $this->post(route('admin.voluntarios.demandas.store', $this->evento), ['titulo' => 'X', 'carga_horaria' => 2, 'local_id' => 9999])->assertSessionHasErrors('local_id');
});
