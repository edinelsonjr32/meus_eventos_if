<?php

use App\Models\Inscricao;
use App\Models\Participante;
use App\Models\User;

beforeEach(function () {
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->evento->update(['slug' => 'semana-x']);
    $this->url = route('evento.publico.store', 'semana-x');
});

it('visitante sem login vê o convite para entrar/criar conta em vez do formulário', function () {
    $this->get(route('evento.publico.show', 'semana-x'))->assertOk()
        ->assertSee('Entre ou crie sua conta para se inscrever')
        ->assertSee(route('evento.publico.acesso', ['semana-x', 'entrar']), false)
        ->assertSee(route('evento.publico.acesso', ['semana-x', 'cadastrar']), false)
        ->assertDontSee('name="cpf"', false)->assertDontSee('Confirmar Inscrição');
});

it('enviar a inscrição sem estar logado é barrado e nada é gravado', function () {
    $this->post($this->url, ['nome_completo' => 'Fulano', 'cpf' => '111.444.777-35', 'email' => 'f@x.com', 'tipo_vinculo' => 'externo'])->assertRedirect(route('login'));

    expect(Inscricao::count())->toBe(0)->and(Participante::count())->toBe(0);
});

it('login e cadastro levam sempre à dashboard da pessoa, mesmo vindo da página do evento', function () {
    $this->get(route('evento.publico.acesso', ['semana-x', 'entrar']))->assertRedirect(route('login'));

    $user = mkUser('user', 'volta@x.com');
    $this->post('/login', ['email' => 'volta@x.com', 'password' => 'password'])->assertRedirect(route('inscrito.dashboard'));
    expect(session('url.intended'))->toBeNull();

    auth()->logout();
    $this->get(route('evento.publico.acesso', ['semana-x', 'cadastrar']))->assertRedirect(route('register'));
    $this->post('/register', ['name' => 'Nova Pessoa', 'email' => 'nova@x.com', 'password' => 'Senha@12345', 'password_confirmation' => 'Senha@12345', 'cpf' => '529.982.247-25'])
        ->assertRedirect(route('inscrito.dashboard'));
    expect(Participante::where('cpf', '529.982.247-25')->first()->user_id)->toBe(User::where('email', 'nova@x.com')->first()->id);
});

it('um link intermediário (ex.: uma tela protegida) também não desvia a pessoa da dashboard no login', function () {
    mkUser('user', 'direto@x.com');
    $this->get('/minha-area/trabalhos')->assertRedirect(route('login'));   // grava url.intended

    $this->post('/login', ['email' => 'direto@x.com', 'password' => 'password'])->assertRedirect(route('inscrito.dashboard'));
});

