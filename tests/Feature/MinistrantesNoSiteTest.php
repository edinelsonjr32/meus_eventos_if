<?php

use App\Models\Atividade;
use App\Models\MinistranteEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__ . '/../Helpers/TrabalhosHelpers.php';

uses(RefreshDatabase::class);

function atividadeCom(\App\Models\Evento $e, string $titulo, ?string $responsavel): Atividade
{
    return Atividade::create(['evento_id' => $e->id, 'titulo' => $titulo, 'tipo' => 'minicurso', 'responsavel' => $responsavel, 'token_frequencia' => \Illuminate\Support\Str::random(32),
        'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHours(2), 'carga_horaria' => 2, 'vagas' => 30]);
}

beforeEach(function () {
    $this->dono = mkUser('admin', 'chefe@ifpa.edu.br');
    $this->evento = mkEvento($this->dono);
    $this->evento->update(['data_inicio' => now()->addDay(), 'data_fim' => now()->addDays(3)]);
});

function pessoasPublicas($teste)
{
    return $teste->get(route('evento.publico.show', $teste->evento->slug))->assertOk()->viewData('ministrantes');
}

it('mostra no site quem está nas atividades, sem repetir a mesma pessoa', function () {
    atividadeCom($this->evento, 'Oficina de Robótica', 'JOÃO DA SILVA, Maria Souza');
    atividadeCom($this->evento, 'Minicurso de Redes', 'João da Silva');
    atividadeCom($this->evento, 'Abertura', null);

    $pessoas = pessoasPublicas($this);

    expect($pessoas->pluck('nome')->all())->toBe(['João da Silva', 'Maria Souza'])
        ->and($pessoas[0]->iniciais)->toBe('JS')
        ->and($pessoas[0]->atividades)->toHaveCount(2);
    $this->get(route('evento.publico.show', $this->evento->slug))->assertSee('id="ministrantes"', false)->assertSee('href="#ministrantes"', false);
});

it('não mostra a seção quando ninguém ministra', function () {
    atividadeCom($this->evento, 'Abertura', null);

    $this->get(route('evento.publico.show', $this->evento->slug))->assertOk()->assertDontSee('id="ministrantes"', false);
});

it('permite pôr foto e descrição, trocar a foto, ocultar e remover', function () {
    Storage::fake('public');
    atividadeCom($this->evento, 'Oficina', 'Maria Souza');
    $url = route('admin.ministrantes.salvar', $this->evento);

    $this->actingAs($this->dono)->post($url, ['chave' => 'maria souza', 'nome' => 'Profa. Maria Souza', 'descricao' => 'Professora de Redes', 'visivel' => 1, 'foto' => UploadedFile::fake()->image('a.jpg', 300, 300)])
        ->assertSessionHasNoErrors()->assertRedirect();
    $p = MinistranteEvento::sole();
    Storage::disk('public')->assertExists($p->caminho_foto);

    $pessoas = pessoasPublicas($this);
    expect($pessoas)->toHaveCount(1)
        ->and($pessoas[0]->nome)->toBe('Profa. Maria Souza')
        ->and($pessoas[0]->descricao)->toBe('Professora de Redes')
        ->and($pessoas[0]->foto)->not->toBeNull()
        ->and($pessoas[0]->atividades)->toBe(['Oficina']);

    $antiga = $p->caminho_foto;
    $this->post($url, ['chave' => 'maria souza', 'nome' => 'Maria Souza', 'foto' => UploadedFile::fake()->image('b.png')]);
    Storage::disk('public')->assertMissing($antiga);
    expect(pessoasPublicas($this))->toHaveCount(0);   // sem "mostrar no site"

    $this->delete(route('admin.ministrantes.destroy', [$this->evento, $p]))->assertRedirect();
    expect(MinistranteEvento::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(pessoasPublicas($this)->pluck('nome')->all())->toBe(['Maria Souza']);
});

it('acrescenta quem não está na programação e respeita a ordem', function () {
    atividadeCom($this->evento, 'Oficina', 'Ana Lima');
    $this->actingAs($this->dono)->post(route('admin.ministrantes.salvar', $this->evento), ['nome' => 'Zeca Palestrante', 'ordem' => 1, 'visivel' => 1]);

    expect(pessoasPublicas($this)->pluck('nome')->all())->toBe(['Zeca Palestrante', 'Ana Lima']);
    $this->get(route('admin.ministrantes.index', $this->evento))->assertOk()->assertSee('Sem atividade na programação');
});

it('só quem administra o evento altera os ministrantes', function () {
    $outro = mkUser('user', 'fulano@gmail.com');

    $this->actingAs($outro)->get(route('admin.ministrantes.index', $this->evento))->assertRedirect();
    $this->actingAs($outro)->post(route('admin.ministrantes.salvar', $this->evento), ['nome' => 'Intruso'])->assertRedirect();
    expect(MinistranteEvento::count())->toBe(0);
});
