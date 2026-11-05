<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida CPF (com ou sem máscara) pelos dígitos verificadores.
 */
class Cpf implements ValidationRule
{
    public static function somenteDigitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    /** Formato padrão salvo no banco: 000.000.000-00 */
    public static function formatar(?string $valor): string
    {
        $d = self::somenteDigitos($valor);

        return strlen($d) === 11
            ? substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2)
            : (string) $valor;
    }

    public static function valido(?string $valor): bool
    {
        $c = self::somenteDigitos($valor);

        if (strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $c[$i] * (($t + 1) - $i);
            }
            if ((int) $c[$t] !== ((($soma * 10) % 11) % 10)) {
                return false;
            }
        }

        return true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!self::valido((string) $value)) {
            $fail('O CPF informado é inválido.');
        }
    }
}
