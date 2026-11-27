<?php

use App\Models\Curso;
use App\Models\Participante;

it('unifica o curso "Integrado ao Ensino Médio" no correto, levando turmas e alunos; sem o correto, renomeia', function () {
    $certo = Curso::create(['nome' => 'Técnico em Informática']);
    $t2024 = $certo->turmas()->create(['ano' => '2024']);
    $errado = Curso::create(['nome' => 'Técnico em Informática Integrado ao Ensino Médio']);
    $e2024 = $errado->turmas()->create(['ano' => '2024']);
    $e2026 = $errado->turmas()->create(['ano' => '2026']);
    $soIntegrado = Curso::create(['nome' => 'Técnico em Florestas Integrado ao Ensino Médio']);
    $f = $soIntegrado->turmas()->create(['ano' => '2025']);
    $proeja = Curso::create(['nome' => 'Técnico em Informática Integrado ao Ensino Médio - PROEJA']);

    $aluno = fn (int $turma, string $cpf) => Participante::create(['nome_completo' => "Aluno {$turma}", 'cpf' => $cpf, 'email' => "a{$turma}@x.com", 'tipo_vinculo' => 'aluno', 'matricula' => (string) $turma, 'turma_id' => $turma]);
    $a = $aluno($e2024->id, '111.444.777-35');
    $b = $aluno($e2026->id, '529.982.247-25');
    $c = $aluno($f->id, '390.533.447-05');

    // simulação não grava
    $this->artisan('cursos:unificar-integrados')->expectsOutputToContain('unificar em "Técnico em Informática"')->assertSuccessful();
    expect(Curso::count())->toBe(4);

    $this->artisan('cursos:unificar-integrados', ['--aplicar' => true])->assertSuccessful();

    expect(Curso::find($errado->id))->toBeNull()->and(Curso::count())->toBe(3);
    expect($a->fresh()->turma_id)->toBe($t2024->id);                                              // mesmo ano: turma fundida
    expect($b->fresh()->turma->curso_id)->toBe($certo->id)->and($b->fresh()->turma->ano)->toBe('2026');   // ano novo: turma muda de curso
    expect($c->fresh()->turma->curso->nome)->toBe('Técnico em Florestas');                        // sem o correto: renomeado
    expect($proeja->fresh()->nome)->toBe('Técnico em Informática Integrado ao Ensino Médio - PROEJA');

    $this->artisan('cursos:unificar-integrados', ['--aplicar' => true])->expectsOutputToContain('Nenhum curso')->assertSuccessful();
});

it('junta dois cursos escolhidos (por nome ou número), só gravando com --aplicar', function () {
    $singular = Curso::create(['nome' => 'Técnico em Floresta']);
    $plural = Curso::create(['nome' => 'Técnico em Florestas']);
    $turma = $singular->turmas()->create(['ano' => '2024']);
    $aluno = Participante::create(['nome_completo' => 'Aluno F', 'cpf' => '111.444.777-35', 'email' => 'f@x.com', 'tipo_vinculo' => 'aluno', 'matricula' => '1', 'turma_id' => $turma->id]);

    $this->artisan('cursos:unificar-integrados', ['--de' => 'tecnico em floresta', '--para' => (string) $plural->id])->assertSuccessful();
    expect(Curso::count())->toBe(2);

    $this->artisan('cursos:unificar-integrados', ['--de' => 'Inexistente', '--para' => (string) $plural->id, '--aplicar' => true])->assertFailed();

    $this->artisan('cursos:unificar-integrados', ['--de' => 'tecnico em floresta', '--para' => 'Técnico em Florestas', '--aplicar' => true])->assertSuccessful();
    expect(Curso::count())->toBe(1)->and($aluno->fresh()->turma->curso_id)->toBe($plural->id);
});
