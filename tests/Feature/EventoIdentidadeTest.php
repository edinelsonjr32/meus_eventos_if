<?php

use App\Models\Evento;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->create(['role' => 'admin']);
});

function dadosEvento(array $extra = []): array
{
    return array_merge([
        'titulo' => 'Semana de Teste', 'descricao' => 'x', 'data_inicio' => now()->addDays(10)->format('Y-m-d\TH:i'),
        'data_fim' => now()->addDays(11)->format('Y-m-d\TH:i'), 'local' => 'Auditório', 'cor_fundo' => '#10b981',
    ], $extra);
}

it('cadastra o evento com logo e banner e exibe na página pública', function () {
    $this->actingAs($this->admin)->post(route('admin.eventos.store'), dadosEvento([
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        'banner' => UploadedFile::fake()->image('banner.jpg', 1600, 500),
    ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.eventos.index'));

    $e = Evento::firstWhere('titulo', 'Semana de Teste');
    expect($e->caminho_logo)->not->toBeNull()->and($e->caminho_banner)->not->toBeNull();
    Storage::disk('public')->assertExists([$e->caminho_logo, $e->caminho_banner]);

    auth()->logout();
    $this->get(route('evento.publico.show', $e->slug))->assertOk()->assertSee($e->logoUrl(), false)->assertSee($e->bannerUrl(), false);
});

it('edita trocando e removendo logo e banner, apagando o arquivo antigo', function () {
    $e = Evento::create(['titulo' => 'Evento X', 'slug' => 'evento-x-2026', 'descricao' => 'x', 'data_inicio' => now()->addDays(5), 'data_fim' => now()->addDays(6), 'local' => 'L', 'criado_por' => $this->admin->id]);
    $this->actingAs($this->admin)->put(route('admin.eventos.update', $e), dadosEvento([
        'logo' => UploadedFile::fake()->image('a.png'), 'banner' => UploadedFile::fake()->image('b.png'),
    ]))->assertSessionHasNoErrors();
    $e->refresh();
    $logoAntiga = $e->caminho_logo;

    $this->put(route('admin.eventos.update', $e), dadosEvento(['logo' => UploadedFile::fake()->image('c.png')]))->assertSessionHasNoErrors();
    $e->refresh();
    expect($e->caminho_logo)->not->toBe($logoAntiga);
    Storage::disk('public')->assertMissing($logoAntiga);
    Storage::disk('public')->assertExists($e->caminho_banner);   // não enviar de novo mantém o banner

    $this->put(route('admin.eventos.update', $e), dadosEvento(['remover_logo' => 1, 'remover_banner' => 1]))->assertSessionHasNoErrors();
    $e->refresh();
    expect($e->caminho_logo)->toBeNull()->and($e->caminho_banner)->toBeNull();
});

it('recusa arquivos que não são imagem (inclusive SVG) ou grandes demais', function () {
    $this->actingAs($this->admin)->post(route('admin.eventos.store'), dadosEvento(['logo' => UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"/>')]))->assertSessionHasErrors('logo');
    $this->post(route('admin.eventos.store'), dadosEvento(['banner' => UploadedFile::fake()->image('g.jpg')->size(6000)]))->assertSessionHasErrors('banner');
    $this->post(route('admin.eventos.store'), dadosEvento(['logo' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')]))->assertSessionHasErrors('logo');
});

it('criar evento com título repetido no mesmo ano gera outro endereço em vez de erro 500', function () {
    $this->actingAs($this->admin);

    foreach (range(1, 3) as $_) {
        $this->post(route('admin.eventos.store'), dadosEvento(['titulo' => 'Evento Repetido']))->assertSessionHasNoErrors()->assertRedirect(route('admin.eventos.index'));
    }

    $slugs = Evento::where('titulo', 'Evento Repetido')->orderBy('id')->pluck('slug')->all();
    $base = 'evento-repetido-' . date('Y');
    expect($slugs)->toBe([$base, "{$base}-2", "{$base}-3"]);

    // também considera eventos já excluídos (o slug continua ocupado no banco)
    Evento::where('slug', "{$base}-3")->first()->delete();
    $this->post(route('admin.eventos.store'), dadosEvento(['titulo' => 'Evento Repetido']))->assertSessionHasNoErrors();
    expect(Evento::where('slug', "{$base}-4")->exists())->toBeTrue();
});

it('a organização escolhe exibir ou não a logo e o banner na página pública', function () {
    $this->actingAs($this->admin)->post(route('admin.eventos.store'), dadosEvento([
        'titulo' => 'Evento Visual', 'logo' => UploadedFile::fake()->image('l.png'), 'banner' => UploadedFile::fake()->image('b.png', 1600, 500),
    ]))->assertSessionHasNoErrors();
    $e = Evento::firstWhere('titulo', 'Evento Visual');
    expect($e->exibeLogo())->toBeTrue()->and($e->exibeBanner())->toBeTrue();   // padrão: exibir

    // desmarcar as duas opções: os arquivos continuam guardados, mas não aparecem
    $this->put(route('admin.eventos.update', $e), dadosEvento(['titulo' => 'Evento Visual', 'exibir_logo' => 0, 'exibir_banner' => 0]))->assertSessionHasNoErrors();
    $e->refresh();
    expect($e->exibeLogo())->toBeFalse()->and($e->exibeBanner())->toBeFalse()->and($e->caminho_logo)->not->toBeNull()->and($e->caminho_banner)->not->toBeNull();
    auth()->logout();
    $this->get(route('evento.publico.show', $e->slug))->assertOk()->assertDontSee($e->logoUrl(), false)->assertDontSee($e->bannerUrl(), false);

    // só a logo
    $this->actingAs($this->admin)->put(route('admin.eventos.update', $e), dadosEvento(['titulo' => 'Evento Visual', 'exibir_logo' => 1, 'exibir_banner' => 0]));
    auth()->logout();
    $this->get(route('evento.publico.show', $e->slug))->assertSee($e->logoUrl(), false)->assertDontSee($e->bannerUrl(), false);

    // formulário antigo, sem os campos: mantém a escolha anterior
    $this->actingAs($this->admin)->put(route('admin.eventos.update', $e), dadosEvento(['titulo' => 'Evento Visual']));
    expect($e->fresh()->exibeLogo())->toBeTrue()->and($e->fresh()->exibeBanner())->toBeFalse();
});

it('o formulário do evento oferece as opções de exibição', function () {
    $this->actingAs($this->admin)->get(route('admin.eventos.create'))->assertOk()->assertSee('Exibir a logo na página pública')->assertSee('Exibir o banner na página pública');
});
