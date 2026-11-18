<?php

use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('trabalhos');
    Mail::fake();

    $dono = User::factory()->create(['role' => 'admin']);
    $this->evento = Evento::create(['titulo' => 'Evento', 'slug' => 'ev-' . uniqid(), 'data_inicio' => now(), 'data_fim' => now()->addDays(3), 'local' => 'X', 'criado_por' => $dono->id]);
    TrabalhoConfig::create(['evento_id' => $this->evento->id, 'ativo' => true, 'submissao_inicio' => now()->subDay(), 'submissao_fim' => now()->addDays(5), 'max_autores' => 5, 'resumo_min_palavras' => 20, 'resumo_max_palavras' => 100]);
    $this->autor = User::factory()->create();
    $this->url = route('trabalhos.store', $this->evento->slug);
    $this->form = route('trabalhos.create', $this->evento->slug);
});

function payloadEnvio(array $autores, array $extra = []): array
{
    return array_merge([
        'acao' => 'submeter', 'titulo' => 'Um trabalho', 'resumo' => trim(str_repeat('palavra ', 40)), 'palavras_chave' => 'ensino, tecnologia, inovação',
        'tipo_trabalho' => 'pesquisa', 'modalidade_apresentacao' => 'oral', 'declaracao_ineditismo' => 1, 'declaracao_regulamento' => 1,
        'autores' => $autores, 'arquivo' => UploadedFile::fake()->createWithContent('t.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF"),
    ], $extra);
}

function autorBase(array $extra = []): array
{
    return array_merge(['nome_completo' => 'Ana Autora', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'instituicao' => 'IFPA', 'categoria_autor' => 'docente', 'tipo_autor' => 'autor_principal', 'apresentador' => 1, 'correspondente' => 1], $extra);
}

it('demais autores marcados como apresentador (sem campo de CPF no formulário) não derrubam o envio com erro 500', function () {
    // linha sem a chave "cpf": o formulário dos "demais autores" não tem esse campo
    $extra = ['nome_completo' => 'Bia Coautora', 'email' => 'bia@x.com', 'instituicao' => 'IFPA', 'categoria_autor' => 'discente', 'tipo_autor' => 'coautor', 'apresentador' => 1];

    $this->actingAs($this->autor)->post($this->url, payloadEnvio([autorBase(), $extra]))->assertSessionHasNoErrors();
    expect(Trabalho::count())->toBe(1);
});

it('apresentador entre os demais autores sem e-mail gera erro claro, por autor, em vez de 500', function () {
    $extra = ['nome_completo' => 'Bia Coautora', 'instituicao' => 'IFPA', 'tipo_autor' => 'coautor', 'apresentador' => 1];

    $this->actingAs($this->autor)->from($this->form)->post($this->url, payloadEnvio([autorBase(), $extra]))->assertRedirect($this->form)
        ->assertSessionHasErrors(['autores.1.email', 'autores.1.categoria_autor']);
    expect(session('errors')->first('autores.1.email'))->toContain('autor 2')->toContain('Bia Coautora');
    expect(Trabalho::count())->toBe(0);

    // a tela volta com os dados e põe o erro no cartão do autor (não só uma lista genérica no topo)
    $r = $this->get($this->form)->assertOk();
    $r->assertSee('errosAutores', false)->assertSee('Informe o e-mail do autor 2', false)->assertSee('Bia Coautora', false);
});

it('apresentador principal continua exigindo CPF quando o formulário o oferece', function () {
    $this->actingAs($this->autor)->post($this->url, payloadEnvio([autorBase(['cpf' => ''])]))->assertSessionHasErrors('autores.0.cpf');
    expect(Trabalho::count())->toBe(0);
});

it('formulário de novo trabalho guarda rascunho no navegador e mantém a sessão viva; visitante não', function () {
    $this->actingAs($this->autor)->get($this->form)->assertOk()
        ->assertSee('trabalho-rascunho:' . $this->evento->id . ':' . $this->autor->id, false)
        ->assertSee('sessao', false)->assertSee('ping\\u0022', false)->assertSee('restaurarRascunho', false)->assertSee('selecione o PDF novamente', false);

    auth()->logout();
    $this->app['auth']->forgetGuards();
    $guest = $this->get($this->form)->assertOk();
    $guest->assertDontSee('trabalho-rascunho:', false)->assertSee('pingUrl\\u0022:null', false);
});

it('rota de ping da sessão exige login e responde sem conteúdo', function () {
    $this->get(route('sessao.ping'))->assertRedirect(route('login'));
    $this->actingAs($this->autor)->get(route('sessao.ping'))->assertNoContent();
});

it('sessão expirada (419) volta à mesma tela com o texto digitado, sem senha nem arquivo, e avisa', function () {
    Route::post('/_teste-419', fn () => throw new TokenMismatchException)->middleware('web');

    $this->from($this->form)->post('/_teste-419', ['titulo' => 'Meu trabalho', 'resumo' => 'texto longo', 'password' => 'segredo', '_token' => 'velho'])
        ->assertRedirect($this->form)->assertSessionHas('erro_sessao');
    expect(session()->getOldInput('titulo'))->toBe('Meu trabalho')->and(session()->getOldInput('resumo'))->toBe('texto longo')
        ->and(session()->hasOldInput('password'))->toBeFalse()->and(session()->hasOldInput('_token'))->toBeFalse();

    $this->actingAs($this->autor)->get($this->form)->assertSee('Sua sessão expirou por inatividade', false);
});

it('depois de enviar, a tela do trabalho apaga o rascunho guardado no navegador', function () {
    $this->actingAs($this->autor)->post($this->url, payloadEnvio([autorBase()]))->assertSessionHasNoErrors();
    $t = Trabalho::firstOrFail();
    $this->get(route('trabalhos.show', $t))->assertOk()->assertSee("localStorage.removeItem('trabalho-rascunho:{$this->evento->id}:{$this->autor->id}')", false);
});
