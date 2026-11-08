<?php

namespace App\Http\Requests;

use App\Models\Evento;
use App\Models\Trabalho;

/**
 * Cadastro ou edição de um trabalho PELA GESTÃO (em nome dos autores).
 *
 * Mesmas regras de completude do formulário de submissão (categoria, modalidade, autores...), mas sem as
 * declarações do autor, sem exigir o PDF e sem as janelas/limites da chamada.
 */
class GestaoSalvarTrabalhoRequest extends SalvarTrabalhoRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gerirTrabalhos', $this->evento()) ?? false;
    }

    public function evento(): Evento
    {
        $trabalho = $this->route('trabalho');
        if ($trabalho instanceof Trabalho) {
            return $trabalho->evento;
        }

        $evento = $this->route('evento');

        return $evento instanceof Evento ? $evento : Evento::findOrFail($evento);
    }

    /** Valida como um envio completo (o trabalho não é rascunho). */
    public function submeter(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $regras = parent::rules();

        $regras['acao'] = ['nullable'];
        $regras['declaracao_ineditismo'] = ['nullable'];
        $regras['declaracao_regulamento'] = ['nullable'];
        $regras['arquivo'][0] = 'nullable';                       // o PDF pode ser enviado depois
        $regras['responsavel_email'] = ['nullable', 'email', 'max:255'];

        return $regras;
    }

    public function messages(): array
    {
        return parent::messages() + ['responsavel_email.email' => 'E-mail do responsável inválido.'];
    }
}
