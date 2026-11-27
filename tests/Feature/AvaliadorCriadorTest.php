<?php

use App\Models\Evento;
use App\Services\TrabalhoWorkflow;
use Illuminate\Support\Facades\DB;

it('o criador do evento pode ser designado como avaliador e passa a aparecer na lista de avaliadores', function () {
    $dono = mkUser('admin', 'dono@x.com');
    $evento = mkEvento($dono);
    $this->actingAs($dono);

    $this->post(route('admin.eventos.papeis.store', $evento), ['email' => 'dono@x.com', 'papel' => 'avaliador'])->assertSessionHas('success');
    expect(app(TrabalhoWorkflow::class)->avaliadoresDoEvento($evento)->pluck('id')->all())->toBe([$dono->id]);

    // repetir avisa que já tem o papel
    $this->post(route('admin.eventos.papeis.store', $evento), ['email' => 'dono@x.com', 'papel' => 'avaliador'])->assertSessionHas('warning');

    // os outros papéis continuam desnecessários para o criador
    $this->post(route('admin.eventos.papeis.store', $evento), ['email' => 'dono@x.com', 'papel' => Evento::PAPEL_GESTOR_TRABALHOS])->assertSessionHas('error');
    expect(DB::table('evento_user')->where('evento_id', $evento->id)->where('user_id', $dono->id)->count())->toBe(1);

    // e o papel pode ser removido
    $this->delete(route('admin.eventos.papeis.destroy', [$evento, $dono, 'avaliador']))->assertSessionHas('success');
    expect(app(TrabalhoWorkflow::class)->avaliadoresDoEvento($evento))->toBeEmpty();
});
