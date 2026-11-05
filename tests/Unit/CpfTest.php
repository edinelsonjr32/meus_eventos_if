<?php

use App\Rules\Cpf;

it('valida CPFs corretos com e sem máscara', function () {
    expect(Cpf::valido('111.444.777-35'))->toBeTrue()
        ->and(Cpf::valido('11144477735'))->toBeTrue();
});

it('rejeita CPFs inválidos', function () {
    foreach (['000.000.000-00', '111.111.111-11', '111.444.777-36', '123', '', 'abc', '1114447773'] as $cpf) {
        expect(Cpf::valido($cpf))->toBeFalse("deveria rejeitar: {$cpf}");
    }
});

it('formata 11 dígitos para a máscara padrão e preserva o que não é CPF', function () {
    expect(Cpf::formatar('11144477735'))->toBe('111.444.777-35')
        ->and(Cpf::formatar('111.444.777-35'))->toBe('111.444.777-35')
        ->and(Cpf::formatar('123'))->toBe('123');
});
