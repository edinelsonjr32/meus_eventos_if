<?php

use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Inscricao;
use App\Models\Participante;
use App\Models\User;

function agendaUsuario(string $role = 'user', bool $comParticipante = false): User
{
    $u = User::factory()->create();
    $u->forceFill(['role' => $role])->save();

    if ($comParticipante) {
        Participante::create(['user_id' => $u->id, 'nome_completo' => $u->name, 'cpf' => '111.444.777-35', 'email' => $u->email, 'tipo_vinculo' => 'externo']);
    }

    return $u;
}

it('abre a agenda para quem ainda não tem cadastro de participante, explicando o que fazer', function () {
    foreach (['user', 'admin'] as $perfil) {
        $u = agendaUsuario($perfil);

        $this->actingAs($u)->get(route('inscrito.dashboard'))->assertOk()
            ->assertSee('Complete seu cadastro para usar a agenda')->assertSee('Informar meu CPF')
            ->assertSee(route('profile.edit'), false)->assertSee($u->name)->assertSee('Sua agenda está vazia');
    }
});

it('quem tem cadastro e nenhuma inscrição vê a agenda vazia, sem o aviso de cadastro', function () {
    $this->actingAs(agendaUsuario('user', true))->get(route('inscrito.dashboard'))->assertOk()
        ->assertSee('Sua agenda está vazia')->assertDontSee('Complete seu cadastro para usar a agenda');
});

it('mostra os eventos e atividades de quem está inscrito', function () {
    $u = agendaUsuario('user', true);
    $dono = agendaUsuario('admin');
    $evento = Evento::create(['titulo' => 'Semana de Teste', 'slug' => 's-' . uniqid(), 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDays(2), 'local' => 'X', 'criado_por' => $dono->id]);
    Atividade::create(['evento_id' => $evento->id, 'titulo' => 'Palestra de Abertura', 'tipo' => 'palestra', 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHours(2), 'carga_horaria' => 2, 'vagas' => 10, 'token_frequencia' => uniqid()]);
    Inscricao::create(['evento_id' => $evento->id, 'participante_id' => $u->participante->id, 'data_inscricao' => now()]);

    $this->actingAs($u)->get(route('inscrito.dashboard'))->assertOk()
        ->assertSee('Semana de Teste')->assertSee('Palestra de Abertura')->assertSee('Inscrição Confirmada')->assertDontSee('Sua agenda está vazia');
});

it('visitante é enviado ao login', function () {
    $this->get(route('inscrito.dashboard'))->assertRedirect(route('login'));
});

it('o login de usuário sem cadastro de participante cai na agenda (não em Minha Conta)', function () {
    $u = agendaUsuario('user');
    $this->actingAs($u)->get('/dashboard')->assertRedirect(route('inscrito.dashboard'));
    $this->actingAs($u)->get(route('inscrito.dashboard'))->assertOk();
});

it('participar de atividade sem cadastro volta a Minha Conta mostrando o motivo', function () {
    $u = agendaUsuario('user');
    $dono = agendaUsuario('admin');
    $evento = Evento::create(['titulo' => 'E', 'slug' => 'e-' . uniqid(), 'data_inicio' => now(), 'data_fim' => now()->addDay(), 'local' => 'X', 'criado_por' => $dono->id]);
    $a = Atividade::create(['evento_id' => $evento->id, 'titulo' => 'Oficina', 'tipo' => 'oficina', 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHour(), 'carga_horaria' => 1, 'vagas' => 5, 'token_frequencia' => uniqid()]);

    $this->actingAs($u)->post(route('inscrito.atividade.toggle', $a))->assertRedirect(route('profile.edit'));
    $this->get(route('profile.edit'))->assertOk()->assertSee('Complete seu cadastro primeiro.');
});

it('Minha Conta exibe avisos enviados por outras telas', function () {
    $this->actingAs(agendaUsuario('user'))->withSession(['warning' => 'Aviso de teste exibido'])->get(route('profile.edit'))->assertOk()->assertSee('Aviso de teste exibido');
});
