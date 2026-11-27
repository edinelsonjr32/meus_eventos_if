<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__ . '/../Helpers/TrabalhosHelpers.php';

uses(RefreshDatabase::class);

/** IDs das linhas que o servidor deixa visíveis para a busca enviada. */
function usuariosVisiveis(string $html): array
{
    preg_match_all('/<tr data-usuario="(\d+)"[^>]*?(style="display: none")?\s*>/', $html, $m, PREG_SET_ORDER);

    return array_map(fn ($l) => (int) $l[1], array_values(array_filter($m, fn ($l) => empty($l[2]))));
}

beforeEach(function () {
    $this->admin = mkUser('admin', 'chefe@ifpa.edu.br');
    $this->admin->update(['name' => 'Administrador']);
    $this->joao = mkUser('user', 'jp.silva@gmail.com');
    $this->joao->update(['name' => 'JOÃO PEDRO DA SILVA']);
    $this->maria = mkUser('user', 'maria@gmail.com');
    $this->maria->update(['name' => 'Maria Souza']);
});

it('filtra por nome ignorando acentos, maiúsculas e a ordem das palavras', function () {
    $html = $this->actingAs($this->admin)->get(route('admin.users.index', ['search' => 'silva joao']))->assertOk()->getContent();

    expect(usuariosVisiveis($html))->toBe([$this->joao->id]);
});

it('filtra por e-mail e mostra tudo sem termo', function () {
    $porEmail = $this->actingAs($this->admin)->get(route('admin.users.index', ['search' => 'maria@']))->getContent();
    $tudo = $this->actingAs($this->admin)->get(route('admin.users.index'))->getContent();

    expect(usuariosVisiveis($porEmail))->toBe([$this->maria->id])
        ->and(usuariosVisiveis($tudo))->toHaveCount(3);
});

it('avisa quando nada é encontrado e não quebra com apóstrofo ou parâmetro em lista', function () {
    $html = $this->actingAs($this->admin)->get(route('admin.users.index', ['search' => "D'Avila"]))->assertOk()->getContent();

    expect(usuariosVisiveis($html))->toBe([])
        ->and($html)->toContain('Nenhum usuário encontrado')
        ->and($html)->not->toContain("search: 'D&#039;Avila'");
    $this->actingAs($this->admin)->get('/admin/users?search[]=x')->assertOk();
});
