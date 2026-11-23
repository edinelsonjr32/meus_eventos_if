<?php

use App\Models\Atividade;
use App\Models\Frequencia;
use App\Models\Participante;

beforeEach(function () {
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->atividade = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Final de handebol', 'tipo' => 'jogo', 'data_inicio' => now(), 'data_fim' => now()->addHours(2), 'carga_horaria' => 2, 'gera_certificado' => false, 'token_frequencia' => 'ENC-QR']);
    $this->user = mkUser('user', 'atleta@x.com');
    $this->p = Participante::create(['user_id' => $this->user->id, 'nome_completo' => 'Atleta', 'cpf' => '111.444.777-35', 'email' => 'atleta@x.com', 'tipo_vinculo' => 'externo', 'vinculo_confirmado_em' => now()]);
});

it('organização encerra e reabre a frequência; o QR deixa de registrar e volta a registrar', function () {
    expect($this->atividade->frequenciaAberta())->toBeTrue();
    $this->actingAs($this->user)->get(route('frequencia.form', 'ENC-QR'))->assertOk()->assertSee('id="formPresenca"', false);

    $this->actingAs($this->dono)->post(route('admin.atividades.frequencia.fechar', $this->atividade))->assertSessionHas('success');
    expect($this->atividade->fresh()->frequenciaAberta())->toBeFalse()->and($this->atividade->fresh()->estadoDaFrequencia())->toBe('encerrada');

    // página mostra o aviso e nenhum formulário; o envio é recusado e nada é gravado
    $this->actingAs($this->user)->get(route('frequencia.form', 'ENC-QR'))->assertOk()->assertSee('já foi encerrado')->assertDontSee('id="formPresenca"', false);
    $this->post(route('frequencia.store', 'ENC-QR'), [], ['Accept' => 'application/json'])->assertForbidden()->assertJsonPath('message', 'A frequência desta atividade foi encerrada. Procure a organização do evento.');
    $this->post(route('frequencia.store', 'ENC-QR'))->assertSessionHas('error');
    expect(Frequencia::count())->toBe(0);

    // visitante sem login também vê que encerrou (em vez do convite para entrar)
    auth()->logout();
    $this->get(route('frequencia.form', 'ENC-QR'))->assertOk()->assertSee('já foi encerrado')->assertDontSee('Entre ou crie sua conta');

    $this->actingAs($this->dono)->post(route('admin.atividades.frequencia.reabrir', $this->atividade))->assertSessionHas('success');
    $this->actingAs($this->user)->post(route('frequencia.store', 'ENC-QR'), [], ['Accept' => 'application/json'])->assertOk();
    expect(Frequencia::count())->toBe(1);
});

it('quem já registrou continua vendo o comprovante depois do encerramento; o registro manual da organização segue valendo', function () {
    $this->actingAs($this->user)->post(route('frequencia.store', 'ENC-QR'), [], ['Accept' => 'application/json'])->assertOk();
    $this->atividade->update(['frequencia_encerrada_em' => now()]);

    $this->actingAs($this->user)->get(route('frequencia.form', 'ENC-QR'))->assertOk()->assertSee('Presença já registrada')->assertDontSee('já foi encerrado');
    $this->get(route('frequencia.confirmada', 'ENC-QR'))->assertOk()->assertSee('Presença registrada!');
    $this->post(route('frequencia.store', 'ENC-QR'), [], ['Accept' => 'application/json'])->assertStatus(409);

    // organização lança presença de outra pessoa mesmo com a frequência encerrada
    $outra = Participante::create(['nome_completo' => 'Outra Pessoa', 'cpf' => '529.982.247-25', 'email' => 'o@x.com', 'tipo_vinculo' => 'aluno', 'matricula' => '1']);
    $this->actingAs($this->dono)->post(route('admin.atividades.checkin', [$this->atividade, $outra]))->assertSessionHasNoErrors();
    expect(Frequencia::where('participante_id', $outra->id)->exists())->toBeTrue();
});

it('fechamento automático: encerra no horário definido e a edição guarda ou limpa o horário', function () {
    $dados = ['titulo' => 'Final de handebol', 'tipo' => 'jogo', 'data_inicio' => now()->format('Y-m-d\TH:i'), 'carga_horaria' => 2, 'gera_certificado' => '0'];

    $this->actingAs($this->dono)->put(route('admin.atividades.update', $this->atividade), $dados + ['frequencia_fecha_em' => now()->addHour()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
    expect($this->atividade->fresh()->frequencia_fecha_em)->not->toBeNull()->and($this->atividade->fresh()->frequenciaAberta())->toBeTrue();

    $this->atividade->update(['frequencia_fecha_em' => now()->subMinute()]);
    expect($this->atividade->fresh()->frequenciaAberta())->toBeFalse()->and($this->atividade->fresh()->estadoDaFrequencia())->toBe('encerrada_auto');
    $this->actingAs($this->user)->post(route('frequencia.store', 'ENC-QR'), [], ['Accept' => 'application/json'])->assertForbidden();
    $this->actingAs($this->dono)->get(route('admin.atividades.participantes', $this->atividade))->assertSee('Encerrou automaticamente')->assertSee('Reabrir frequência');

    // reabrir limpa o fechamento vencido
    $this->actingAs($this->dono)->post(route('admin.atividades.frequencia.reabrir', $this->atividade));
    expect($this->atividade->fresh()->frequenciaAberta())->toBeTrue()->and($this->atividade->fresh()->frequencia_fecha_em)->toBeNull();

    $this->actingAs($this->dono)->put(route('admin.atividades.update', $this->atividade), $dados)->assertSessionHasNoErrors();
    expect($this->atividade->fresh()->frequencia_fecha_em)->toBeNull();
});

it('telas da organização mostram o estado e só quem gerencia o evento encerra', function () {
    $this->actingAs($this->dono)->get(route('admin.atividades.participantes', $this->atividade))->assertOk()->assertSee('Frequência aberta')->assertSee('Encerrar frequência');
    $this->actingAs($this->dono)->get(route('admin.eventos.show', $this->evento))->assertOk()->assertDontSee('Frequência encerrada');

    $this->atividade->update(['frequencia_encerrada_em' => now()]);
    $this->actingAs($this->dono)->get(route('admin.eventos.show', $this->evento))->assertOk()->assertSee('Frequência encerrada');
    $this->actingAs($this->dono)->get(route('admin.atividades.edit', $this->atividade))->assertOk()->assertSee('Fechar a frequência automaticamente');

    $estranho = mkUser('admin');
    $this->actingAs($estranho)->post(route('admin.atividades.frequencia.reabrir', $this->atividade))->assertForbidden();
    $this->actingAs($this->user)->post(route('admin.atividades.frequencia.fechar', $this->atividade))->assertRedirect();
    expect($this->atividade->fresh()->frequencia_encerrada_em)->not->toBeNull();
});
