<?php

use App\Models\Curso;
use App\Models\Participante;
use App\Models\Turma;

function csvDiscentes(array $linhas): string
{
    $caminho = tempnam(sys_get_temp_dir(), 'disc') . '.csv';
    $conteudo = "Campus;Matrícula;Nome;Curso;Status;CPF;AnoIngresso\n" . implode("\n", $linhas) . "\n";
    file_put_contents($caminho, mb_convert_encoding($conteudo, 'ISO-8859-1', 'UTF-8'));   // o SIGAA exporta em ISO-8859-1

    return $caminho;
}

function mkPart(string $nome, string $cpf, string $tipo = 'externo'): Participante
{
    return Participante::create(['nome_completo' => $nome, 'cpf' => $cpf, 'email' => Str::slug($nome) . '@x.com', 'tipo_vinculo' => $tipo]);
}

it('simula sem gravar e, com --aplicar, atualiza tipo, matrícula, curso e turma pelo nome (sem diferenciar maiúsculas/acentos)', function () {
    $p = mkPart('joão da  silva', '111.444.777-35');
    Curso::create(['nome' => 'Técnico em Informática Integrado ao Ensino Médio']);
    $csv = csvDiscentes(['OBI;20241234567;JOÃO DA SILVA;TÉCNICO EM INFORMÁTICA INTEGRADO AO ENSINO MÉDIO;ATIVO;11144477735;2024']);

    $this->artisan('participantes:importar-discentes', ['arquivo' => $csv])->assertSuccessful();
    expect($p->fresh()->tipo_vinculo)->toBe('externo')->and(Turma::count())->toBe(0);

    $this->artisan('participantes:importar-discentes', ['arquivo' => $csv, '--aplicar' => true])->assertSuccessful();
    $p = $p->fresh();
    expect($p->tipo_vinculo)->toBe('aluno')->and($p->matricula)->toBe('20241234567')->and($p->vinculo_confirmado_em)->not->toBeNull();
    expect($p->turma->ano)->toBe('2024')->and($p->turma->curso->nome)->toBe('Técnico em Informática Integrado ao Ensino Médio');
});

it('cria cursos faltantes só com --criar-cursos; sem ele, não atualiza e avisa', function () {
    $p = mkPart('Maria Souza', '529.982.247-25');
    $csv = csvDiscentes(['OBI;20251111111;MARIA SOUZA;TÉCNICO EM FLORESTAS INTEGRADO AO ENSINO MÉDIO;ATIVO;52998224725;2025']);

    $this->artisan('participantes:importar-discentes', ['arquivo' => $csv, '--aplicar' => true])->expectsOutputToContain('--criar-cursos')->assertSuccessful();
    expect($p->fresh()->tipo_vinculo)->toBe('externo')->and(Curso::count())->toBe(0);

    $this->artisan('participantes:importar-discentes', ['arquivo' => $csv, '--aplicar' => true, '--criar-cursos' => true])->assertSuccessful();
    expect(Curso::first()->nome)->toBe('Técnico em Florestas Integrado ao Ensino Médio')->and($p->fresh()->tipo_vinculo)->toBe('aluno');

    // rodar de novo não duplica curso nem turma
    $this->artisan('participantes:importar-discentes', ['arquivo' => $csv, '--aplicar' => true, '--criar-cursos' => true])->assertSuccessful();
    expect(Curso::count())->toBe(1)->and(Turma::count())->toBe(1);
});

