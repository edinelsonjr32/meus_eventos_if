<?php

use App\Models\Evento;
use App\Support\HtmlSeguro;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = mkUser('admin');
});

function dadosDoEvento(array $extra = []): array
{
    return array_merge([
        'titulo' => 'Evento Rico', 'data_inicio' => now()->addDays(5)->format('Y-m-d\TH:i'), 'data_fim' => now()->addDays(6)->format('Y-m-d\TH:i'),
        'local' => 'Auditório', 'cor_fundo' => '#10b981',
    ], $extra);
}

// ---------- Limpeza do HTML ----------
it('mantém a formatação e as imagens do editor', function () {
    $html = '<h2>Programação</h2><p>Texto com <strong>negrito</strong>, <em>itálico</em>, <u>sublinhado</u> e <a href="https://ifpa.edu.br">link</a>.</p>'
        . '<ul><li>Um</li><li>Dois</li></ul><blockquote>Citação</blockquote>'
        . '<figure class="image image_resized" style="width:50%;"><img src="https://site.com/a.png" alt="Foto"><figcaption>Legenda</figcaption></figure>'
        . '<p style="text-align:center;"><span style="color:#ff0000;">Vermelho</span></p>'
        . '<figure class="table"><table><thead><tr><th>Hora</th></tr></thead><tbody><tr><td>08:00</td></tr></tbody></table></figure>';

    $limpo = HtmlSeguro::limpar($html);

    expect($limpo)->toContain('<h2>Programação</h2>')->toContain('<strong>negrito</strong>')->toContain('<em>itálico</em>')->toContain('<u>sublinhado</u>')
        ->toContain('href="https://ifpa.edu.br"')->toContain('<li>Dois</li>')->toContain('<blockquote>Citação</blockquote>')
        ->toContain('<img')->toContain('src="https://site.com/a.png"')->toContain('alt="Foto"')->toContain('<figcaption>Legenda</figcaption>')
        ->toContain('text-align:center')->toContain('color:#ff0000')->toContain('<table')->toContain('<td>08:00</td>');
});

it('remove scripts, eventos, iframes, javascript: e estilos perigosos', function () {
    $sujo = '<p onclick="alert(1)">Oi</p><script>alert(2)</script><img src="x" onerror="alert(3)"><a href="javascript:alert(4)">clique</a>'
        . '<iframe src="https://evil.com"></iframe><style>body{display:none}</style><p style="position:fixed;background:url(javascript:alert(5))">fixo</p>'
        . '<object data="x"></object><form action="/x"><input></form><svg onload="alert(6)"></svg>';

    $limpo = HtmlSeguro::limpar($sujo);

    foreach (['<script', 'onclick', 'onerror', 'javascript:', '<iframe', '<style', 'position:fixed', '<object', '<form', '<input', '<svg', 'onload'] as $perigo) {
        expect($limpo)->not->toContain($perigo);
    }
    expect($limpo)->toContain('Oi')->toContain('clique');
});

it('links externos abrem em outra aba com rel seguro; editor vazio vira nulo', function () {
    expect(HtmlSeguro::limpar('<p><a href="https://x.com" target="_blank">x</a></p>'))->toContain('noopener')
        ->and(HtmlSeguro::limpar('<p>&nbsp;</p>'))->toBeNull()->and(HtmlSeguro::limpar('   '))->toBeNull()->and(HtmlSeguro::limpar(null))->toBeNull()
        ->and(HtmlSeguro::limpar('<p><img src="https://x.com/a.png"></p>'))->toContain('<img');   // só imagem também é conteúdo
});

it('texto simples antigo mantém as quebras de linha e é escapado', function () {
    expect(HtmlSeguro::paraExibir("Linha 1\nLinha 2 & y"))->toContain('Linha 1<br />')->toContain('&amp; y')->and(HtmlSeguro::paraExibir("a\nb"))->toContain('<br')
        ->and(HtmlSeguro::paraExibir('<p>Já é <strong>HTML</strong></p>'))->toBe('<p>Já é <strong>HTML</strong></p>')
        ->and(HtmlSeguro::paraExibir(null))->toBe('');
});

// ---------- Salvar o evento ----------
it('criar e editar o evento grava a descrição já limpa', function () {
    $this->actingAs($this->admin)->post(route('admin.eventos.store'), dadosDoEvento(['descricao' => '<h2>Bem-vindos</h2><p onclick="x()">Oi <strong>você</strong></p><script>alert(1)</script>']))->assertSessionHasNoErrors();
    $e = Evento::firstWhere('titulo', 'Evento Rico');
    expect($e->descricao)->toContain('<h2>Bem-vindos</h2>')->toContain('<strong>você</strong>')->not->toContain('script')->not->toContain('onclick');

    $this->put(route('admin.eventos.update', $e), dadosDoEvento(['titulo' => 'Evento Rico', 'descricao' => '<p>Novo <a href="javascript:alert(1)">x</a></p>']))->assertSessionHasNoErrors();
    expect($e->fresh()->descricao)->toContain('Novo')->not->toContain('javascript');

    $this->put(route('admin.eventos.update', $e), dadosDoEvento(['titulo' => 'Evento Rico', 'descricao' => '<p>&nbsp;</p>']));
    expect($e->fresh()->descricao)->toBeNull();
});

