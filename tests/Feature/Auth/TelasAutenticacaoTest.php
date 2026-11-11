<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;

beforeEach(fn () => app()->setLocale('pt-br'));

it('login, cadastro, redefinição e confirmação têm o botão de mostrar/ocultar senha', function () {
    $this->get(route('login'))->assertOk()->assertSee('btn-ver-senha', false)->assertSee('Mostrar senha');
    $this->get(route('register'))->assertOk()->assertSee('btn-ver-senha', false);
    // cadastro tem dois campos de senha, cada um com seu botão
    expect(substr_count($this->get(route('register'))->getContent(), 'data-alvo='))->toBe(2);

    $this->get(route('password.reset', ['token' => 'abc', 'email' => 'a@b.com']))->assertOk()->assertSee('Crie uma nova senha')->assertSee('btn-ver-senha', false);

    $u = User::factory()->create();
    $this->actingAs($u)->get(route('password.confirm'))->assertOk()->assertSee('Confirme sua senha')->assertSee('btn-ver-senha', false);
});

it('a tela de esqueci a senha segue o estilo das demais e está em português', function () {
    $this->get(route('password.request'))->assertOk()
        ->assertSee('Esqueceu a senha?')->assertSee('Enviar link de redefinição')->assertSee('Voltar ao login')
        ->assertSee('auth-card', false)->assertDontSee('<header class="pub-nav">', false)->assertDontSee('<footer class="pub-rodape">', false)->assertSee('bg-success bg-opacity-10', false)
        ->assertDontSee('Forgot your password')->assertDontSee('Email Password Reset Link');
});

it('pedir o link mostra confirmação em português; e-mail desconhecido recebe aviso em português', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $u = User::factory()->create();

    $this->post(route('password.email'), ['email' => $u->email])->assertSessionHas('status', 'Enviamos o link de redefinição de senha para o seu e-mail.');
    $this->post(route('password.email'), ['email' => 'ninguem@nada.com'])->assertSessionHasErrors(['email' => 'Não encontramos uma conta com este e-mail.']);
});

it('credenciais erradas no login mostram a mensagem em português', function () {
    $u = User::factory()->create();
    $this->from(route('login'))->post(route('login'), ['email' => $u->email, 'password' => 'errada'])->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
});

it('erros de validação do cadastro saem em português', function () {
    $r = $this->post(route('register'), ['name' => '', 'email' => 'nao-e-email', 'password' => '123', 'password_confirmation' => '999', 'cpf' => '']);
    $r->assertSessionHasErrors(['name', 'email', 'password']);
    expect(session('errors')->first('name'))->toBe('O campo NOME COMPLETO é obrigatório.');
});

it('o link de redefinição do e-mail leva à tela de nova senha e a senha é trocada', function () {
    $u = User::factory()->create();
    $token = Password::createToken($u);

    $this->post(route('password.store'), ['token' => $token, 'email' => $u->email, 'password' => 'NovaSenha#2026', 'password_confirmation' => 'NovaSenha#2026'])
        ->assertRedirect(route('login'))->assertSessionHas('status', 'Sua senha foi redefinida. Entre com a nova senha.');
    expect(\Illuminate\Support\Facades\Hash::check('NovaSenha#2026', $u->fresh()->password))->toBeTrue();
});

it('as telas de autenticação não têm o menu nem o rodapé do site, mas mantêm o caminho de volta', function () {
    foreach (['login', 'register', 'password.request'] as $rota) {
        $this->get(route($rota))->assertOk()->assertDontSee('<header class="pub-nav">', false)->assertDontSee('<footer class="pub-rodape">', false)->assertSee('Voltar ao início')->assertSee('Política de privacidade');
    }
});

it('o ícone do site (favicon) é servido e está nas páginas', function () {
    $this->get(route('icone.svg'))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    expect($this->get(route('icone.png'))->assertOk()->assertHeader('Content-Type', 'image/png')->baseResponse->getFile()->getSize())->toBeGreaterThan(500);

    foreach ([route('home'), route('login'), route('privacidade')] as $url) {
        $this->get($url)->assertSee(route('icone.svg'), false)->assertSee('rel="apple-touch-icon"', false);
    }
    $this->actingAs(mkUser())->get(route('inscrito.dashboard'))->assertSee(route('icone.svg'), false);
});

it('"Ir para o Site" fica no topo do menu, logo abaixo da marca', function () {
    $html = $this->actingAs(mkUser())->get(route('inscrito.dashboard'))->getContent();
    $marca = strpos($html, 'sidebar-marca');
    $site = strpos($html, 'Ir para o Site');
    $primeiraSecao = strpos($html, 'Área do participante');
    expect($marca)->toBeLessThan($site)->and($site)->toBeLessThan($primeiraSecao)->and(substr_count($html, 'Ir para o Site'))->toBe(1);
});
