<?php

use App\Models\Atividade;
use App\Models\Curso;
use App\Models\Frequencia;
use App\Models\Participante;
use App\Models\Turma;
use App\Models\User;

beforeEach(function () {
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->curso = Curso::create(['nome' => 'Técnico em Informática']);
    $this->turma = Turma::create(['curso_id' => $this->curso->id, 'ano' => 2026]);
    $this->atividade = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Jogo', 'tipo' => 'jogo', 'data_inicio' => now(), 'data_fim' => now()->addHours(2), 'carga_horaria' => 2, 'token_frequencia' => 'TIPO-QR']);
    $this->conta = fn (array $extra = []) => array_merge(['name' => 'Maria Souza', 'email' => 'maria@x.com', 'cpf' => '111.444.777-35', 'password' => 'Senha@12345', 'password_confirmation' => 'Senha@12345'], $extra);
});

it('cadastro da conta mostra o tipo de cadastro com aluno por padrão, curso e turma', function () {
    $this->get(route('register'))->assertOk()
        ->assertSee('Tipo de cadastro')->assertSee('Aluno (discente do IFPA)')->assertSee('Comunidade externa')
        ->assertSee('Selecione o curso')->assertSee('name="turma_id"', false)->assertSee('name="matricula"', false);
});

it('cadastra aluno com matrícula e turma (confirmado), servidor com matrícula e externo sem nada', function () {
    $this->post(route('register'), ($this->conta)(['tipo_vinculo' => 'aluno', 'matricula' => '2026001', 'turma_id' => $this->turma->id]))->assertSessionHasNoErrors();
    $p = Participante::where('cpf', '111.444.777-35')->firstOrFail();
    expect($p->tipo_vinculo)->toBe('aluno')->and($p->matricula)->toBe('2026001')->and($p->turma_id)->toBe($this->turma->id)->and($p->vinculo_confirmado_em)->not->toBeNull();
    auth()->logout();

    $this->post(route('register'), ($this->conta)(['email' => 's@x.com', 'cpf' => '529.982.247-25', 'tipo_vinculo' => 'servidor', 'matricula' => '1234567', 'turma_id' => $this->turma->id]))->assertSessionHasNoErrors();
    $s = Participante::where('cpf', '529.982.247-25')->firstOrFail();
    expect($s->tipo_vinculo)->toBe('servidor')->and($s->matricula)->toBe('1234567')->and($s->turma_id)->toBeNull();
    auth()->logout();

    $this->post(route('register'), ($this->conta)(['email' => 'e@x.com', 'cpf' => '390.533.447-05', 'tipo_vinculo' => 'externo', 'matricula' => 'xx', 'turma_id' => $this->turma->id]))->assertSessionHasNoErrors();
    $e = Participante::where('cpf', '390.533.447-05')->firstOrFail();
    expect($e->tipo_vinculo)->toBe('externo')->and($e->matricula)->toBeNull()->and($e->turma_id)->toBeNull()->and($e->vinculo_confirmado_em)->not->toBeNull();
});

it('aluno sem matrícula ou sem turma é recusado no cadastro da conta', function () {
    $this->post(route('register'), ($this->conta)(['tipo_vinculo' => 'aluno']))->assertSessionHasErrors(['matricula', 'turma_id']);
    $this->post(route('register'), ($this->conta)(['tipo_vinculo' => 'aluno', 'matricula' => '1']))->assertSessionHasErrors('turma_id');
    $this->post(route('register'), ($this->conta)(['tipo_vinculo' => 'servidor']))->assertSessionHasErrors('matricula');
    $this->post(route('register'), ($this->conta)(['tipo_vinculo' => 'astronauta']))->assertSessionHasErrors('tipo_vinculo');
    expect(User::where('email', 'maria@x.com')->exists())->toBeFalse();
});

it('cadastro antigo como externo é perguntado na frequência; confirmar como externo grava e não pergunta de novo', function () {
    $user = mkUser('user', 'antigo@x.com');
    $p = Participante::create(['user_id' => $user->id, 'nome_completo' => 'Antigo Externo', 'cpf' => '111.444.777-35', 'email' => 'antigo@x.com', 'tipo_vinculo' => 'externo']);

    $this->actingAs($user)->get(route('frequencia.form', 'TIPO-QR'))->assertOk()
        ->assertSee('Confirme seu tipo de cadastro')->assertSee('Sim, sou da comunidade externa')->assertSee('Minha Conta › Editar dados do perfil');

    $this->post(route('frequencia.store', 'TIPO-QR'), ['confirmacao_vinculo' => 'externo'], ['Accept' => 'application/json'])->assertOk();
    expect($p->fresh()->tipo_vinculo)->toBe('externo')->and($p->fresh()->vinculo_confirmado_em)->not->toBeNull();

    // outra atividade: não pergunta mais, só mostra o tipo e o caminho para mudar
    Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Outra', 'tipo' => 'jogo', 'data_inicio' => now(), 'data_fim' => now()->addHour(), 'carga_horaria' => 1, 'token_frequencia' => 'TIPO-QR2']);
    $this->get(route('frequencia.form', 'TIPO-QR2'))->assertOk()->assertDontSee('Confirme seu tipo de cadastro')->assertSee('Tipo de cadastro')->assertSee('Comunidade externa')->assertSee('Editar dados do perfil');
});

