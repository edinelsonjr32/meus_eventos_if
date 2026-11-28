<?php

use App\Models\Atividade;
use App\Services\ApresentacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__ . '/../Helpers/TrabalhosHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    Illuminate\Support\Facades\Mail::fake();
    $this->dono = mkUser('admin', 'chefe@ifpa.edu.br');
    $this->evento = mkEvento($this->dono);
    $nova = fn (string $titulo, bool $sessao) => Atividade::create(['evento_id' => $this->evento->id, 'titulo' => $titulo, 'tipo' => 'oficina', 'sessao_trabalhos' => $sessao,
        'token_frequencia' => \Illuminate\Support\Str::random(10), 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHour(), 'carga_horaria' => 1]);
    $this->oficina = $nova('Oficina de Solos', false);
    $this->sessao = $nova('Comunicações orais', true);
    $this->trabalho = mkTrabalho($this->evento, mkUser('user', 'a@gmail.com'), [], 'aprovado');
});

it('só as sessões de trabalhos aparecem para escolha', function () {
    foreach (['gestao.trabalhos.certificados.index', 'gestao.trabalhos.apresentacoes.index'] as $rota) {
        if (!\Illuminate\Support\Facades\Route::has($rota)) {
            continue;
        }
        $vistas = $this->actingAs($this->dono)->get(route($rota, $this->evento))->assertOk()->viewData('atividades');
        expect($vistas->pluck('titulo')->all())->toBe(['Comunicações orais']);
    }
    $this->actingAs($this->dono)->get(route('gestao.trabalhos.show', $this->trabalho))->assertOk()
        ->assertSee('Comunicações orais')->assertDontSee('Oficina de Solos');
});

it('recusa usar uma oficina como sessão', function () {
    expect(fn () => app(ApresentacaoService::class)->definirSessao($this->trabalho, $this->oficina))->toThrow(DomainException::class);
    app(ApresentacaoService::class)->definirSessao($this->trabalho, $this->sessao);
    expect($this->trabalho->fresh()->atividade_id)->toBe($this->sessao->id);
});

it('marca na edição e não deixa desmarcar com trabalhos na sessão', function () {
    $dados = fn (Atividade $a, int $sessao) => ['titulo' => $a->titulo, 'tipo' => 'Oficina', 'data_inicio' => $a->data_inicio->format('Y-m-d\TH:i'), 'carga_horaria' => 1, 'sessao_trabalhos' => $sessao];

    $this->actingAs($this->dono)->put(route('admin.atividades.update', $this->oficina), $dados($this->oficina, 1))->assertSessionHasNoErrors();
    expect($this->oficina->fresh()->sessao_trabalhos)->toBeTrue();

    $this->trabalho->update(['atividade_id' => $this->sessao->id]);
    $this->put(route('admin.atividades.update', $this->sessao), $dados($this->sessao, 0))->assertSessionHasErrors('sessao_trabalhos');
    expect($this->sessao->fresh()->sessao_trabalhos)->toBeTrue();
});
