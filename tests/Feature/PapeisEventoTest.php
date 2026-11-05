<?php

use App\Models\Evento;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->dono = User::factory()->create();
    $this->dono->forceFill(['role' => 'admin'])->save();
    $this->evento = Evento::create([
        'titulo' => 'Ev', 'slug' => 'ev-' . uniqid(), 'data_inicio' => now(), 'data_fim' => now()->addDay(),
        'local' => 'X', 'criado_por' => $this->dono->id,
    ]);
    $this->pessoa = User::factory()->create();
});

it('designa gestor de trabalhos e avaliador, inclusive os dois papéis para a mesma pessoa', function () {
    foreach (['gestor_trabalhos', 'avaliador'] as $papel) {
        $this->actingAs($this->dono)
            ->post(route('admin.eventos.papeis.store', $this->evento), ['email' => $this->pessoa->email, 'papel' => $papel])
            ->assertSessionHas('success');
    }

    expect(DB::table('evento_user')->where('user_id', $this->pessoa->id)->count())->toBe(2)
        ->and($this->pessoa->papelNoEvento($this->evento, [Evento::PAPEL_AVALIADOR]))->toBeTrue()
        ->and($this->pessoa->papelNoEvento($this->evento, [Evento::PAPEL_GESTOR_TRABALHOS]))->toBeTrue();
});

it('não duplica o mesmo papel', function () {
    $dados = ['email' => $this->pessoa->email, 'papel' => 'avaliador'];
    $this->actingAs($this->dono)->post(route('admin.eventos.papeis.store', $this->evento), $dados);
    $this->actingAs($this->dono)->post(route('admin.eventos.papeis.store', $this->evento), $dados)->assertSessionHas('warning');

    expect(DB::table('evento_user')->count())->toBe(1);
});

it('rejeita e-mail inexistente, papel inválido e co-admin sem perfil admin', function () {
    $this->actingAs($this->dono);
    $this->post(route('admin.eventos.papeis.store', $this->evento), ['email' => 'nao@existe.com', 'papel' => 'avaliador'])->assertSessionHas('error');
    $this->post(route('admin.eventos.papeis.store', $this->evento), ['email' => $this->pessoa->email, 'papel' => 'chefe'])->assertSessionHasErrors('papel');
    $this->post(route('admin.eventos.papeis.store', $this->evento), ['email' => $this->pessoa->email, 'papel' => 'editor'])->assertSessionHas('error');
    expect(DB::table('evento_user')->count())->toBe(0);
});

it('remove um papel sem afetar o outro', function () {
    foreach (['gestor_trabalhos', 'avaliador'] as $papel) {
        DB::table('evento_user')->insert(['evento_id' => $this->evento->id, 'user_id' => $this->pessoa->id, 'role' => $papel, 'created_at' => now(), 'updated_at' => now()]);
    }

    $this->actingAs($this->dono)
        ->delete(route('admin.eventos.papeis.destroy', [$this->evento, $this->pessoa, 'avaliador']))->assertSessionHas('success');

    expect(DB::table('evento_user')->pluck('role')->all())->toBe(['gestor_trabalhos']);
});

it('policy: gestor de trabalhos e avaliador não gerenciam o evento', function () {
    foreach (['gestor_trabalhos', 'avaliador'] as $papel) {
        DB::table('evento_user')->insert(['evento_id' => $this->evento->id, 'user_id' => $this->pessoa->id, 'role' => $papel, 'created_at' => now(), 'updated_at' => now()]);
    }

    expect($this->pessoa->can('gerenciar', $this->evento))->toBeFalse()
        ->and($this->pessoa->can('gerirTrabalhos', $this->evento))->toBeTrue()
        ->and($this->pessoa->can('avaliar', $this->evento))->toBeTrue();
});
