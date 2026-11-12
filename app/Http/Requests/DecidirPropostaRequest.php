<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Decisão da organização sobre uma proposta (inclui os dados de programação quando aprovada). */
class DecidirPropostaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('gerenciar', $this->route('evento'));
    }

    public function aprovando(): bool
    {
        return $this->input('decisao') === 'aprovada';
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['ignorar_conflito' => $this->boolean('ignorar_conflito')]);
    }

    public function rules(): array
    {
        $a = $this->aprovando();

        return [
            'decisao' => ['required', Rule::in(['em_analise', 'ajustes', 'reprovada', 'aprovada'])],
            'parecer' => [Rule::requiredIf(in_array($this->input('decisao'), ['ajustes', 'reprovada'], true)), 'nullable', 'string', 'min:5', 'max:3000'],
            'data_inicio' => [$a ? 'required' : 'nullable', 'date'],
            'data_fim' => [$a ? 'required' : 'nullable', 'date', 'after:data_inicio'],
            'local_id' => [$a ? 'required' : 'nullable', 'integer', Rule::exists('locais', 'id')->where('evento_id', $this->route('evento')->id)->where('ativo', true)],
            'equipamentos' => ['nullable', 'string', 'max:2000'],
            'observacoes_organizacao' => ['nullable', 'string', 'max:2000'],
            'vagas' => [$a ? 'required' : 'nullable', 'integer', 'min:1', 'max:2000'],
            'carga_horaria' => [$a ? 'required' : 'nullable', 'integer', 'min:1', 'max:200'],
            'ignorar_conflito' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'decisao.required' => 'Escolha a decisão.',
            'parecer.required' => 'Escreva a mensagem para o proponente (obrigatória para ajustes ou reprovação).',
            'parecer.min' => 'A mensagem deve ter pelo menos 5 caracteres.',
            'data_inicio.required' => 'Informe a data e hora de início.',
            'data_fim.required' => 'Informe a data e hora de término.',
            'data_fim.after' => 'O término deve ser depois do início.',
            'local_id.required' => 'Escolha o local da atividade.',
            'local_id.exists' => 'Escolha um local ativo cadastrado neste evento.',
            'vagas.required' => 'Informe a capacidade máxima.',
            'vagas.min' => 'A capacidade mínima é de 1 vaga.',
            'carga_horaria.required' => 'Informe a carga horária do certificado.',
            'carga_horaria.min' => 'A carga horária mínima é de 1 hora.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || !$this->aprovando()) {
                return;
            }

            $inicio = Carbon::parse($this->input('data_inicio'));
            $fim = Carbon::parse($this->input('data_fim'));
            $evento = $this->route('evento');

            // A atividade precisa cair dentro dos dias do evento
            $antes = $evento->data_inicio && $inicio->copy()->startOfDay()->lt($evento->data_inicio->copy()->startOfDay());
            $depois = $evento->data_fim && $fim->copy()->startOfDay()->gt($evento->data_fim->copy()->startOfDay());
            if ($antes || $depois) {
                $validator->errors()->add('data_inicio', 'A atividade deve acontecer dentro do período do evento (' . $evento->data_inicio->format('d/m/Y') . ' a ' . $evento->data_fim->format('d/m/Y') . ').');
            }

            // A carga horária do certificado não pode passar do tempo da atividade
            $horas = (int) ceil($inicio->diffInMinutes($fim) / 60);
            if ((int) $this->input('carga_horaria') > $horas) {
                $validator->errors()->add('carga_horaria', "A carga horária ({$this->input('carga_horaria')} h) é maior que a duração da atividade ({$horas} h).");
            }
        }];
    }
}
