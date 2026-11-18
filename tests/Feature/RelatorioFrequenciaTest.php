<?php

use App\Exports\FrequenciaGeralExport;
use App\Models\Atividade;
use App\Models\Curso;
use App\Models\Frequencia;
use App\Models\Participante;
use App\Models\Turma;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->admin = mkUser('admin');
    $this->evento = mkEvento($this->admin);
    $this->actingAs($this->admin);

    $curso = Curso::create(['nome' => 'Técnico em Informática']);
    $this->turmaA = Turma::create(['curso_id' => $curso->id, 'ano' => 2026]);
    $curso2 = Curso::create(['nome' => 'Agropecuária']);
    $this->turmaB = Turma::create(['curso_id' => $curso2->id, 'ano' => 2025]);

    $this->jogo = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Final de handebol', 'tipo' => 'jogo', 'data_inicio' => '2026-11-20 09:00', 'data_fim' => '2026-11-20 11:00', 'carga_horaria' => 2, 'gera_certificado' => false, 'token_frequencia' => 'T1']);
    $this->palestra = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Palestra de IA', 'tipo' => 'palestra', 'data_inicio' => '2026-11-21 14:00', 'data_fim' => '2026-11-21 16:00', 'carga_horaria' => 3, 'token_frequencia' => 'T2']);

    $mk = function ($nome, $cpf, $turma, $mat, $vinculo = 'aluno') {
        return Participante::create(['nome_completo' => $nome, 'cpf' => $cpf, 'email' => strtolower(explode(' ', $nome)[0]) . '@x.com', 'tipo_vinculo' => $vinculo, 'matricula' => $mat, 'turma_id' => $turma?->id]);
    };
    $this->ana = $mk('Ana Souza', '529.982.247-25', $this->turmaA, '2026001');
    $this->bia = $mk('Bia Lima', '111.444.777-35', $this->turmaB, '2025002');
    $this->prof = $mk('Prof Carlos', '390.533.447-05', null, null, 'servidor');

    $reg = function ($p, $a, $quando) {
        Frequencia::create(['participante_id' => $p->id, 'atividade_id' => $a->id, 'data_registro' => $quando, 'hash_validacao' => (string) \Illuminate\Support\Str::uuid(), 'tipo_participacao' => 'ouvinte']);
    };
    $reg($this->ana, $this->jogo, '2026-11-20 09:05');
    $reg($this->ana, $this->palestra, '2026-11-21 14:02');
    $reg($this->bia, $this->palestra, '2026-11-21 14:10');
    $reg($this->prof, $this->palestra, '2026-11-21 14:11');
});

function lerPlanilha($export): array
{
    $arquivo = tempnam(sys_get_temp_dir(), 'freq') . '.xlsx';
    file_put_contents($arquivo, Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));
    $livro = IOFactory::load($arquivo);
    @unlink($arquivo);

    return ['livro' => $livro, 'abas' => $livro->getSheetNames()];
}

it('gera planilha em abas com filtro, cabeçalho fixo e dados corretos (só alunos por padrão, sem CPF)', function () {
    $r = lerPlanilha(new FrequenciaGeralExport(['eventos_permitidos' => [$this->evento->id]], 'Admin'));
    expect($r['abas'])->toBe(['Resumo', 'Frequência', 'Por aluno', 'Por atividade', 'Matriz']);

    $det = $r['livro']->getSheetByName('Frequência');
    expect($det->getAutoFilter()->getRange())->not->toBe('')
        ->and($det->getFreezePane())->toBe('A2')
        ->and($det->getHighestRow())->toBe(4)   // cabeçalho + 3 presenças de alunos (servidor fora)
        ->and($det->getCell('C2')->getValue())->toBe('Final de handebol')
        ->and($det->getCell('G2')->getValue())->toBe('Ana Souza')
        ->and($det->getCell('H2')->getValue())->toBe('2026001')
        ->and($det->getCell('N2')->getValue())->toBe('Não')   // jogo não gera certificado
        ->and($det->getCell('N3')->getValue())->toBe('Sim');
    foreach ($det->toArray() as $linha) {
        expect(implode('|', $linha))->not->toContain('529.982.247-25');
    }

    $alunos = $r['livro']->getSheetByName('Por aluno');
    expect($alunos->getCell('A2')->getValue())->toBe('Ana Souza')
        ->and((int) $alunos->getCell('F2')->getValue())->toBe(2)
        ->and((float) $alunos->getCell('G2')->getValue())->toBe(5.0)   // 2h + 3h
        ->and($alunos->getCell('A3')->getValue())->toBe('Bia Lima');

    $ativ = $r['livro']->getSheetByName('Por atividade');
    expect((int) $ativ->getCell('G3')->getValue())->toBe(2);   // palestra: 2 alunos

    $m = $r['livro']->getSheetByName('Matriz');
    expect($m->getCell('A2')->getValue())->toBe('Ana Souza')->and($m->getCell('E2')->getValue())->toBe('P')->and($m->getCell('F2')->getValue())->toBe('P')
        ->and($m->getCell('E3')->getValue())->toBeNull()->and($m->getCell('F3')->getValue())->toBe('P');

    $resumo = collect($r['livro']->getSheetByName('Resumo')->toArray())->flatten()->filter()->implode(' | ');
    expect($resumo)->toContain('Registros de presença')->toContain('Somente alunos')->toContain('Técnico em Informática')->toContain('LGPD');
});

it('filtra por turma, período e vínculo', function () {
    $linhas = fn ($f) => lerPlanilha(new FrequenciaGeralExport($f + ['eventos_permitidos' => [$this->evento->id]]))['livro']->getSheetByName('Frequência')->getHighestRow() - 1;

    expect($linhas(['turma_id' => $this->turmaA->id]))->toBe(2)
        ->and($linhas(['curso_id' => $this->turmaB->curso_id]))->toBe(1)
        ->and($linhas(['data_de' => '2026-11-21']))->toBe(2)
        ->and($linhas(['data_ate' => '2026-11-20']))->toBe(1)
        ->and($linhas(['vinculo' => 'todos']))->toBe(4);
});

it('tela de relatório tem os novos filtros e o download sai com nome organizado', function () {
    $this->get(route('admin.relatorios.frequencia.index'))->assertOk()->assertSee('Filtrar por Turma')->assertSee('Atividades a partir de')->assertSee('Somente alunos');

    $r = $this->post(route('admin.relatorios.frequencia.exportar'), ['evento_id' => $this->evento->id, 'turma_id' => $this->turmaA->id, 'vinculo' => 'aluno']);
    $r->assertOk();
    expect($r->headers->get('content-disposition'))->toContain('Frequencia_')->toContain('.xlsx');

    $this->post(route('admin.relatorios.frequencia.exportar'), ['data_de' => '2026-11-22', 'data_ate' => '2026-11-20'])->assertSessionHasErrors('data_ate');
});