it('cadastro antigo como externo pode ser corrigido para aluno na frequência, com matrícula e turma', function () {
    $user = mkUser('user', 'errado@x.com');
    $p = Participante::create(['user_id' => $user->id, 'nome_completo' => 'Cadastrou Errado', 'cpf' => '111.444.777-35', 'email' => 'errado@x.com', 'tipo_vinculo' => 'externo']);
    $this->actingAs($user);

    // corrigir exige os dados do aluno
    $this->post(route('frequencia.store', 'TIPO-QR'), ['confirmacao_vinculo' => 'corrigir', 'tipo_vinculo' => 'aluno'], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors(['matricula', 'turma_id']);
    expect($p->fresh()->tipo_vinculo)->toBe('externo')->and(Frequencia::count())->toBe(0);

    $this->post(route('frequencia.store', 'TIPO-QR'), ['confirmacao_vinculo' => 'corrigir', 'tipo_vinculo' => 'aluno', 'matricula' => '2026009', 'turma_id' => $this->turma->id], ['Accept' => 'application/json'])->assertOk();
    $p = $p->fresh();
    expect($p->tipo_vinculo)->toBe('aluno')->and($p->matricula)->toBe('2026009')->and($p->turma_id)->toBe($this->turma->id)->and($p->vinculo_confirmado_em)->not->toBeNull();
    expect(Frequencia::where('participante_id', $p->id)->count())->toBe(1);
});

it('cadastro em dia não muda de tipo pela frequência (só em Minha Conta) e aluno incompleto é obrigado a completar', function () {
    $user = mkUser('user', 'ok@x.com');
    $p = Participante::create(['user_id' => $user->id, 'nome_completo' => 'Aluna Ok', 'cpf' => '111.444.777-35', 'email' => 'ok@x.com', 'tipo_vinculo' => 'aluno', 'matricula' => '55', 'turma_id' => $this->turma->id]);
    $this->actingAs($user);

    $this->get(route('frequencia.form', 'TIPO-QR'))->assertOk()->assertDontSee('Confirme seu tipo')->assertSee('matrícula 55')->assertSee('Editar dados do perfil');
    // tentativa de forçar outro tipo pela frequência é ignorada
    $this->post(route('frequencia.store', 'TIPO-QR'), ['tipo_vinculo' => 'externo', 'matricula' => 'x'], ['Accept' => 'application/json'])->assertOk();
    expect($p->fresh()->tipo_vinculo)->toBe('aluno')->and($p->fresh()->matricula)->toBe('55');

    // aluno sem turma: pede para completar
    $u2 = mkUser('user', 'inc@x.com');
    Participante::create(['user_id' => $u2->id, 'nome_completo' => 'Aluno Incompleto', 'cpf' => '529.982.247-25', 'email' => 'inc@x.com', 'tipo_vinculo' => 'aluno', 'matricula' => '77']);
    $this->actingAs($u2)->get(route('frequencia.form', 'TIPO-QR'))->assertSee('Complete seu cadastro');
    $this->post(route('frequencia.store', 'TIPO-QR'), [], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('turma_id');
});

it('a inscrição no evento segue a mesma regra de confirmação do tipo', function () {
    $user = mkUser('user', 'insc@x.com');
    $p = Participante::create(['user_id' => $user->id, 'nome_completo' => 'Inscrita', 'cpf' => '111.444.777-35', 'email' => 'insc@x.com', 'tipo_vinculo' => 'externo']);
    $this->actingAs($user)->get(route('evento.publico.show', $this->evento->slug))->assertOk()->assertSee('Confirme seu tipo de cadastro');

    $this->post(route('evento.publico.store', $this->evento->slug), ['confirmacao_vinculo' => 'corrigir', 'tipo_vinculo' => 'aluno', 'matricula' => '9', 'turma_id' => $this->turma->id])->assertSessionHasNoErrors();
    expect($p->fresh()->tipo_vinculo)->toBe('aluno')->and($p->fresh()->turma_id)->toBe($this->turma->id);
});

it('Minha Conta altera o tipo de cadastro (aluno, matrícula, curso e turma) e marca como confirmado', function () {
    $user = mkUser('user', 'perfil@x.com');
    $p = Participante::create(['user_id' => $user->id, 'nome_completo' => 'Perfil', 'cpf' => '111.444.777-35', 'email' => 'perfil@x.com', 'tipo_vinculo' => 'externo']);
    $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Tipo de cadastro')->assertSee('Selecione o curso');

    $base = ['name' => 'Perfil', 'email' => 'perfil@x.com', 'cpf' => '111.444.777-35'];
    $this->patch(route('profile.update'), $base + ['tipo_vinculo' => 'aluno'])->assertSessionHasErrors(['matricula', 'turma_id']);
    $this->patch(route('profile.update'), $base + ['tipo_vinculo' => 'aluno', 'matricula' => '2026100', 'turma_id' => $this->turma->id])->assertSessionHasNoErrors();
    expect($p->fresh()->tipo_vinculo)->toBe('aluno')->and($p->fresh()->turma_id)->toBe($this->turma->id)->and($p->fresh()->vinculo_confirmado_em)->not->toBeNull();

    // sem o campo (formulário antigo) o tipo não muda
    $this->patch(route('profile.update'), $base)->assertSessionHasNoErrors();
    expect($p->fresh()->tipo_vinculo)->toBe('aluno');
});