it('a página pública mostra a descrição formatada e segura, inclusive a de eventos antigos com HTML sujo no banco', function () {
    $e = Evento::create(['titulo' => 'Antigo', 'slug' => 'antigo-2026', 'descricao' => '<h3>Sobre nós</h3><p>Texto <b>forte</b></p><script>alert(1)</script><img src="x" onerror="alert(2)">',
        'data_inicio' => now()->addDay(), 'data_fim' => now()->addDays(2), 'local' => 'L', 'criado_por' => $this->admin->id]);

    $r = $this->get(route('evento.publico.show', $e->slug))->assertOk()->assertSee('<h3>Sobre nós</h3>', false)->assertSee('<b>forte</b>', false);
    expect($r->getContent())->not->toContain('<script>alert(1)</script>')->not->toContain('onerror');

    $simples = Evento::create(['titulo' => 'Simples', 'slug' => 'simples-2026', 'descricao' => "Primeira linha\nSegunda linha", 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDays(2), 'local' => 'L', 'criado_por' => $this->admin->id]);
    $this->get(route('evento.publico.show', $simples->slug))->assertSee('Primeira linha<br', false);
});

// ---------- Upload de imagens ----------
it('o administrador envia imagens para o editor e recebe o endereço público', function () {
    $r = $this->actingAs($this->admin)->postJson(route('admin.eventos.imagens.store'), ['upload' => UploadedFile::fake()->image('foto.jpg', 800, 600)])->assertOk()->assertJsonStructure(['url']);

    $url = $r->json('url');
    expect($url)->toStartWith('/storage/eventos/descricoes/');   // caminho relativo ao site
    Storage::disk('public')->assertExists(ltrim(str($url)->after('/storage/')->toString(), '/'));
});

it('o upload recusa o que não é imagem (SVG, PDF, PHP), imagens grandes e quem não é administrador', function () {
    $this->actingAs($this->admin);
    foreach ([UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"/>'), UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'), UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')] as $arq) {
        $this->postJson(route('admin.eventos.imagens.store'), ['upload' => $arq])->assertStatus(422)->assertJsonStructure(['error' => ['message']]);
    }
    $this->postJson(route('admin.eventos.imagens.store'), ['upload' => UploadedFile::fake()->image('grande.png')->size(5000)])->assertStatus(422)->assertJsonPath('error.message', 'A imagem pode ter no máximo 4 MB.');
    $this->postJson(route('admin.eventos.imagens.store'), [])->assertStatus(422);
    expect(Storage::disk('public')->allFiles())->toBe([]);

    auth()->logout();
    $this->actingAs(mkUser('user', 'u@x.com'))->post(route('admin.eventos.imagens.store'), ['upload' => UploadedFile::fake()->image('a.png')])->assertRedirect();
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

// ---------- Formulários ----------
it('os formulários de criar e editar usam o editor completo com envio de imagens', function () {
    $e = Evento::create(['titulo' => 'Para Editar', 'slug' => 'para-editar-2026', 'descricao' => '<p>Texto <strong>antigo</strong></p>', 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDays(2), 'local' => 'L', 'criado_por' => $this->admin->id]);
    $this->actingAs($this->admin);

    foreach ([route('admin.eventos.create'), route('admin.eventos.edit', $e)] as $url) {
        $this->get($url)->assertOk()->assertSee('class="editor-rico"', false)->assertSee('data-upload="' . route('admin.eventos.imagens.store') . '"', false)
            ->assertSee('ckeditor5/41.4.2/super-build', false)->assertSee("'insertImage'", false)->assertSee("'insertTable'", false)->assertSee("'fontColor'", false)->assertSee("'alignment'", false);
    }
    // o conteúdo atual vai escapado dentro do campo de texto, de onde o editor o carrega
    $this->get(route('admin.eventos.edit', $e))->assertSee('&lt;strong&gt;antigo&lt;/strong&gt;', false);
});

it('imagens enviadas (caminho relativo) sobrevivem à limpeza do HTML', function () {
    $limpo = HtmlSeguro::limpar('<figure class="image"><img src="/storage/eventos/descricoes/2026/11/a.png"></figure>');
    expect($limpo)->toContain('src="/storage/eventos/descricoes/2026/11/a.png"')->toContain('<figure class="image">');
});

it('se o purificador falhar, a tela não cai: o texto sai escapado, sem tags', function () {
    $falho = Mockery::mock(\HTMLPurifier::class);
    $falho->shouldReceive('purify')->andThrow(new RuntimeException('pasta de cache sem permissão'));
    $prop = new ReflectionProperty(HtmlSeguro::class, 'purifier');
    $prop->setAccessible(true);
    $original = $prop->getValue();
    $prop->setValue(null, $falho);

    try {
        $r = HtmlSeguro::limpar("<p onclick=\"x()\">Olá <script>alert(1)</script>mundo</p>\nlinha 2");
        expect($r)->toContain('Olá')->toContain('mundo')->not->toContain('<script')->not->toContain('onclick');

        $e = Evento::create(['titulo' => 'Resiliente', 'slug' => 'resiliente-2026', 'descricao' => '<p>Texto <b>x</b></p>', 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDays(2), 'local' => 'L', 'criado_por' => $this->admin->id]);
        $this->actingAs($this->admin)->get(route('admin.eventos.edit', $e))->assertOk();
        $this->get(route('evento.publico.show', $e->slug))->assertOk();
    } finally {
        $prop->setValue(null, $original);
    }
});
