<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** O servidor de e-mail não entrega para endereços com acento (ex.: "joão@..."), então eles são recusados no cadastro. */
class EmailSemAcento implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!preg_match('/^[\x21-\x7E]+$/', (string) $value)) {
            $fail('O e-mail não pode ter acentos, ç ou espaços. Confira como ele foi digitado.');
        }
    }
}
