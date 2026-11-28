<?php

use App\Models\ApresentacaoAvaliacao;
use App\Models\CertificadoAvaliador;
use App\Models\TrabalhoAvaliacao;
use App\Services\CertificadoTrabalhoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__ . '/../Helpers/TrabalhosHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    Illuminate\Support\Facades\Mail::fake();
    $this->dono = mkUser('admin', 'chefe@ifpa.edu.br');
    $this->evento = mkEvento($this->dono);
    $this->autor = mkUser('user', 'autora@gmail.com');
    $this->avaliador = mkUser('user', 'avaliador@gmail.com');
    $this->avaliador->update(['name' => 'Carlos Avaliador']);
    $this->t1 = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Solos da várzea'], 'aprovado');
    $this->t2 = mkTrabalho($this->evento, mkUser('user', 'outra@gmail.com'), ['titulo' => 'Peixes do lago'], 'aprovado');

    foreach ([[$this->t1, 'concluida', 1], [$this->t1, 'concluida', 2], [$this->t2, 'pendente', 1]] as [$t, $status, $rodada]) {
        TrabalhoAvaliacao::create(['trabalho_id' => $t->id, 'avaliador_id' => $this->avaliador->id, 'rodada' => $rodada, 'status' => $status]);
    }
    ApresentacaoAvaliacao::create(['trabalho_id' => $this->t2->id, 'avaliador_id' => $this->avaliador->id, 'status' => 'pendente']);
    $this->url = route('gestao.trabalhos.avaliadores-certificados.emitir', $this->evento);
});

it('lista os avaliadores por fase e emite só para quem concluiu, contando cada trabalho uma vez', function () {
    $this->actingAs($this->dono)->get(route('gestao.trabalhos.avaliadores-certificados.index', $this->evento))->assertOk()
        ->assertSee('Carlos Avaliador')->assertSee('Avaliação escrita')->assertSee('Aguardando concluir');

    $this->post($this->url, ['todos' => 1, 'carga_horaria' => 10])->assertSessionHas('success', '1 certificado(s) de avaliador emitido(s).');
    $c = CertificadoAvaliador::sole();
    expect($c->fase)->toBe('escrita')->and($c->quantidade)->toBe(1)->and($c->carga_horaria)->toBe(10);

    $this->post($this->url, ['user_id' => $this->avaliador->id, 'fase' => 'apresentacao'])->assertSessionHas('error');
});

it('o avaliador vê, baixa e valida o certificado, e a revogação o invalida', function () {
    $this->actingAs($this->dono)->post($this->url, ['todos' => 1]);
    $c = CertificadoAvaliador::sole();

    $this->actingAs($this->avaliador)->get(route('inscrito.dashboard'))->assertOk()
        ->assertSee('Avaliador(a) de trabalhos')->assertDontSee('Solos da várzea');
    $this->get(route('certificado.avaliador.download', $c->hash_validacao))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('certificado.validar', $c->hash_validacao))->assertRedirect(route('certificado.avaliador.validar', $c->hash_validacao));
    $this->get(route('certificado.avaliador.validar', $c->hash_validacao))->assertSee('Certificado autêntico')->assertSee('Carlos Avaliador');

    $this->actingAs($this->dono)->post(route('gestao.trabalhos.avaliadores-certificados.revogar', [$this->evento, $c]));
    $this->get(route('certificado.avaliador.download', $c->hash_validacao))->assertNotFound();
});

it('quem não gere os trabalhos não emite', function () {
    $this->actingAs($this->avaliador)->post($this->url, ['todos' => 1])->assertForbidden();
    expect(CertificadoAvaliador::count())->toBe(0);
});

it('o certificado de autoria aparece no perfil do autor e não no de outra pessoa', function () {
    $this->t1->update(['apresentado' => true]);
    app(CertificadoTrabalhoService::class)->emitir($this->t1->fresh(), $this->dono);

    $this->actingAs($this->autor)->get(route('inscrito.dashboard'))->assertOk()->assertSee('Solos da várzea')->assertSee('Baixar certificado');
    $this->actingAs($this->avaliador)->get(route('inscrito.dashboard'))->assertDontSee('Solos da várzea');
});