it('não mexe em homônimo com CPF diferente, em servidor, em nome ambíguo nem em quem não está cadastrado', function () {
    $outro = mkPart('Ana Lima', '111.444.777-35');               // mesmo nome, CPF diferente do da planilha
    $serv = mkPart('Bruno Servidor', '529.982.247-25', 'servidor');
    $d1 = mkPart('Carla Dupla', '390.533.447-05');
    $d2 = mkPart('carla dupla', '168.995.350-09');
    Curso::create(['nome' => 'Técnico em Meio Ambiente']);
    $csv = csvDiscentes([
        'OBI;20241;ANA LIMA;TÉCNICO EM MEIO AMBIENTE;ATIVO;52998224725;2024',
        'OBI;20242;BRUNO SERVIDOR;TÉCNICO EM MEIO AMBIENTE;ATIVO;52998224725;2024',
        'OBI;20243;CARLA DUPLA;TÉCNICO EM MEIO AMBIENTE;ATIVO;;2024',
        'OBI;20244;PESSOA NAO CADASTRADA;TÉCNICO EM MEIO AMBIENTE;ATIVO;;2024',
    ]);

    $this->artisan('participantes:importar-discentes', ['arquivo' => $csv, '--aplicar' => true])->assertSuccessful();
    expect($outro->fresh()->tipo_vinculo)->toBe('externo')->and($serv->fresh()->tipo_vinculo)->toBe('servidor')
        ->and($d1->fresh()->tipo_vinculo)->toBe('externo')->and($d2->fresh()->tipo_vinculo)->toBe('externo')
        ->and(Participante::whereNotNull('matricula')->count())->toBe(0);

    // com CPF na planilha, o desempate do nome repetido funciona
    $csv2 = csvDiscentes(['OBI;20245;CARLA DUPLA;TÉCNICO EM MEIO AMBIENTE;ATIVO;16899535009;2024']);
    $this->artisan('participantes:importar-discentes', ['arquivo' => $csv2, '--aplicar' => true])->assertSuccessful();
    expect($d2->fresh()->tipo_vinculo)->toBe('aluno')->and($d1->fresh()->tipo_vinculo)->toBe('externo');
});

it('lê também planilha .xlsx (matrícula e CPF numéricos, CPF sem o zero à esquerda)', function () {
    $p = mkPart('Zé Ninguém', '012.345.678-90');
    Curso::create(['nome' => 'Técnico em Agroecologia']);

    $planilha = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $aba = $planilha->getActiveSheet();
    $aba->fromArray([['Campus', 'Matrícula', 'Nome', 'Curso', 'Status', 'CPF', 'AnoIngresso'], ['OBI', 20231551173, 'ZÉ NINGUÉM', 'TÉCNICO EM AGROECOLOGIA', 'ATIVO', 1234567890, 2023]]);
    $caminho = tempnam(sys_get_temp_dir(), 'disc') . '.xlsx';
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($planilha))->save($caminho);

    $this->artisan('participantes:importar-discentes', ['arquivo' => $caminho, '--aplicar' => true])->assertSuccessful();
    expect($p->fresh()->tipo_vinculo)->toBe('aluno')->and($p->fresh()->matricula)->toBe('20231551173')->and($p->fresh()->turma->ano)->toBe('2023');
});

it('repara planilha com acentos corrompidos (ISO-8859-1 lido como MacRoman) antes de comparar e criar cursos', function () {
    $moj = fn (string $s) => iconv('MACINTOSH', 'UTF-8', mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8'));
    expect($moj('TÉCNICO'))->not->toBe('TÉCNICO');

    $p = mkPart('João da Conceição', '529.982.247-25');
    $planilha = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $planilha->getActiveSheet()->fromArray([
        ['Campus', 'Matrícula', 'Nome', 'Curso', 'Status', 'CPF', 'AnoIngresso'],
        ['OBI', 20241, $moj('JOÃO DA CONCEIÇÃO'), $moj('TÉCNICO EM AGROPECUÁRIA INTEGRADO AO ENSINO MÉDIO'), 'ATIVO', 52998224725, 2024],
    ]);
    $caminho = tempnam(sys_get_temp_dir(), 'disc') . '.xlsx';
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($planilha))->save($caminho);

    $this->artisan('participantes:importar-discentes', ['arquivo' => $caminho, '--aplicar' => true, '--criar-cursos' => true])->assertSuccessful();
    expect($p->fresh()->tipo_vinculo)->toBe('aluno')->and(Curso::first()->nome)->toBe('Técnico em Agropecuária Integrado ao Ensino Médio');
});

