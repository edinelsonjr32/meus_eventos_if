<?php

namespace App\Http\Requests;

use App\Models\TrabalhoAvaliacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valida o formulário de avaliação (rascunho ou conclusão, pelo botão usado). */
class SalvarAvaliacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var TrabalhoAvaliacao $avaliacao */
        $avaliacao = $this->route('avaliacao');
        $criterios = $avaliacao->trabalho->evento->criterios()->where('ativo', true)->get();

        $rules = [
            'parecer' => ['nullable', 'string', 'max:10000'],
            'parecer_confidencial' => ['nullable', 'string', 'max:5000'],
            'recomendacao' => ['nullable', Rule::in(array_keys(TrabalhoAvaliacao::RECOMENDACOES))],
            'comentarios' => ['nullable', 'array'],
            'comentarios.*' => ['nullable', 'string', 'max:2000'],
            'notas' => ['nullable', 'array'],
        ];

        foreach ($criterios as $c) {
            $rules["notas.{$c->id}"] = ['nullable', 'numeric', 'min:0', 'max:' . $c->nota_max, 'decimal:0,2'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'notas.*.numeric' => 'As notas devem ser numéricas.',
            'notas.*.min' => 'A nota não pode ser negativa.',
            'notas.*.max' => 'A nota excede o máximo do critério.',
            'notas.*.decimal' => 'Use no máximo 2 casas decimais na nota.',
            'recomendacao.in' => 'Recomendação inválida.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Aceita vírgula decimal (7,5)
        $notas = collect($this->input('notas', []))->map(fn ($n) => is_string($n) ? str_replace(',', '.', trim($n)) : $n)->all();
        $this->merge(['notas' => $notas]);
    }
}
