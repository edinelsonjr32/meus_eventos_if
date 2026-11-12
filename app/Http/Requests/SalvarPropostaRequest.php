<?php

namespace App\Http\Requests;

use App\Models\ChamadaProposta;
use App\Models\Proposta;
use App\Rules\Cpf;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use App\Support\HtmlSeguro;
use Illuminate\Validation\Rule;

/** Validação do formulário de proposta (envio e edição) feito pelo proponente. */
class SalvarPropostaRequest extends FormRequest
{
    /** Campos de texto formatado (editor): o tamanho conta o texto visível, não as tags. */
    public const RICOS = ['resumo', 'objetivos', 'conteudo_programatico', 'metodologia', 'equipamentos_solicitados', 'observacoes'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function chamada(): ChamadaProposta
    {
        $proposta = $this->route('proposta');

        return $proposta instanceof Proposta ? $proposta->chamada : $this->route('chamada');
    }

    protected function prepareForValidation(): void
    {
        $apoios = collect($this->input('apoios', []))
            ->filter(fn ($a) => is_array($a) && collect($a)->only(['nome', 'email', 'cpf'])->filter(fn ($v) => filled($v))->isNotEmpty())
            ->map(function ($a) {
                $cpf = Cpf::formatar((string) ($a['cpf'] ?? ''));

                return [
                    'nome' => trim((string) ($a['nome'] ?? '')),
                    'email' => filled($a['email'] ?? null) ? mb_strtolower(trim($a['email'])) : null,
                    'cpf' => filled($cpf) ? $cpf : null,
                ];
            })->values()->all();

        // Campos com formatação (editor de texto): HTML limpo no servidor, sem imagens nem scripts
        $ricos = [];
        foreach (self::RICOS as $campo) {
            $ricos[$campo] = HtmlSeguro::limparSimples($this->input($campo));
        }

        $lab = $this->boolean('requer_laboratorio');

        $this->merge($ricos + [
            'apoios' => $apoios,
            'requer_laboratorio' => $lab,
            // sem laboratório, o campo de espaço não faz sentido
            'espaco_solicitado' => $lab ? $this->input('espaco_solicitado') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(array_keys($this->chamada()->tiposAceitos()))],
            'titulo' => ['required', 'string', 'min:5', 'max:200'],
            'resumo' => ['required', 'string', 'max:30000', $this->texto(80, 3000, 'O resumo')],
            'objetivos' => ['nullable', 'string', 'max:30000', $this->texto(0, 3000, 'Os objetivos')],
            'publico_alvo' => ['nullable', 'string', 'max:255'],
            'conteudo_programatico' => ['nullable', 'string', 'max:50000', $this->texto(0, 5000, 'O conteúdo programático')],
            'metodologia' => ['nullable', 'string', 'max:30000', $this->texto(0, 3000, 'A metodologia')],
            'carga_horaria_prevista' => ['required', 'integer', 'min:1', 'max:40'],
            'vagas_sugeridas' => ['required', 'integer', 'min:1', 'max:500'],
            'requer_laboratorio' => ['boolean'],
            'espaco_solicitado' => ['nullable', 'required_if:requer_laboratorio,true', 'string', 'max:255'],
            'equipamentos_solicitados' => ['nullable', 'string', 'max:20000', $this->texto(0, 2000, 'Os equipamentos')],
            'observacoes' => ['nullable', 'string', 'max:20000', $this->texto(0, 2000, 'As observações')],
            'apoios' => ['nullable', 'array', 'max:5'],
            'apoios.*.nome' => ['required', 'string', 'min:3', 'max:255'],
            'apoios.*.email' => ['required', 'email:rfc', 'max:255'],
            'apoios.*.cpf' => ['required', 'string', 'size:14', new Cpf],
        ];
    }

    /** Regra de tamanho sobre o texto visível (sem tags HTML). */
    private function texto(int $min, int $max, string $rotulo): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $falha) use ($min, $max, $rotulo) {
            $n = HtmlSeguro::tamanho((string) $valor);
            if ($n === 0 && $min === 0) {
                return;
            }
            if ($n < $min) {
                $falha("{$rotulo} deve ter pelo menos {$min} caracteres" . ($min >= 80 ? ': descreva o que será ensinado.' : '.'));
            } elseif ($n > $max) {
                $falha("{$rotulo} pode ter no máximo {$max} caracteres (atual: {$n}).");
            }
        };
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Selecione o tipo da atividade.',
            'tipo.in' => 'Tipo de atividade não aceito nesta chamada.',
            'titulo.required' => 'Informe o título da proposta.',
            'titulo.min' => 'O título deve ter pelo menos 5 caracteres.',
            'resumo.required' => 'Escreva o resumo da proposta.',
            'carga_horaria_prevista.required' => 'Informe a carga horária prevista.',
            'carga_horaria_prevista.integer' => 'A carga horária deve ser um número inteiro de horas.',
            'carga_horaria_prevista.min' => 'A carga horária mínima é de 1 hora.',
            'carga_horaria_prevista.max' => 'A carga horária máxima é de 40 horas.',
            'vagas_sugeridas.required' => 'Informe o número de vagas sugerido.',
            'vagas_sugeridas.integer' => 'As vagas devem ser um número inteiro.',
            'vagas_sugeridas.min' => 'Informe ao menos 1 vaga.',
            'vagas_sugeridas.max' => 'O máximo sugerido é de 500 vagas.',
            'espaco_solicitado.required_if' => 'Informe qual laboratório ou espaço específico você precisa.',
            'apoios.max' => 'Informe no máximo 5 pessoas de apoio.',
            'apoios.*.nome.required' => 'Informe o nome de cada pessoa de apoio.',
            'apoios.*.nome.min' => 'O nome da pessoa de apoio deve ter pelo menos 3 caracteres.',
            'apoios.*.email.required' => 'Informe o e-mail de cada pessoa de apoio.',
            'apoios.*.email.email' => 'E-mail de apoio inválido.',
            'apoios.*.cpf.required' => 'Informe o CPF de cada pessoa de apoio (necessário para adicioná-la ao evento).',
            'apoios.*.cpf.size' => 'CPF de apoio inválido (use 000.000.000-00).',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $apoios = collect($this->input('apoios', []));
            if ($apoios->pluck('cpf')->unique()->count() !== $apoios->count()) {
                $validator->errors()->add('apoios', 'Há pessoas de apoio com CPF repetido.');
            }
            if ($apoios->pluck('email')->unique()->count() !== $apoios->count()) {
                $validator->errors()->add('apoios', 'Há pessoas de apoio com e-mail repetido.');
            }

            // O próprio proponente não é "apoio" da sua atividade
            $user = $this->user();
            $participante = \App\Models\Participante::where('user_id', $user->id)->first();
            if ($apoios->contains(fn ($a) => ($participante && $a['cpf'] === $participante->cpf) || mb_strtolower($user->email) === $a['email'])) {
                $validator->errors()->add('apoios', 'Você não pode se indicar como pessoa de apoio da própria atividade.');
            }

            // Mesma proposta enviada duas vezes (mesmo título, mesma chamada, mesma pessoa)
            $atual = $this->route('proposta');
            $duplicada = Proposta::where('chamada_id', $this->chamada()->id)->where('user_id', $user->id)
                ->whereNotIn('status', ['retirada', 'reprovada'])
                ->when($atual instanceof Proposta, fn ($q) => $q->where('id', '!=', $atual->id))
                ->whereRaw('LOWER(titulo) = ?', [mb_strtolower(trim((string) $this->input('titulo')))])->exists();
            if ($duplicada) {
                $validator->errors()->add('titulo', 'Você já enviou uma proposta com este título nesta chamada.');
            }
        }];
    }
}
