<?php

use App\Models\Atividade;
use App\Models\AtividadeResponsavel;
use App\Models\MinistranteEvento;
use App\Models\Participante;
use App\Services\Lgpd\TitularService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__ . '/../Helpers/TrabalhosHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->dono = mkUser('admin', 'chefe@ifpa.edu.br');
    $this->evento = mkEvento($this->dono);
    $this->maria = Participante::create(['nome_completo' => 'MARIA DE SOUZA', 'cpf' => '111.444.777-35', 'email' => 'maria@gmail.com', 'tipo_vinculo' => 'servidor']);
    $this->conta = mkUser('user', 'joao@gmail.com');
    $this->conta->update(['name' => 'João Pereira']);
    $this->base = ['titulo' => 'Oficina de Solos', 'tipo' => 'Oficina', 'data_inicio' => now()->addDay()->format('Y-m-d\TH:i'), 'carga_horaria' => 2, 'responsaveis_enviado' => 1];
});

function criarComResponsaveis($teste, array $extra = []): Atividade
{
    $teste->actingAs($teste->dono)->post(route('admin.eventos.atividades.store', $teste->evento), $teste->base + $extra)->assertSessionHasNoErrors();

    return Atividade::sole();
}

it('busca participantes e contas sem cadastro, só para quem administra o evento', function () {
    $url = route('admin.eventos.buscar-responsaveis', $this->evento);

    $nomes = fn (string $q) => collect($this->actingAs($this->dono)->getJson("{$url}?q={$q}")->assertOk()->json('results'))->pluck('id')->all();
    expect($nomes('maria'))->toBe(["p:{$this->maria->id}"])
        ->and($nomes('joao@'))->toBe(["u:{$this->conta->id}"])
        ->and($nomes('ninguem'))->toBe([]);

    $this->actingAs($this->conta)->getJson("{$url}?q=maria")->assertRedirect()->assertDontSee("MARIA");
});

it('cria a atividade com pessoas vinculadas e nomes sem cadastro, sem repetir', function () {
    $a = criarComResponsaveis($this, ['responsaveis' => ["p:{$this->maria->id}", "u:{$this->conta->id}"], 'responsavel' => 'Convidado Externo, maria de souza']);

    expect($a->responsavel)->toBe('Maria de Souza, João Pereira, Convidado Externo')
        ->and($a->responsaveis->pluck('participante_id')->filter()->values()->all())->toBe([$this->maria->id])
        ->and($a->responsaveis->pluck('user_id')->filter()->values()->all())->toBe([$this->conta->id]);

    $html = $this->get(route('admin.atividades.edit', $a))->assertOk()->getContent();
    expect($html)->toContain('value="Convidado Externo"')->toContain("p:{$this->maria->id}");
});

it('na edição troca e remove os vínculos, e sem o seletor não mexe neles', function () {
    $a = criarComResponsaveis($this, ['responsaveis' => ["p:{$this->maria->id}"]]);
    $url = route('admin.atividades.update', $a);

    $sem = collect($this->base)->except('responsaveis_enviado')->all();
    $this->put($url, $sem + ['responsavel' => $a->responsavel])->assertSessionHasNoErrors();
    expect(AtividadeResponsavel::count())->toBe(1);

    $this->put($url, $this->base + ['responsaveis' => ["u:{$this->conta->id}"]])->assertSessionHasNoErrors();
    expect($a->refresh()->responsavel)->toBe('João Pereira')->and(AtividadeResponsavel::sole()->user_id)->toBe($this->conta->id);

    $this->put($url, $this->base)->assertSessionHasNoErrors();
    expect($a->refresh()->responsavel)->toBeNull()->and(AtividadeResponsavel::count())->toBe(0);
});

it('recusa referência malformada e ignora pessoa inexistente', function () {
    $this->actingAs($this->dono)->post(route('admin.eventos.atividades.store', $this->evento), $this->base + ['responsaveis' => ['x:1']])->assertSessionHasErrors('responsaveis.0');

    $a = criarComResponsaveis($this, ['responsaveis' => ['p:999999'], 'responsavel' => 'Fulana']);
    expect($a->responsavel)->toBe('Fulana')->and(AtividadeResponsavel::count())->toBe(0);
});

it('a anonimização retira o vínculo, o nome da programação e a foto de ministrante', function () {
    $a = criarComResponsaveis($this, ['responsaveis' => ["p:{$this->maria->id}"], 'responsavel' => 'Convidado Externo']);
    MinistranteEvento::create(['evento_id' => $this->evento->id, 'chave' => 'maria de souza', 'nome' => 'Maria de Souza']);

    app(TitularService::class)->anonimizar('maria@gmail.com');

    expect($a->refresh()->responsavel)->toBe('Convidado Externo')
        ->and(AtividadeResponsavel::count())->toBe(0)
        ->and(MinistranteEvento::count())->toBe(0);
});
