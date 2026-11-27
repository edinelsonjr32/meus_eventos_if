<?php

use App\Models\Atividade;
use App\Models\Frequencia;
use App\Models\Inscricao;
use App\Models\Participante;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = mkUser('admin');
    $this->evento = mkEvento($this->admin);
    $this->atividade = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Palestra de Abertura', 'tipo' => 'palestra', 'data_inicio' => now(), 'data_fim' => now()->addHours(2), 'carga_horaria' => 2, 'vagas' => 50, 'token_frequencia' => 'QR-GF']);
    $this->gestor = mkUser('user', 'gestorfreq@x.com');
    $this->aluno = Participante::create(['nome_completo' => 'Joana Sem Celular', 'cpf' => '529.982.247-25', 'email' => 'joana@x.com', 'tipo_vinculo' => 'externo']);
});

it('o administrador designa o gestor de frequência, que vê o QR Code das atividades e nada mais da administração', function () {
    $this->actingAs($this->gestor)->get(route('gestao.frequencia.index'))->assertOk()->assertSee('ainda não foi designado')->assertDontSee('Palestra de Abertura');
    $this->get(route('gestao.frequencia.qr', $this->atividade))->assertForbidden();

    $this->actingAs($this->admin)->post(route('admin.eventos.papeis.store', $this->evento), ['email' => 'gestorfreq@x.com', 'papel' => 'gestor_frequencia'])->assertSessionHas('success');

    $this->actingAs($this->gestor)->get(route('gestao.frequencia.index'))->assertOk()->assertSee('Palestra de Abertura')->assertSee(route('gestao.frequencia.qr', $this->atividade), false)->assertSee('Frequência (QR Code)');
    $this->get(route('gestao.frequencia.qr', $this->atividade))->assertOk()->assertSee('<svg', false)->assertSee(route('frequencia.form', 'QR-GF'), false)->assertSee('Tela cheia');

    // sem acesso à administração, à lista completa de presença, à recepção ou aos papéis
    foreach ([route('admin.atividades.participantes', $this->atividade), route('admin.eventos.show', $this->evento), route('admin.eventos.papeis.index', $this->evento), route('admin.eventos.credenciamento.index', $this->evento)] as $url) {
        expect($this->get($url)->status())->not->toBe(200);
    }

    // e não vale para outro evento
    $outro = mkEvento($this->admin);
    $deOutro = Atividade::create(['evento_id' => $outro->id, 'titulo' => 'Outra', 'tipo' => 'palestra', 'data_inicio' => now(), 'data_fim' => now()->addHour(), 'carga_horaria' => 1, 'vagas' => 5, 'token_frequencia' => 'QR-OUTRO']);
    $this->get(route('gestao.frequencia.qr', $deOutro))->assertForbidden();
    $this->post(route('gestao.frequencia.registrar', [$deOutro, $this->aluno]))->assertForbidden();
});

it('registra presença manual de quem já tem cadastro, sem duplicar e sem alterar o cadastro', function () {
    mkPapel($this->gestor, $this->evento, 'gestor_frequencia');
    $this->actingAs($this->gestor);

    $this->get(route('gestao.frequencia.manual', [$this->atividade, 'q' => 'Joana']))->assertOk()->assertSee('Joana Sem Celular')->assertSee('***.982.247-**')->assertDontSee('529.982.247-25');
    $this->get(route('gestao.frequencia.manual', [$this->atividade, 'q' => '52998224725']))->assertSee('Joana Sem Celular');

    $this->post(route('gestao.frequencia.registrar', [$this->atividade, $this->aluno]))->assertSessionHas('success');
    $this->post(route('gestao.frequencia.registrar', [$this->atividade, $this->aluno]))->assertSessionHas('warning');
    expect(Frequencia::count())->toBe(1)->and(Inscricao::count())->toBe(1);
    $this->get(route('gestao.frequencia.manual', [$this->atividade, 'q' => 'Joana']))->assertSee('Já presente');
});

it('cadastra quem não tem cadastro; CPF existente mantém os dados antigos; e-mail com acento é recusado', function () {
    mkPapel($this->gestor, $this->evento, 'gestor_frequencia');
    $this->actingAs($this->gestor);
    $url = route('gestao.frequencia.novo', $this->atividade);

    $this->post($url, ['nome_completo' => 'Novo Visitante', 'cpf' => '11144477735', 'email' => 'novo@x.com', 'tipo_vinculo' => 'externo'])->assertSessionHas('success');
    expect(Participante::where('cpf', '111.444.777-35')->value('nome_completo'))->toBe('Novo Visitante')->and(Frequencia::count())->toBe(1);

    $this->post($url, ['nome_completo' => 'Nome Trocado', 'cpf' => '529.982.247-25', 'email' => 'outro@x.com', 'tipo_vinculo' => 'externo'])->assertSessionHas('success');
    expect($this->aluno->fresh()->nome_completo)->toBe('Joana Sem Celular')->and($this->aluno->fresh()->email)->toBe('joana@x.com')->and(Participante::count())->toBe(2)->and(Frequencia::count())->toBe(2);

    $this->post($url, ['nome_completo' => 'Com Acento', 'cpf' => '390.533.447-05', 'email' => 'joão@x.com', 'tipo_vinculo' => 'externo'])->assertSessionHasErrors('email');
    $this->post($url, ['nome_completo' => 'Aluno Sem Turma', 'cpf' => '390.533.447-05', 'email' => 'a@x.com', 'tipo_vinculo' => 'aluno'])->assertSessionHasErrors(['matricula', 'turma_id']);
});

it('quem não tem o papel não registra presença manual', function () {
    $this->actingAs(mkUser('user', 'curioso@x.com'));
    $this->get(route('gestao.frequencia.manual', $this->atividade))->assertForbidden();
    $this->post(route('gestao.frequencia.registrar', [$this->atividade, $this->aluno]))->assertForbidden();
    expect(Frequencia::count())->toBe(0);
});
