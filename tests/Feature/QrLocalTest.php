<?php

use App\Models\Atividade;
use App\Models\Frequencia;
use App\Models\Inscricao;
use App\Models\Participante;

it('nenhuma tela envia dados a serviço externo de QR Code', function () {
    $achados = collect(\Illuminate\Support\Facades\File::allFiles(resource_path('views')))
        ->filter(fn ($f) => str_contains($f->getContents(), 'qrserver.com'))
        ->map(fn ($f) => $f->getRelativePathname());

    expect($achados->all())->toBe([]);
});

it('a etiqueta de credenciamento gera o QR localmente e não expõe o CPF em URL externa', function () {
    $dono = mkUser('admin');
    $evento = mkEvento($dono);
    $p = Participante::create(['nome_completo' => 'Ana Teste', 'cpf' => '111.444.777-35', 'email' => 'a@x.com', 'tipo_vinculo' => 'externo']);
    Inscricao::create(['evento_id' => $evento->id, 'participante_id' => $p->id, 'data_inscricao' => now()]);

    $html = $this->actingAs($dono)->get(route('admin.eventos.credenciamento.etiqueta', [$evento, $p]))->assertOk()->getContent();

    expect($html)->toContain('data:image/svg+xml;base64,')->not->toContain('qrserver')->not->toContain('data=111.444');
});

it('o painel do evento mostra o QR de presença gerado localmente', function () {
    $dono = mkUser('admin');
    $evento = mkEvento($dono);
    Atividade::create(['evento_id' => $evento->id, 'titulo' => 'Palestra', 'tipo' => 'palestra', 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHours(2), 'carga_horaria' => 2, 'vagas' => 10, 'token_frequencia' => 'TOKEN123']);

    $this->actingAs($dono)->get(route('admin.eventos.show', $evento->id))->assertOk()->assertDontSee('qrserver', false);
});

it('o certificado de atividade é gerado com o QR local', function () {
    $dono = mkUser('admin');
    $evento = mkEvento($dono);
    $atividade = Atividade::create(['evento_id' => $evento->id, 'titulo' => 'Palestra', 'tipo' => 'palestra', 'data_inicio' => now(), 'data_fim' => now()->addHours(2), 'carga_horaria' => 2, 'vagas' => 10, 'token_frequencia' => 'TK']);
    $p = Participante::create(['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'a@x.com', 'tipo_vinculo' => 'externo']);
    Frequencia::create(['participante_id' => $p->id, 'atividade_id' => $atividade->id, 'data_registro' => now(), 'hash_validacao' => 'HASHQR1', 'tipo_participacao' => 'ouvinte']);

    $r = $this->get(route('certificado.download', 'HASHQR1'))->assertOk();
    expect(substr($r->getContent(), 0, 5))->toBe('%PDF-');
});
