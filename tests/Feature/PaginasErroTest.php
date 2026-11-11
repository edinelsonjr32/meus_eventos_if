<?php

use Symfony\Component\HttpKernel\Exception\HttpException;

dataset('codigos', [401, 403, 404, 419, 429, 500, 503]);

it('cada página de erro personalizada renderiza com botão de voltar ou ação própria e sem depender de CDN', function (int $codigo) {
    $html = view("errors.$codigo", ['exception' => new HttpException($codigo)])->render();

    expect($html)->toContain((string) $codigo)->and($html)->toContain('Meus Eventos')
        ->and($html)->not->toContain('cdn.')->and($html)->not->toContain('https://fonts');
    // 401 leva ao login e 503 recarrega; as demais têm o botão Voltar
    $temAcao = str_contains($html, 'id="btn-voltar"') || str_contains($html, 'Entrar') || str_contains($html, 'Tentar de novo');
    expect($temAcao)->toBeTrue();
    expect($html)->toContain('Página inicial');
})->with('codigos');

it('a página 404 aparece para endereço inexistente, com Voltar', function () {
    $this->get('/rota-que-nao-existe-123')->assertNotFound()->assertSee('Página não encontrada')->assertSee('Voltar')->assertSee('id="btn-voltar"', false);
});

it('a página 403 mostra a mensagem em português e, para quem está logado, o atalho Minha área', function () {
    $dono = mkUser('admin');
    $evento = mkEvento($dono);
    $outro = mkUser('admin');

    $this->actingAs($outro)->get(route('admin.eventos.edit', $evento->id))->assertForbidden()
        ->assertSee('Acesso negado')->assertSee('permissão')->assertSee('Voltar')->assertSee('Minha área')
        ->assertDontSee('This action is unauthorized');
});

it('a 403 mostra a mensagem personalizada do sistema quando ela existe', function () {
    $html = view('errors.403', ['exception' => new HttpException(403, 'Somente o criador do evento pode designar co-administradores.')])->render();
    expect($html)->toContain('Somente o criador do evento pode designar co-administradores.');

    $html = view('errors.403', ['exception' => new HttpException(403, 'This action is unauthorized.')])->render();
    expect($html)->toContain('Você não tem permissão')->and($html)->not->toContain('This action is unauthorized');
});

it('a mensagem de uma exceção não é vazada em outras páginas e é escapada', function () {
    $html = view('errors.500', ['exception' => new HttpException(500, '<script>alert(1)</script> SQLSTATE[23000]')])->render();
    expect($html)->not->toContain('SQLSTATE')->and($html)->not->toContain('<script>alert(1)</script>');

    $html = view('errors.403', ['exception' => new HttpException(403, '<script>alert(1)</script>')])->render();
    expect($html)->not->toContain('<script>alert(1)</script>')->and($html)->toContain('&lt;script&gt;');
});

it('a 500 e a 503 não consultam a conta (funcionam com o sistema fora do ar)', function () {
    foreach ([500, 503] as $c) {
        $html = view("errors.$c", ['exception' => new HttpException($c)])->render();
        expect($html)->not->toContain('Minha área');
    }
});

it('endereços de listagem/criação/detalhe de atividade redirecionam ao painel do evento em vez de dar erro 500', function () {
    $dono = mkUser('admin');
    $evento = mkEvento($dono);
    $atividade = \App\Models\Atividade::create(['evento_id' => $evento->id, 'titulo' => 'Oficina', 'tipo' => 'oficina', 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHour(), 'carga_horaria' => 1, 'vagas' => 5, 'token_frequencia' => uniqid()]);

    $this->actingAs($dono);
    foreach ([route('admin.eventos.show', $evento) . '/atividades', route('admin.eventos.show', $evento) . '/atividades/create', url("/admin/atividades/{$atividade->id}")] as $url) {
        $this->get($url)->assertRedirect(route('admin.eventos.show', $evento));
    }
});

it('o layout prepara as tabelas para o modo cartão no celular e avisa quando a rolagem lateral é necessária', function () {
    $this->actingAs(mkUser('admin'))->get(route('admin.dashboard'))->assertOk()
        ->assertSee('tabela-cartoes', false)->assertSee('aviso-rolagem', false)->assertSee('data-label', false);
});
