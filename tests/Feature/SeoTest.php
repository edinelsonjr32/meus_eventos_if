<?php

use App\Models\ChamadaVoluntario;
use App\Models\Evento;

beforeEach(function () {
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->evento->update(['titulo' => 'XI Semana de Ciência, Tecnologia e Cultura', 'slug' => 'xi-semana', 'local' => 'IFPA Campus Óbidos', 'descricao' => '<p>Palestras, <strong>minicursos</strong> e oficinas &amp; muito mais.</p>']);
});

it('página do evento tem título, descrição, imagem, canonical e dados estruturados para a prévia do link', function () {
    $r = $this->get(route('evento.publico.show', 'xi-semana'))->assertOk();

    $r->assertSee('<title>XI Semana de Ciência, Tecnologia e Cultura · ', false)
        ->assertSee('<meta property="og:title" content="XI Semana de Ciência, Tecnologia e Cultura">', false)
        ->assertSee('<meta property="og:type" content="website">', false)
        ->assertSee('<link rel="canonical" href="' . route('evento.publico.show', 'xi-semana') . '">', false)
        ->assertSee('<meta property="og:image" content="' . asset('og-padrao.png') . '">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('application/ld+json', false)->assertSee('"@type":"Event"', false);

    // descrição: período + local + começo do texto do evento, sem tags HTML
    $html = $r->getContent();
    preg_match('/<meta name="description" content="([^"]*)"/', $html, $m);
    expect($m[1])->toContain('IFPA Campus Óbidos')->toContain('Palestras, minicursos e oficinas')->not->toContain('<')->not->toContain('login');
    expect(mb_strlen(html_entity_decode($m[1])))->toBeLessThanOrEqual(200);
    expect($html)->not->toContain('noindex');
});

it('home usa título e descrição do portal e imagem padrão', function () {
    $this->get('/')->assertOk()->assertSee('og:description', false)->assertSee('Eventos acadêmicos do IFPA Campus Óbidos', false)->assertSee(asset('og-padrao.png'), false);
});

it('login e cadastro não são indexados e têm descrição própria (não "login seguro")', function () {
    foreach (['/login', '/register'] as $url) {
        $this->get($url)->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false)->assertSee('<meta property="og:title" content="Entrar ou criar conta">', false);
    }
});

it('formulário de chamada compartilhado pelo link mostra título e descrição da chamada (não a tela de login)', function () {
    $c = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'Chamada de Voluntários XI Semana', 'descricao' => '<p>Venha <strong>ajudar</strong> no evento.</p>', 'ativa' => true]);
    $this->get(route('voluntariado.create', $c))->assertOk()
        ->assertSee('<meta property="og:title" content="Chamada de Voluntários XI Semana">', false)
        ->assertSee('<meta property="og:description" content="Venha ajudar no evento.">', false)
        ->assertSee('<link rel="canonical" href="' . route('voluntariado.create', $c) . '">', false);
});

it('robots.txt bloqueia áreas privadas e aponta o sitemap; sitemap lista eventos e chamadas abertas', function () {
    $c = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'Voluntários', 'ativa' => true]);

    $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /admin', false)->assertSee('Disallow: /minha-area', false)->assertSee('Sitemap: ' . url('/sitemap.xml'), false);

    \Illuminate\Support\Facades\Cache::forget('sitemap-xml');
    $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<urlset', false)->assertSee(route('evento.publico.show', 'xi-semana'), false)->assertSee(route('voluntariado.create', $c), false);
});
