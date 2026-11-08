<?php

namespace App\Http\Requests;

use App\Models\ApresentacaoAvaliacao;
use Illuminate\Foundation\Http\FormRequest;

/** Valida o formulário de avaliação da apresentação (rascunho ou conclusão, pelo botão usado). */
class SalvarApresentacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var ApresentacaoAvaliacao $avaliacao */
        $avaliacao = $this->route('avaliacao');
        $rules = [
            'parecer' => ['nullable', 'string', 'max:5000'],
            'parecer_confidencial' => ['nullable', 'string', 'max:5000'],
            'comentarios' => ['nullable', 'array'],
            'comentarios.*' => ['nullable', 'string', 'max:2000'],
            'notas' => ['nullable', 'array'],
        ];
        foreach ($avaliacao->trabalho->evento->criteriosApresentacao()->where('ativo', true)->get() as $c) {
            $rules["notas.{$c->id}"] = ['nullable', 'numeric', 'min:0', 'max:' . $c->nota_max];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'notas.*.numeric' => 'As notas devem ser numéricas.',
            'notas.*.min' => 'A nota não pode ser negativa.',
            'notas.*.max' => 'A nota excede o máximo do critério.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['notas' => collect($this->input('notas', []))->map(fn ($n) => is_string($n) ? str_replace(',', '.', trim($n)) : $n)->all()]);
    }
}
