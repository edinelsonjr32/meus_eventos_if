<?php

use App\Models\Atividade;
use App\Models\Equipe;
use App\Models\Evento;
use App\Models\Frequencia;
use App\Models\Participante;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function criarUsuario(string $role = 'admin'): User
{
    $u = User::factory()->create();
    $u->forceFill(['role' => $role])->save();

    return $u;
}

function criarEvento(User $dono): Evento
{
    return Evento::create([
        'titulo' => 'Evento ' . uniqid(), 'slug' => 'ev-' . uniqid(),
        'data_inicio' => now()->addDay(), 'data_fim' => now()->addDays(2),
        'local' => 'Campus', 'criado_por' => $dono->id,
    ]);
}

function criarAtividade(Evento $e): Atividade
{
    return Atividade::create([
        'evento_id' => $e->id, 'titulo' => 'Palestra', 'tipo' => 'palestra',
        'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHours(2),
        'carga_horaria' => 2, 'vagas' => 10, 'token_frequencia' => uniqid(),
    ]);
}

beforeEach(function () {
    $this->dono = criarUsuario();
    $this->outro = criarUsuario();
    $this->evento = criarEvento($this->dono);
    $this->atividade = criarAtividade($this->evento);
    $this->participante = Participante::create([
        'nome_completo' => 'Fulano', 'cpf' => '111.444.777-35', 'email' => 'f@x.com', 'tipo_vinculo' => 'externo',
    ]);
    $this->freq = Frequencia::create([
        'participante_id' => $this->participante->id, 'atividade_id' => $this->atividade->id,
        'data_registro' => now(), 'hash_validacao' => 'HASH123',
    ]);
});

it('dono acessa as rotas do próprio evento', function () {
    foreach ([
        route('admin.eventos.show', $this->evento),
        route('admin.eventos.edit', $this->evento),
        route('admin.eventos.certificado.edit', $this->evento),
        route('admin.eventos.inscritos', $this->evento),
        route('admin.eventos.equipe.index', $this->evento),
        route('admin.eventos.credenciamento.index', $this->evento),
        route('admin.atividades.participantes', $this->atividade),
        route('admin.eventos.papeis.index', $this->evento),
    ] as $url) {
        $this->actingAs($this->dono)->get($url)->assertOk();
    }
});

it('admin que não é dono recebe 403 em todas as rotas de leitura', function () {
    foreach ([
        route('admin.eventos.show', $this->evento),
        route('admin.eventos.edit', $this->evento),
        route('admin.eventos.certificado.edit', $this->evento),
        route('admin.eventos.inscritos', $this->evento),
        route('admin.eventos.inscritos.exportar', $this->evento),
        route('admin.eventos.equipe.index', $this->evento),
        route('admin.eventos.credenciamento.index', $this->evento),
        route('admin.eventos.credenciamento.search', $this->evento) . '?q=ab',
        route('admin.atividades.participantes', $this->atividade),
        route('admin.atividades.manual', $this->atividade),
        route('admin.atividades.exportar', $this->atividade),
        route('admin.eventos.papeis.index', $this->evento),
    ] as $url) {
        $this->actingAs($this->outro)->get($url)->assertForbidden();
    }
});

it('admin que não é dono não consegue alterar nem excluir dados do evento', function () {
    $equipe = Equipe::create([
        'evento_id' => $this->evento->id, 'participante_id' => $this->participante->id, 'funcao' => 'Apoio', 'carga_horaria_trabalho' => 5,
    ]);

    $this->actingAs($this->outro);
    $this->put(route('admin.eventos.update', $this->evento), ['titulo' => 'X'])->assertForbidden();
    $this->delete(route('admin.eventos.destroy', $this->evento))->assertForbidden();
    $this->post(route('admin.eventos.atividades.store', $this->evento), [])->assertForbidden();
    $this->delete(route('admin.atividades.destroy', $this->atividade))->assertForbidden();
    $this->delete(route('admin.frequencias.destroy', $this->freq->id))->assertForbidden();
    $this->patch(route('admin.frequencias.role', $this->freq->id), ['role' => 'palestrante'])->assertForbidden();
    $this->post(route('admin.atividades.checkin', [$this->atividade, $this->participante]))->assertForbidden();
    $this->delete(route('admin.equipe.destroy', $equipe))->assertForbidden();
    $this->post(route('admin.eventos.credenciamento.checkin', [$this->evento, $this->participante]))->assertForbidden();

    expect(Frequencia::count())->toBe(1)->and(Equipe::count())->toBe(1)->and(Evento::count())->toBe(1);
});

it('relatório lista e exporta apenas eventos do próprio usuário', function () {
    $this->actingAs($this->outro)->get(route('admin.relatorios.frequencia.index'))
        ->assertOk()->assertDontSee($this->evento->titulo);

    $this->actingAs($this->outro)
        ->post(route('admin.relatorios.frequencia.exportar'), ['evento_id' => $this->evento->id])
        ->assertForbidden();
});

it('co-administrador (pivô editor) gerencia o evento, mas não designa co-administradores', function () {
    DB::table('evento_user')->insert([
        'evento_id' => $this->evento->id, 'user_id' => $this->outro->id, 'role' => 'editor',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($this->outro)->get(route('admin.eventos.show', $this->evento))->assertOk();
    $this->actingAs($this->outro)->post(route('admin.eventos.papeis.store', $this->evento), [
        'email' => criarUsuario()->email, 'papel' => 'editor',
    ])->assertForbidden();
});

it('usuário comum é redirecionado para a área do inscrito', function () {
    $this->actingAs(criarUsuario('user'))->get(route('admin.dashboard'))->assertRedirect(route('inscrito.dashboard'));
});

it('visitante é redirecionado ao login', function () {
    $this->get(route('admin.eventos.show', $this->evento))->assertRedirect(route('login'));
});
