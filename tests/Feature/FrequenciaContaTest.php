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

    // quem já registrou não preenche de novo: a tela avisa e o envio é recusado (sem duplicar)
    $this->actingAs($user)->get(route('frequencia.form', 'QR-TESTE'))->assertOk()->assertSee('Presença já registrada')->assertDontSee('id="formPresenca"', false);
    $this->post($this->url, ['tipo_vinculo' => 'externo'], ['Accept' => 'application/json'])->assertStatus(409)->assertJsonPath('message', 'Você já registrou presença nesta atividade.');
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

it('gera o PDF dos QR Codes de frequência com uma página por atividade', function () {
    $admin = $this->dono;
    $this->atividade->update(['responsavel' => 'Profa. Teste', 'local' => 'Auditório']);

    $resp = $this->actingAs($admin)->get(route('admin.eventos.atividades.qrcodes', $this->evento->id));
    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toContain('pdf');

    $this->actingAs(mkUser('user', 'comum-qr@x.com'))->get(route('admin.eventos.atividades.qrcodes', $this->evento->id))->assertRedirect();
});

it('quem vem de um QR Code de presença volta ao QR depois de entrar (única exceção à dashboard)', function () {
    mkUser('user', 'qr@x.com');
    $this->get(route('frequencia.acesso', ['QR-TESTE', 'entrar']))->assertRedirect(route('login'));

    $this->post('/login', ['email' => 'qr@x.com', 'password' => 'password'])->assertRedirect(route('frequencia.form', 'QR-TESTE'));
});

it('um endereço de outro site gravado como destino é ignorado no login', function () {
    mkUser('user', 'fora@x.com');
    $this->withSession(['url.intended' => 'https://site-malicioso.example/frequencia/abc'])
        ->post('/login', ['email' => 'fora@x.com', 'password' => 'password'])->assertRedirect(route('inscrito.dashboard'));
});

// ---------- Coleta de localização (GPS) ----------
it('evento com coordenadas: a página pede a localização a cada carregamento e explica como liberar se for bloqueada', function () {
    $this->evento->update(['latitude' => -1.9183, 'longitude' => -55.5186, 'raio_permitido' => 300]);

    $this->actingAs(mkUser('user', 'gps@x.com'))->get(route('frequencia.form', 'QR-TESTE'))->assertOk()
        ->assertSee('exigeGps: true', false)
        // fluxo: pedir permissão, mostrar localização obtida e só então confirmar
        ->assertSee('Permita o acesso à localização')->assertSee('Localização obtida')->assertSee('Confirmar presença')->assertSee('Permitir localização e confirmar')
        // bloqueio: o navegador não pergunta de novo, então mostra o passo a passo e um botão de nova tentativa
        ->assertSee('A localização está bloqueada')->assertSee('Já liberei, tentar de novo')->assertSee('Ajustes do Site')->assertSee('Permissões')
        ->assertSee('Recarregar a página')
        // outros problemas conhecidos
        ->assertSee('Demorou para achar sua localização')->assertSee('Conexão não segura')->assertSee('Abrir no Chrome/Safari')
        // a lógica de nova tentativa e de acompanhar a permissão
        ->assertSee('permissions.query', false)->assertSee('visibilitychange', false)->assertSee('solicitar()', false)->assertSee('enableHighAccuracy: false', false);
});

it('evento sem coordenadas não exige nem pede localização', function () {
    $r = $this->actingAs(mkUser('user', 'semgps@x.com'))->get(route('frequencia.form', 'QR-TESTE'))->assertOk();

    $r->assertSee('exigeGps: false', false)->assertSee('Confirmar presença')
        ->assertDontSee('A localização está bloqueada')->assertDontSee('Permita o acesso à localização')->assertDontSee('Permitir localização e confirmar');
});

it('o servidor continua exigindo coordenadas válidas dentro do raio quando o evento tem localização', function () {
    $this->evento->update(['latitude' => -1.9183, 'longitude' => -55.5186, 'raio_permitido' => 300]);
    $this->actingAs(mkUser('user', 'raio@x.com'));
    $dados = ['tipo_vinculo' => 'externo', 'cpf' => '111.444.777-35', 'nome_completo' => 'Fulano'];

    $this->post($this->url, $dados, ['Accept' => 'application/json'])->assertStatus(422)->assertJsonFragment(['message' => 'Não foi possível obter sua localização. Permita o acesso ao GPS e tente novamente.']);
    $this->post($this->url, $dados + ['user_lat' => '10', 'user_lng' => '10'], ['Accept' => 'application/json'])->assertStatus(403);
    $this->post($this->url, $dados + ['user_lat' => '-1.9183', 'user_lng' => '-55.5186'], ['Accept' => 'application/json'])->assertOk();
});

it('conta sem cadastro de participante e sem CPF no envio recebe aviso claro, não erro 500', function () {
    $user = mkUser('user', 'semcpf@x.com');

    expect(fn () => app(\App\Services\ParticipanteDaConta::class)->resolver($user, ['tipo_vinculo' => 'externo']))
        ->toThrow(\DomainException::class, 'Informe seu CPF e nome completo');
    expect(Participante::count())->toBe(0);
});
