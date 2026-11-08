<?php

use App\Models\Atividade;
use App\Models\Frequencia;
use App\Models\Inscricao;
use App\Models\Participante;

beforeEach(function () {
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);   // sem coordenadas: GPS não é exigido
    $this->atividade = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Palestra QR', 'tipo' => 'palestra', 'data_inicio' => now(), 'data_fim' => now()->addHours(2), 'carga_horaria' => 2, 'vagas' => 50, 'token_frequencia' => 'QR-TESTE']);
    $this->url = route('frequencia.store', 'QR-TESTE');
});

it('visitante sem login vê o convite para entrar/criar conta e nenhum formulário', function () {
    $this->get(route('frequencia.form', 'QR-TESTE'))->assertOk()
        ->assertSee('Entre ou crie sua conta')
        ->assertSee(route('frequencia.acesso', ['QR-TESTE', 'entrar']), false)->assertSee(route('frequencia.acesso', ['QR-TESTE', 'cadastrar']), false)
        ->assertDontSee('<form id="formPresenca"', false)->assertDontSee('name="cpf"', false);
});

it('registrar presença sem login é barrado (JSON 401, página vai ao login) e nada é gravado', function () {
    $dados = ['nome_completo' => 'Sem Conta', 'cpf' => '111.444.777-35', 'email' => 'sc@x.com', 'tipo_vinculo' => 'externo'];

    $this->post($this->url, $dados, ['Accept' => 'application/json'])->assertUnauthorized();
    $this->post($this->url, $dados)->assertRedirect(route('login'));

    expect(Frequencia::count())->toBe(0)->and(Participante::count())->toBe(0)->and(Inscricao::count())->toBe(0);
});

it('login e cadastro pelo QR levam de volta à página de presença', function () {
    $this->get(route('frequencia.acesso', ['QR-TESTE', 'entrar']))->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe(route('frequencia.form', 'QR-TESTE'));

    $this->get(route('frequencia.acesso', ['QR-TESTE', 'cadastrar']))->assertRedirect(route('register'));
    $this->actingAs(mkUser())->get(route('frequencia.acesso', ['QR-TESTE', 'entrar']))->assertRedirect(route('frequencia.form', 'QR-TESTE'));
});

it('registra a presença usando o participante da conta (CPF, nome e e-mail da conta; inscrição automática)', function () {
    $user = mkUser('user', 'conta@x.com');
    $p = Participante::create(['user_id' => $user->id, 'nome_completo' => 'Maria da Conta', 'cpf' => '111.444.777-35', 'email' => 'conta@x.com', 'tipo_vinculo' => 'externo']);

    $this->actingAs($user)->get(route('frequencia.form', 'QR-TESTE'))->assertOk()->assertSee('Maria da Conta')->assertSee('111.444.777-35')->assertDontSee('name="cpf"', false);

    // dados forjados no POST (CPF/nome/e-mail de outra pessoa) são ignorados
    $this->post($this->url, ['nome_completo' => 'Outra', 'cpf' => '529.982.247-25', 'email' => 'outro@x.com', 'tipo_vinculo' => 'externo'], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonStructure(['redirect']);

    expect(Participante::count())->toBe(1)
        ->and(Frequencia::where('atividade_id', $this->atividade->id)->where('participante_id', $p->id)->exists())->toBeTrue()
        ->and(Inscricao::where('evento_id', $this->evento->id)->where('participante_id', $p->id)->exists())->toBeTrue()
        ->and($p->fresh()->cpf)->toBe('111.444.777-35')->and($p->fresh()->email)->toBe('conta@x.com');

    // escanear de novo não duplica
    $this->post($this->url, ['tipo_vinculo' => 'externo'], ['Accept' => 'application/json'])->assertOk();
    expect(Frequencia::count())->toBe(1)->and(Inscricao::count())->toBe(1);
});

it('conta sem cadastro informa o CPF uma vez; CPF de outra conta é recusado', function () {
    $user = mkUser('user', 'semcad@x.com');
    $this->actingAs($user)->get(route('frequencia.form', 'QR-TESTE'))->assertSee('name="cpf"', false);
    $this->post($this->url, ['tipo_vinculo' => 'externo'], ['Accept' => 'application/json'])->assertStatus(422);

    $this->post($this->url, ['cpf' => '111.444.777-35', 'nome_completo' => 'Sem Cadastro', 'tipo_vinculo' => 'externo'], ['Accept' => 'application/json'])->assertOk();
    $p = Participante::where('cpf', '111.444.777-35')->first();
    expect($p->user_id)->toBe($user->id)->and($p->email)->toBe('semcad@x.com');

    $intruso = mkUser('user', 'intruso@x.com');
    $this->actingAs($intruso)->post($this->url, ['cpf' => '111.444.777-35', 'nome_completo' => 'Intruso', 'tipo_vinculo' => 'externo'], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'outra conta'));
    expect(Frequencia::count())->toBe(1);
});

it('mantém a validação de localização quando o evento tem coordenadas', function () {
    $this->evento->update(['latitude' => '-1.9183', 'longitude' => '-55.5186', 'raio_permitido' => 300]);
    $user = mkUser('user', 'gps@x.com');
    Participante::create(['user_id' => $user->id, 'nome_completo' => 'GPS', 'cpf' => '111.444.777-35', 'email' => 'gps@x.com', 'tipo_vinculo' => 'externo']);
    $this->actingAs($user);

    $this->post($this->url, ['tipo_vinculo' => 'externo'], ['Accept' => 'application/json'])->assertStatus(422);                                   // sem GPS
    $this->post($this->url, ['tipo_vinculo' => 'externo', 'user_lat' => '10', 'user_lng' => '10'], ['Accept' => 'application/json'])->assertStatus(403); // longe
    $this->post($this->url, ['tipo_vinculo' => 'externo', 'user_lat' => '-1.9183', 'user_lng' => '-55.5186'], ['Accept' => 'application/json'])->assertOk();
    expect(Frequencia::count())->toBe(1);
});
