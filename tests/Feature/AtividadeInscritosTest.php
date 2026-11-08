<?php

use App\Models\Atividade;
use App\Models\Frequencia;
use App\Models\Participante;

beforeEach(function () {
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->atividade = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Palestra', 'tipo' => 'palestra', 'data_inicio' => now(), 'data_fim' => now()->addHours(2), 'carga_horaria' => 2, 'vagas' => 10, 'token_frequencia' => uniqid()]);
    $this->p = Participante::create(['nome_completo' => 'Pessoa Inscrita', 'cpf' => '111.444.777-35', 'email' => 'p@x.com', 'tipo_vinculo' => 'externo']);
    $this->atividade->inscritos()->attach($this->p->id);
    $this->url = route('admin.atividades.inscritos.destroy', [$this->atividade->id, $this->p->id]);
});

it('mostra o botão de remover só para inscritos sem presença', function () {
    $this->actingAs($this->dono)->get(route('admin.atividades.participantes', $this->atividade->id))->assertOk()->assertSee('Remover da atividade')->assertSee($this->url, false);

    Frequencia::create(['participante_id' => $this->p->id, 'atividade_id' => $this->atividade->id, 'data_registro' => now(), 'hash_validacao' => 'H1', 'tipo_participacao' => 'ouvinte']);
    $this->actingAs($this->dono)->get(route('admin.atividades.participantes', $this->atividade->id))->assertDontSee($this->url, false);
});

it('remove da atividade quem se inscreveu e ainda não registrou presença', function () {
    $this->actingAs($this->dono)->delete($this->url)->assertSessionHas('success');

    expect($this->atividade->inscritos()->where('participantes.id', $this->p->id)->exists())->toBeFalse()
        ->and(Participante::find($this->p->id))->not->toBeNull();   // o cadastro da pessoa permanece
    $this->actingAs($this->dono)->get(route('admin.atividades.participantes', $this->atividade->id))->assertDontSee('Dar Presença');   // (o nome ainda aparece na mensagem de sucesso)
});

it('não remove quem já registrou presença nem quem não está inscrito', function () {
    Frequencia::create(['participante_id' => $this->p->id, 'atividade_id' => $this->atividade->id, 'data_registro' => now(), 'hash_validacao' => 'H2', 'tipo_participacao' => 'ouvinte']);
    $this->actingAs($this->dono)->delete($this->url)->assertSessionHas('error');
    expect($this->atividade->inscritos()->count())->toBe(1);

    $outro = Participante::create(['nome_completo' => 'Outro', 'cpf' => '529.982.247-25', 'email' => 'o@x.com', 'tipo_vinculo' => 'externo']);
    $this->actingAs($this->dono)->delete(route('admin.atividades.inscritos.destroy', [$this->atividade->id, $outro->id]))->assertSessionHas('error');
});

it('só quem administra o evento remove inscritos', function () {
    $this->actingAs(mkUser())->delete($this->url)->assertRedirect();   // perfil comum não entra na área administrativa
    expect($this->atividade->inscritos()->count())->toBe(1);

    $outroAdmin = mkUser('admin');   // admin de outro evento
    $this->actingAs($outroAdmin)->delete($this->url)->assertForbidden();
    expect($this->atividade->inscritos()->count())->toBe(1);
});
