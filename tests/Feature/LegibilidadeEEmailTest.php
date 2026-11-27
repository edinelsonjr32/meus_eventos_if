<?php

use App\Support\CorDaMarca;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__ . '/../Helpers/TrabalhosHelpers.php';

uses(RefreshDatabase::class);

it('escurece cores claras demais e mantém as legíveis', function () {
    expect(CorDaMarca::legivel('#059669'))->toBe('#059669')
        ->and(CorDaMarca::legivel('#064E3B'))->toBe('#064e3b')
        ->and(CorDaMarca::legivel(null))->toBe(CorDaMarca::PADRAO)
        ->and(CorDaMarca::legivel('red;}'))->toBe(CorDaMarca::PADRAO);

    foreach (['#f2e4fa', '#ffffff', '#fff', '#ffff00', '#10b981'] as $clara) {
        expect(CorDaMarca::contrasteComBranco(CorDaMarca::legivel($clara)))->toBeGreaterThanOrEqual(CorDaMarca::CONTRASTE_MINIMO);
    }
});

it('usa a cor legível na página pública do evento', function () {
    $evento = mkEvento(mkUser('admin', 'chefe@ifpa.edu.br'));
    $evento->update(['configuracoes' => ['cor_fundo' => '#f2e4fa']]);

    $this->get(route('evento.publico.show', $evento->slug))->assertOk()
        ->assertSee('--brand: ' . CorDaMarca::legivel('#f2e4fa') . ';', false)
        ->assertDontSee('--brand: #f2e4fa', false);
});

it('recusa e-mail com acento no cadastro de conta', function () {
    $this->post('/register', ['name' => 'Teste', 'email' => 'joão@gmail.com', 'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123'])
        ->assertSessionHasErrors(['email' => 'O e-mail não pode ter acentos, ç ou espaços. Confira como ele foi digitado.']);
    $this->assertGuest();
});

it('recusa e-mail com acento no perfil e na criação de usuário pelo administrador', function () {
    $admin = mkUser('admin', 'chefe@ifpa.edu.br');

    $this->actingAs($admin)->patch(route('profile.update'), ['name' => 'Chefe', 'email' => 'conceição@gmail.com'])->assertSessionHasErrors('email');
    $this->actingAs($admin)->post(route('admin.users.store'), ['name' => 'X', 'email' => 'andré@gmail.com', 'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123', 'role' => 'user'])
        ->assertSessionHasErrors('email');
});

it('mostra o acesso do usuário sem controles que não gravam', function () {
    $this->actingAs(mkUser('admin', 'chefe@ifpa.edu.br'))->get(route('admin.users.index'))->assertOk()
        ->assertSee('Administrador')
        ->assertDontSee('selectedUsers', false)
        ->assertDontSee('toggle de Admin', false);
});
