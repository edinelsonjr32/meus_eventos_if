<?php

namespace App\Http\Requests;

use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use App\Rules\Cpf;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida rascunho (regras mínimas) ou submissão (regras completas), conforme o campo "acao".
 */
class SalvarTrabalhoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function evento(): Evento
    {
        $trabalho = $this->route('trabalho');

        return $trabalho instanceof Trabalho
            ? $trabalho->evento
            : Evento::where('slug', $this->route('slug'))->firstOrFail();
    }

    public function config(): TrabalhoConfig
    {
        return $this->evento()->trabalhoConfig ?? new TrabalhoConfig(['max_autores' => 8, 'max_arquivo_mb' => 20]);
    }

    public function submeter(): bool
    {
        return $this->input('acao') === 'submeter';
    }

    protected function prepareForValidation(): void
    {
        // Remove linhas de autor totalmente vazias e padroniza o CPF de cada autor
        $autores = collect($this->input('autores', []))
            ->filter(fn ($a) => is_array($a) && collect($a)->only(['nome_completo', 'cpf', 'email'])->filter(fn ($v) => filled($v))->isNotEmpty())
            ->map(function ($a) {
                $a['cpf'] = Cpf::formatar($a['cpf'] ?? '');
                $a['email'] = mb_strtolower(trim($a['email'] ?? ''));
                $a['apresentador'] = filter_var($a['apresentador'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $a['correspondente'] = filter_var($a['correspondente'] ?? false, FILTER_VALIDATE_BOOLEAN);

                return $a;
            })
            ->values()
            ->all();

        $this->merge(['autores' => $autores]);
    }

    public static function palavras(?string $texto): array
    {
        return collect(preg_split('/[,;\n]+/', (string) $texto))
            ->map(fn ($p) => trim($p))->filter()->unique()->values()->all();
    }

    public function rules(): array
    {
        $config = $this->config();
        $evento = $this->evento();
        $submeter = $this->submeter();
        $trabalho = $this->route('trabalho');
        $jaTemArquivo = $trabalho instanceof Trabalho && $trabalho->arquivoAtual()->exists();

        $obrigatorio = $submeter ? 'required' : 'nullable';

        return [
            'acao' => ['required', Rule::in(['rascunho', 'submeter'])],
            'titulo' => ['required', 'string', 'max:255'],
            'resumo' => [$obrigatorio, 'string', 'max:6000'],
            'abstract' => ['nullable', 'string', 'max:6000'],
            'palavras_chave' => [$obrigatorio, 'string', 'max:255'],
            'tipo_trabalho' => [$obrigatorio, Rule::in(array_keys($config->tiposHabilitados()))],
            'area_tematica_id' => ['nullable', Rule::exists('areas_tematicas', 'id')->where('evento_id', $evento->id)],
            'modalidade_apresentacao' => [$obrigatorio, Rule::in(array_keys(Trabalho::MODALIDADES))],
            'agencia_fomento' => ['nullable', 'string', 'max:255'],
            'projeto_pesquisa' => ['nullable', 'string', 'max:255'],
            'comite_etica' => ['nullable', 'string', 'max:255'],
            'autoriza_publicacao' => ['nullable', 'boolean'],

            'declaracao_ineditismo' => [$submeter ? 'accepted' : 'nullable'],
            'declaracao_regulamento' => [$submeter ? 'accepted' : 'nullable'],

            'autores' => [$submeter ? 'required' : 'nullable', 'array', 'min:' . ($submeter ? 1 : 0), 'max:' . ($config->max_autores + 4)],
            'autores.*.nome_completo' => ['required', 'string', 'max:255'],
            'autores.*.cpf' => ['required', 'string', 'size:14', new Cpf],
            'autores.*.email' => ['required', 'email', 'max:255'],
            'autores.*.telefone' => ['nullable', 'string', 'max:20'],
            'autores.*.instituicao' => ['nullable', 'string', 'max:255'],
            'autores.*.vinculo' => ['nullable', Rule::in(['aluno', 'servidor', 'externo'])],
            'autores.*.titulacao' => ['nullable', 'string', 'max:255'],
            'autores.*.lattes' => ['nullable', 'url', 'max:255'],
            'autores.*.orcid' => ['nullable', 'regex:/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/'],
            'autores.*.tipo_autor' => ['required', Rule::in(array_keys(Trabalho::TIPOS_AUTOR))],
            'autores.*.apresentador' => ['boolean'],
            'autores.*.correspondente' => ['boolean'],

            'arquivo' => [
                $submeter && !$jaTemArquivo ? 'required' : 'nullable',
                'file', 'mimes:pdf', 'max:' . ($config->max_arquivo_mb * 1024),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'autores.*.nome_completo.required' => 'Informe o nome de todos os autores.',
            'autores.*.cpf.required' => 'Informe o CPF de todos os autores.',
            'autores.*.cpf.size' => 'CPF de autor inválido.',
            'autores.*.email.required' => 'Informe o e-mail de todos os autores.',
            'autores.*.email.email' => 'E-mail de autor inválido.',
            'autores.*.tipo_autor.required' => 'Informe o tipo de todos os autores.',
            'autores.*.lattes.url' => 'O Lattes deve ser uma URL completa (https://...).',
            'autores.*.orcid.regex' => 'ORCID inválido (formato 0000-0000-0000-0000).',
            'declaracao_ineditismo.accepted' => 'É necessário declarar o ineditismo do trabalho.',
            'declaracao_regulamento.accepted' => 'É necessário aceitar o regulamento.',
            'arquivo.required' => 'Envie o arquivo PDF do trabalho.',
            'arquivo.mimes' => 'O arquivo deve estar em formato PDF.',
            'arquivo.max' => 'O arquivo excede o tamanho máximo permitido.',
        ];
    }

    /** Regras que dependem do conjunto (autores, resumo, PDF real). */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $config = $this->config();
            $autores = collect($this->input('autores', []));

            // CPF duplicado entre autores (vale para rascunho também)
            $cpfs = $autores->pluck('cpf');
            if ($cpfs->count() !== $cpfs->unique()->count()) {
                $validator->errors()->add('autores', 'Há autores com CPF repetido.');
            }

            // Arquivo precisa ser PDF de verdade (cabeçalho %PDF-)
            if ($arquivo = $this->file('arquivo')) {
                $cabecalho = @file_get_contents($arquivo->getRealPath(), false, null, 0, 5);
                if ($cabecalho !== '%PDF-') {
                    $validator->errors()->add('arquivo', 'O arquivo enviado não é um PDF válido.');
                }
            }

            if (!$this->submeter()) {
                return;
            }

            $principais = $autores->where('tipo_autor', 'autor_principal')->count();
            if ($principais !== 1) {
                $validator->errors()->add('autores', 'Informe exatamente um autor principal.');
            }

            if ($autores->where('apresentador', true)->isEmpty()) {
                $validator->errors()->add('autores', 'Marque ao menos um autor como apresentador.');
            }

            if ($autores->where('correspondente', true)->count() > 1) {
                $validator->errors()->add('autores', 'Apenas um autor pode ser o correspondente.');
            }

            $contaveis = $autores->whereNotIn('tipo_autor', ['orientador', 'coorientador'])->count();
            if ($contaveis > $config->max_autores) {
                $validator->errors()->add('autores', "O limite é de {$config->max_autores} autores (orientadores não contam).");
            }

            $palavras = self::palavras($this->input('palavras_chave'));
            if (count($palavras) < 3 || count($palavras) > 5) {
                $validator->errors()->add('palavras_chave', 'Informe de 3 a 5 palavras-chave, separadas por vírgula.');
            }

            $total = str_word_count(strip_tags((string) $this->input('resumo')), 0, 'àáâãçéêíóôõúüÀÁÂÃÇÉÊÍÓÔÕÚÜ');
            if ($total < $config->resumo_min_palavras || $total > $config->resumo_max_palavras) {
                $validator->errors()->add('resumo', "O resumo deve ter entre {$config->resumo_min_palavras} e {$config->resumo_max_palavras} palavras (atual: {$total}).");
            }
        }];
    }
}
