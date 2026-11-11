<?php

use App\Models\Curso;
use App\Models\Participante;
use App\Models\Turma;

beforeEach(function () {
    $this->admin = mkUser('admin');
    $this->usuario = mkUser('user', 'aluno@x.com');
});

it('só administradores acessam a gestão de cursos e turmas', function () {
    $this->get(route('admin.cursos.index'))->assertRedirect(route('login'));
    // quem não é administrador é barrado pelo middleware (redireciona) e nada é gravado
    $this->actingAs($this->usuario)->get(route('admin.cursos.index'))->assertRedirect();
    $this->post(route('admin.cursos.store'), ['nome' => 'X'])->assertRedirect();
    expect(Curso::count())->toBe(0);

    $this->actingAs($this->admin)->get(route('admin.cursos.index'))->assertOk()->assertSee('Cursos e Turmas')->assertSee('Novo curso');
});

it('cadastra, renomeia e exclui um curso; nomes repetidos são recusados', function () {
    $this->actingAs($this->admin);
    $this->post(route('admin.cursos.store'), ['nome' => '  Técnico em Informática '])->assertSessionHasNoErrors();
    $curso = Curso::firstWhere('nome', 'Técnico em Informática');
    expect($curso)->not->toBeNull();

    $this->post(route('admin.cursos.store'), ['nome' => 'Técnico em Informática'])->assertSessionHasErrors(['nome' => 'Já existe um curso com este nome.']);
    $this->post(route('admin.cursos.store'), ['nome' => ''])->assertSessionHasErrors('nome');

    $this->put(route('admin.cursos.update', $curso), ['nome' => 'Técnico em Informática para Internet'])->assertSessionHasNoErrors();
    expect($curso->fresh()->nome)->toBe('Técnico em Informática para Internet');

    $outro = Curso::create(['nome' => 'Agropecuária']);
    $this->put(route('admin.cursos.update', $curso), ['nome' => 'Agropecuária'])->assertSessionHasErrors('nome');
    $this->put(route('admin.cursos.update', $curso), ['nome' => 'Técnico em Informática para Internet'])->assertSessionHasNoErrors();   // mesmo nome do próprio curso é permitido

    $this->delete(route('admin.cursos.destroy', $outro))->assertSessionHas('success');
    expect(Curso::find($outro->id))->toBeNull();
});

it('cadastra, altera e exclui turmas; o ano não se repete dentro do curso', function () {
    $this->actingAs($this->admin);
    $curso = Curso::create(['nome' => 'Informática']);
    $outro = Curso::create(['nome' => 'Agropecuária']);

    $this->post(route('admin.turmas.store', $curso), ['ano' => '2025'])->assertSessionHasNoErrors();
    $this->post(route('admin.turmas.store', $curso), ['ano' => '2025'])->assertSessionHasErrors(['ano' => 'Este curso já tem uma turma com este ano.']);
    $this->post(route('admin.turmas.store', $outro), ['ano' => '2025'])->assertSessionHasNoErrors();   // outro curso pode ter o mesmo ano
    $this->post(route('admin.turmas.store', $curso), ['ano' => ''])->assertSessionHasErrors('ano');

    $turma = $curso->turmas()->first();
    $this->put(route('admin.turmas.update', $turma), ['ano' => '2026'])->assertSessionHasNoErrors();
    expect($turma->fresh()->ano)->toBe('2026')->and($turma->fresh()->nome_completo)->toBe('Informática - 2026');

    $this->delete(route('admin.turmas.destroy', $turma))->assertSessionHas('success');
    expect(Turma::find($turma->id))->toBeNull();
});

it('curso ou turma com alunos vinculados não pode ser excluído', function () {
    $this->actingAs($this->admin);
    $curso = Curso::create(['nome' => 'Informática']);
    $turma = $curso->turmas()->create(['ano' => '2025']);
    Participante::create(['nome_completo' => 'Aluno Um', 'cpf' => '111.444.777-35', 'email' => 'a@x.com', 'tipo_vinculo' => 'aluno', 'turma_id' => $turma->id]);

    $this->delete(route('admin.turmas.destroy', $turma))->assertSessionHas('error');
    $this->delete(route('admin.cursos.destroy', $curso))->assertSessionHas('error');
    expect(Turma::find($turma->id))->not->toBeNull()->and(Curso::find($curso->id))->not->toBeNull();

    $this->get(route('admin.cursos.index'))->assertOk()->assertSee('1 aluno(s)')->assertSee('Informática');
});

it('os cursos e turmas cadastrados aparecem nas telas de inscrição de alunos', function () {
    $curso = Curso::create(['nome' => 'Curso Novo Visível']);
    $curso->turmas()->create(['ano' => '2031']);
    $dono = $this->admin;
    $evento = mkEvento($dono);

    $this->actingAs($this->usuario)->get(route('evento.publico.show', $evento->slug))->assertOk()->assertSee('Curso Novo Visível - 2031');
});

it('o menu da gestão mostra Cursos e Turmas apenas para administradores', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Cursos e Turmas')->assertSee(route('admin.cursos.index'), false);
    $this->actingAs($this->usuario)->get(route('inscrito.dashboard'))->assertOk()->assertDontSee(route('admin.cursos.index'), false);
});

it('todos os perfis têm o item "Ir para o Site" no menu', function () {
    $avaliador = mkUser('user', 'aval@x.com');
    $evento = mkEvento($this->admin);
    mkPapel($avaliador, $evento, 'avaliador');

    foreach ([$this->admin, $this->usuario, $avaliador] as $u) {
        $rota = $u->role === 'admin' ? route('admin.dashboard') : route('inscrito.dashboard');
        $this->actingAs($u)->get($rota)->assertOk()->assertSee('Ir para o Site')->assertSee('href="' . route('home') . '"', false);
    }
});
