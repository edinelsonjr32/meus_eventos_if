<?php

use App\Models\Atividade;
use App\Models\Equipe;
use App\Models\Participante;

beforeEach(function () {
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->pessoa = Participante::create(['nome_completo' => 'Ana Equipe', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_vinculo' => 'externo']);
});

it('adiciona membro com carga horária informada (e padrão 20 quando vazia) e não duplica', function () {
    $this->actingAs($this->dono)->post(route('admin.eventos.equipe.store', $this->evento->id), ['cpf_busca' => '111.444.777-35', 'funcao' => 'Monitor', 'carga_horaria' => 8])->assertSessionHas('success');
    expect(Equipe::first()->carga_horaria_trabalho)->toBe(8);

    $this->post(route('admin.eventos.equipe.store', $this->evento->id), ['cpf_busca' => '111.444.777-35', 'funcao' => 'Monitor', 'carga_horaria' => 8])->assertSessionHas('warning');
    expect(Equipe::count())->toBe(1);

    $outra = Participante::create(['nome_completo' => 'Bia', 'cpf' => '529.982.247-25', 'email' => 'b@x.com', 'tipo_vinculo' => 'externo']);
    $this->post(route('admin.eventos.equipe.store', $this->evento->id), ['cpf_busca' => $outra->cpf, 'funcao' => 'Apoio Técnico', 'carga_horaria' => ''])->assertSessionHas('success');
    expect(Equipe::where('participante_id', $outra->id)->value('carga_horaria_trabalho'))->toBe(20);

    // função fora da lista e C.H. inválida são recusadas
    $this->post(route('admin.eventos.equipe.store', $this->evento->id), ['cpf_busca' => '000', 'funcao' => 'Rei', 'carga_horaria' => 0])->assertSessionHasErrors(['funcao', 'carga_horaria']);
});

it('a tela mostra a carga horária salva do membro e permite editar', function () {
    $m = Equipe::create(['evento_id' => $this->evento->id, 'participante_id' => $this->pessoa->id, 'funcao' => 'Monitor', 'carga_horaria_trabalho' => 12]);

    $this->actingAs($this->dono)->get(route('admin.eventos.equipe.index', $this->evento->id))->assertOk()->assertSee('12h')->assertDontSee('N/A');

    $this->put(route('admin.equipe.update', $m->id), ['funcao' => 'Coordenador', 'carga_horaria' => 30])->assertSessionHas('success');
    expect($m->fresh()->funcao)->toBe('Coordenador')->and($m->fresh()->carga_horaria_trabalho)->toBe(30);

    $this->put(route('admin.equipe.update', $m->id), ['funcao' => 'Coordenador', 'carga_horaria' => 0])->assertSessionHasErrors('carga_horaria');
});

it('emite o certificado da equipe (idempotente), valida publicamente, reflete edição e revoga', function () {
    $m = Equipe::create(['evento_id' => $this->evento->id, 'participante_id' => $this->pessoa->id, 'funcao' => 'Monitor', 'carga_horaria_trabalho' => 12]);

    $this->actingAs($this->dono)->post(route('admin.equipe.emitir', $m->id))->assertSessionHas('success');
    $hash = $m->fresh()->hash_validacao;
    expect($hash)->not->toBeNull();

    $this->post(route('admin.equipe.emitir', $m->id))->assertSessionHas('warning');
    expect($m->fresh()->hash_validacao)->toBe($hash);

    auth()->logout();
    $this->get(route('certificado.equipe.validar', $hash))->assertOk()->assertSee('Ana Equipe')->assertSee('Monitor')->assertSee('12h');
    $resp = $this->get(route('certificado.equipe.download', $hash))->assertOk();
    expect($resp->headers->get('content-type'))->toContain('pdf');

    $this->actingAs($this->dono)->put(route('admin.equipe.update', $m->id), ['funcao' => 'Monitor', 'carga_horaria' => 40]);
    auth()->logout();
    $this->get(route('certificado.equipe.validar', $hash))->assertSee('40h');

    $this->actingAs($this->dono)->post(route('admin.equipe.revogar', $m->id));
    auth()->logout();
    $this->get(route('certificado.equipe.validar', $hash))->assertSee('não existe ou foi revogado');
    $this->get(route('certificado.equipe.download', $hash))->assertNotFound();
});

it('emite os certificados de toda a equipe de uma vez e o texto é escapado', function () {
    $outra = Participante::create(['nome_completo' => '<b>Hack</b>', 'cpf' => '529.982.247-25', 'email' => 'b@x.com', 'tipo_vinculo' => 'externo']);
    Equipe::create(['evento_id' => $this->evento->id, 'participante_id' => $this->pessoa->id, 'funcao' => 'Monitor', 'carga_horaria_trabalho' => 12]);
    $h = Equipe::create(['evento_id' => $this->evento->id, 'participante_id' => $outra->id, 'funcao' => 'Voluntário', 'carga_horaria_trabalho' => 5]);

    $this->actingAs($this->dono)->post(route('admin.eventos.equipe.emitir-todos', $this->evento->id))->assertSessionHas('success');
    expect(Equipe::whereNotNull('hash_validacao')->count())->toBe(2);

    $texto = app(\App\Services\CertificadoEquipeService::class)->textos($h->fresh());
    expect($texto['corpo'])->not->toContain('<B>HACK')->toContain('&lt;B&gt;HACK&lt;/B&gt;');
});

it('só quem gerencia o evento mexe na equipe', function () {
    $m = Equipe::create(['evento_id' => $this->evento->id, 'participante_id' => $this->pessoa->id, 'funcao' => 'Monitor', 'carga_horaria_trabalho' => 12]);
    $outro = mkUser('admin', 'outro-adm@x.com');

    $this->actingAs($outro)->post(route('admin.equipe.emitir', $m->id))->assertForbidden();
    $this->put(route('admin.equipe.update', $m->id), ['funcao' => 'Monitor', 'carga_horaria' => 3])->assertForbidden();
    expect($m->fresh()->carga_horaria_trabalho)->toBe(12);
});

it('edita a atividade: carga horária, fim recalculado, vagas não menores que os inscritos', function () {
    $a = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Oficina', 'tipo' => 'oficina', 'data_inicio' => '2026-12-01 08:00', 'data_fim' => '2026-12-01 12:00', 'carga_horaria' => 4, 'vagas' => 10, 'token_frequencia' => 'ED-1']);

    $this->actingAs($this->dono)->get(route('admin.atividades.edit', $a->id))->assertOk()->assertSee('Oficina');

    $this->put(route('admin.atividades.update', $a->id), ['titulo' => 'Oficina 2', 'tipo' => 'minicurso', 'responsavel' => 'Prof. X', 'local_id' => \App\Models\Local::create(['evento_id' => $a->evento_id, 'nome' => 'Sala 3', 'tipo' => 'sala'])->id, 'data_inicio' => '2026-12-02T09:00', 'carga_horaria' => 6, 'vagas' => 20])->assertRedirect();
    $a->refresh();
    expect($a->titulo)->toBe('Oficina 2')->and($a->carga_horaria)->toBe(6)->and($a->responsavel)->toBe('Prof. X')->and($a->local)->toBe('Sala 3')
        ->and($a->data_fim->format('Y-m-d H:i'))->toBe('2026-12-02 15:00')->and($a->vagas)->toBe(20);

    $a->inscritos()->attach($this->pessoa->id);
    $this->put(route('admin.atividades.update', $a->id), ['titulo' => 'Oficina 2', 'tipo' => 'minicurso', 'data_inicio' => '2026-12-02T09:00', 'carga_horaria' => 6, 'vagas' => 0 + 1])->assertRedirect();
    $this->put(route('admin.atividades.update', $a->id), ['titulo' => 'X', 'tipo' => 'minicurso', 'data_inicio' => '2026-12-02T09:00', 'carga_horaria' => 6, 'vagas' => 0])->assertRedirect();
    $this->put(route('admin.atividades.update', $a->id), ['titulo' => 'X', 'tipo' => 'minicurso', 'data_inicio' => '2026-12-02T09:00', 'carga_horaria' => 0])->assertSessionHasErrors('carga_horaria');
});