it('o admin cai na dashboard de administração ao entrar', function () {
    mkUser('admin', 'chefe@x.com');
    $this->post('/login', ['email' => 'chefe@x.com', 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
});

it('a inscrição concluída volta à dashboard com o aviso de confirmação', function () {
    $user = mkUser('user', 'ok@x.com');
    Participante::create(['user_id' => $user->id, 'nome_completo' => 'Maria da Conta', 'cpf' => '111.444.777-35', 'email' => 'ok@x.com', 'tipo_vinculo' => 'externo']);

    $this->actingAs($user)->post($this->url, ['tipo_vinculo' => 'externo'])->assertRedirect(route('inscrito.dashboard'))
        ->assertSessionHas('success');
    $this->get(route('inscrito.dashboard'))->assertOk()->assertSee('Inscrição confirmada');
});

it('a rota /inscrever manda o visitante ao login e o logado ao formulário', function () {
    $this->get(route('evento.publico.inscrever', 'semana-x'))->assertRedirect(route('login'));

    $this->actingAs(mkUser('user', 'a@x.com'))->get(route('evento.publico.inscrever', 'semana-x'))->assertRedirect(route('evento.publico.show', 'semana-x') . '#inscricao');
});

it('inscreve a pessoa logada usando o cadastro da conta (CPF, nome e e-mail da conta)', function () {
    $user = mkUser('user', 'conta@x.com');
    $p = Participante::create(['user_id' => $user->id, 'nome_completo' => 'Maria da Conta', 'cpf' => '111.444.777-35', 'email' => 'conta@x.com', 'tipo_vinculo' => 'externo']);

    $this->actingAs($user)->get(route('evento.publico.show', 'semana-x'))->assertOk()->assertSee('Maria da Conta')->assertSee('111.444.777-35')->assertDontSee('name="cpf"', false);

    // tentativa de forjar outros dados: CPF/nome/e-mail do POST são ignorados
    $this->post($this->url, ['nome_completo' => 'Outra Pessoa', 'cpf' => '529.982.247-25', 'email' => 'outro@x.com', 'tipo_vinculo' => 'aluno', 'matricula' => '2024', 'turma_id' => null])->assertSessionHasErrors('turma_id');
    $this->post($this->url, ['nome_completo' => 'Outra Pessoa', 'cpf' => '529.982.247-25', 'email' => 'outro@x.com', 'tipo_vinculo' => 'externo'])->assertRedirect(route('inscrito.dashboard'));

    $p->refresh();
    expect(Inscricao::where('evento_id', $this->evento->id)->where('participante_id', $p->id)->exists())->toBeTrue()
        ->and(Participante::count())->toBe(1)
        ->and($p->cpf)->toBe('111.444.777-35')->and($p->nome_completo)->toBe('Maria da Conta')->and($p->email)->toBe('conta@x.com')->and($p->user_id)->toBe($user->id);
});

it('não duplica a inscrição e mostra o estado "já inscrito"', function () {
    $user = mkUser('user', 'dup@x.com');
    Participante::create(['user_id' => $user->id, 'nome_completo' => 'Dup', 'cpf' => '111.444.777-35', 'email' => 'dup@x.com', 'tipo_vinculo' => 'externo']);
    $this->actingAs($user)->post($this->url, ['tipo_vinculo' => 'externo'])->assertRedirect(route('inscrito.dashboard'));

    $this->post($this->url, ['tipo_vinculo' => 'externo'])->assertSessionHas('error');
    expect(Inscricao::count())->toBe(1);
    $this->get(route('evento.publico.show', 'semana-x'))->assertSee('Você já está inscrito');
});

it('conta sem cadastro de participante informa o CPF e passa a ter um, ligado à conta', function () {
    $user = mkUser('user', 'semcad@x.com');
    $this->actingAs($user)->get(route('evento.publico.show', 'semana-x'))->assertOk()->assertSee('name="cpf"', false);

    $this->post($this->url, ['tipo_vinculo' => 'externo'])->assertSessionHasErrors(['cpf', 'nome_completo']);
    $this->post($this->url, ['cpf' => '111.444.777-35', 'nome_completo' => 'Sem Cadastro', 'tipo_vinculo' => 'externo'])->assertRedirect(route('inscrito.dashboard'));

    $p = Participante::where('cpf', '111.444.777-35')->first();
    expect($p->user_id)->toBe($user->id)->and($p->email)->toBe('semcad@x.com')->and(Inscricao::where('participante_id', $p->id)->exists())->toBeTrue();
});

it('assume o participante antigo (sem conta) do mesmo CPF, mas recusa CPF de outra conta', function () {
    // participante criado antes (sem conta): é ligado à conta de quem se inscreve
    $antigo = Participante::create(['nome_completo' => 'Histórico', 'cpf' => '111.444.777-35', 'email' => 'velho@x.com', 'tipo_vinculo' => 'aluno']);
    $user = mkUser('user', 'novo@x.com');
    $this->actingAs($user)->post($this->url, ['cpf' => '111.444.777-35', 'nome_completo' => 'Qualquer', 'tipo_vinculo' => 'externo'])->assertRedirect(route('inscrito.dashboard'));
    $antigo->refresh();
    expect($antigo->user_id)->toBe($user->id)->and($antigo->nome_completo)->toBe('Histórico')->and($antigo->email)->toBe('novo@x.com');

    // CPF já ligado a outra conta: recusado
    $intruso = mkUser('user', 'intruso@x.com');
    $this->actingAs($intruso)->post($this->url, ['cpf' => '111.444.777-35', 'nome_completo' => 'Intruso', 'tipo_vinculo' => 'externo'])->assertSessionHas('error');
    expect(Participante::where('cpf', '111.444.777-35')->first()->user_id)->toBe($user->id)->and(Inscricao::count())->toBe(1);
});

it('valida o CPF e exige matrícula e turma para aluno', function () {
    $user = mkUser('user', 'v@x.com');
    $this->actingAs($user)->post($this->url, ['cpf' => '111.111.111-11', 'nome_completo' => 'X', 'tipo_vinculo' => 'externo'])->assertSessionHasErrors('cpf');
    $this->post($this->url, ['cpf' => '111.444.777-35', 'nome_completo' => 'X', 'tipo_vinculo' => 'servidor'])->assertSessionHasErrors('matricula');
    expect(Inscricao::count())->toBe(0);
});
