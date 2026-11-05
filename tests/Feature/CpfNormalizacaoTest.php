<?php

use App\Models\User;

it('registro aceita CPF sem máscara e salva padronizado', function () {
    $this->post('/register', [
        'name' => 'Novo', 'email' => 'novo@x.com', 'password' => 'Senha@12345', 'password_confirmation' => 'Senha@12345',
        'cpf' => '11144477735',
    ])->assertRedirect();

    expect(\App\Models\Participante::where('cpf', '111.444.777-35')->exists())->toBeTrue();
});

it('registro rejeita CPF com dígitos verificadores inválidos', function () {
    $this->post('/register', [
        'name' => 'Novo', 'email' => 'novo@x.com', 'password' => 'Senha@12345', 'password_confirmation' => 'Senha@12345',
        'cpf' => '111.444.777-36',
    ])->assertSessionHasErrors('cpf');

    expect(User::count())->toBe(0);
});