it('--corrigir-conflitos: servidor vira aluno, CPF divergente é corrigido e duplicados são unificados mantendo frequências', function () {
    $curso = Curso::create(['nome' => 'Técnico em Meio Ambiente']);
    $evento = mkEvento(mkUser('admin'));
    $novaAtividade = fn ($n) => \App\Models\Atividade::create(['evento_id' => $evento->id, 'titulo' => "Atv {$n}", 'tipo' => 'palestra', 'data_inicio' => now(), 'data_fim' => now()->addHours(2), 'carga_horaria' => 2, 'vagas' => 50, 'token_frequencia' => "QR-{$n}"]);
    $atv = $novaAtividade(1);

    $servidora = mkPart('Iane Soares', '049.444.777-25', 'servidor');
    $cpfErrado = mkPart('Maiane Sodre', '057.111.222-10');
    // Natália: dois cadastros; o de CPF certo tem 1 presença, o de CPF errado tem 2 (uma na mesma atividade)
    $certo = mkPart('Natália Santana', '529.982.247-25', 'aluno'); $certo->update(['matricula' => '777']);
    $errado = mkPart('natalia santana', '390.533.447-05', 'aluno'); $errado->update(['matricula' => '777']);
    $atv2 = $novaAtividade(2);
    foreach ([[$certo, $atv], [$errado, $atv], [$errado, $atv2]] as [$pp, $aa]) {
        \App\Models\Frequencia::create(['participante_id' => $pp->id, 'atividade_id' => $aa->id, 'data_registro' => now(), 'hash_validacao' => Str::random(20)]);
    }
    $outraPessoa = mkPart('Outro Nome', '168.995.350-09');   // dono do CPF da planilha, mas é outra pessoa

    $csv = csvDiscentes([
        'OBI;20261;IANE SOARES;TÉCNICO EM MEIO AMBIENTE;ATIVO;04944477725;2026',
        'OBI;20262;MAIANE SODRÉ;TÉCNICO EM MEIO AMBIENTE;ATIVO;05711122214;2024',
        'OBI;777;NATÁLIA SANTANA;TÉCNICO EM MEIO AMBIENTE;ATIVO;52998224725;2026',
        'OBI;20264;FULANA BLOQUEADA;TÉCNICO EM MEIO AMBIENTE;ATIVO;16899535009;2026',
    ]);
    mkPart('Fulana Bloqueada', '111.444.777-35');

    // Sem a opção, nada disso é mexido
    $this->artisan('participantes:importar-discentes', ['arquivo' => $csv, '--aplicar' => true])->assertSuccessful();
    expect($servidora->fresh()->tipo_vinculo)->toBe('servidor')->and(Participante::count())->toBe(6);

    // Simulação com a opção: não grava
    $this->artisan('participantes:importar-discentes', ['arquivo' => $csv, '--corrigir-conflitos' => true])->assertSuccessful();
    expect(Participante::count())->toBe(6)->and($cpfErrado->fresh()->cpf)->toBe('057.111.222-10');

    $this->artisan('participantes:importar-discentes', ['arquivo' => $csv, '--corrigir-conflitos' => true, '--aplicar' => true])->assertSuccessful();

    expect($servidora->fresh()->tipo_vinculo)->toBe('aluno')->and($servidora->fresh()->matricula)->toBe('20261');
    expect($cpfErrado->fresh()->cpf)->toBe('057.111.222-14')->and($cpfErrado->fresh()->tipo_vinculo)->toBe('aluno');

    // Natália: ficou 1 cadastro (o com mais presenças), com o CPF da planilha e as 2 presenças (a repetida não duplica)
    $natalias = Participante::where('nome_completo', 'like', '%atal%')->get();
    expect($natalias)->toHaveCount(1)->and($natalias[0]->id)->toBe($errado->id)->and($natalias[0]->cpf)->toBe('529.982.247-25');
    expect(\App\Models\Frequencia::where('participante_id', $errado->id)->count())->toBe(2)->and(\App\Models\Frequencia::count())->toBe(2);

    // Dono do CPF com outro nome: bloqueado, nada alterado
    expect(Participante::where('nome_completo', 'Fulana Bloqueada')->first()->tipo_vinculo)->toBe('externo')->and($outraPessoa->fresh())->not->toBeNull();
});
