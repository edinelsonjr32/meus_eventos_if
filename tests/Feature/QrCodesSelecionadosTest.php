<?php

use App\Models\Atividade;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__ . '/../Helpers/TrabalhosHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->dono = mkUser('admin', 'chefe@ifpa.edu.br');
    $this->evento = mkEvento($this->dono);
    $nova = fn ($evento, $titulo) => Atividade::create(['evento_id' => $evento->id, 'titulo' => $titulo, 'tipo' => 'palestra',
        'token_frequencia' => \Illuminate\Support\Str::random(10), 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHour(), 'carga_horaria' => 1]);
    $this->a = $nova($this->evento, 'Abertura');
    $this->b = $nova($this->evento, 'Oficina');
    $this->alheia = $nova(mkEvento($this->dono), 'De outro evento');

    // capta as atividades que foram para o PDF
    $this->titulos = null;
    view()->composer('admin.atividades.qrcodes-pdf', function ($v) {
        $this->titulos = collect($v->getData()['paginas'])->map(fn ($p) => $p['atividade']->titulo)->all();
    });
    $this->url = fn (array $q = []) => route('admin.eventos.atividades.qrcodes', [$this->evento->id] + $q);
});

it('gera o PDF só com as atividades selecionadas deste evento', function () {
    $this->actingAs($this->dono)->get(($this->url)(['atividades' => [$this->b->id, $this->alheia->id]]))->assertOk()->assertHeader('content-type', 'application/pdf');

    expect($this->titulos)->toBe(['Oficina']);
});

it('sem seleção gera todas e oferece a escolha na tela do evento', function () {
    $this->actingAs($this->dono)->get(($this->url)())->assertOk();
    expect($this->titulos)->toHaveCount(2);

    $this->get(route('admin.eventos.show', $this->evento->id))->assertOk()
        ->assertSee('id="modalQrcodes"', false)->assertSee('name="atividades[]" value="' . $this->a->id . '"', false);
});

it('avisa quando a seleção não é deste evento', function () {
    $this->actingAs($this->dono)->get(($this->url)(['atividades' => [$this->alheia->id]]))->assertRedirect()->assertSessionHas('error');
});
