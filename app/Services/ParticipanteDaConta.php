<?php

namespace App\Services;

use App\Models\Participante;
use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Validation\Rule;

/**
 * Garante que inscrição, frequência e credenciamento usem sempre o MESMO participante da conta logada
 * (mesmo CPF, nome e e-mail), evitando cadastros duplicados ou divergentes.
 */
class ParticipanteDaConta
{
    public function daConta(User $user): ?Participante
    {
        return Participante::where('user_id', $user->id)->first();
    }

    /**
     * Regras dos campos preenchidos no formulário. CPF e nome só são pedidos quando a conta ainda
     * não tem cadastro de participante (senão vêm do cadastro e não são editáveis).
     */
    public function regras(bool $contaTemCadastro, ?string $tipoVinculo): array
    {
        return [
            'nome_completo' => [$contaTemCadastro ? 'nullable' : 'required', 'string', 'max:255'],
            'cpf' => [$contaTemCadastro ? 'nullable' : 'required', 'string', 'size:14', new Cpf],
            'tipo_vinculo' => ['required', Rule::in(['aluno', 'servidor', 'externo'])],
            'matricula' => [
                Rule::requiredIf(fn () => in_array($tipoVinculo, ['aluno', 'servidor'])),
                'nullable', 'string', 'max:50',
            ],
            'turma_id' => ['required_if:tipo_vinculo,aluno', 'nullable', 'exists:turmas,id'],
        ];
    }

    /**
     * Devolve (criando ou ligando, se preciso) o participante da conta, atualizando vínculo, matrícula e turma.
     * CPF e nome de um cadastro existente nunca são sobrescritos; o e-mail é sempre o da conta.
     *
     * @param  array{cpf?:?string,nome_completo?:?string,tipo_vinculo:string,matricula?:?string,turma_id?:mixed}  $dados
     * @throws \DomainException CPF pertence a outra conta
     */
    public function resolver(User $user, array $dados): Participante
    {
        $participante = $this->daConta($user);

        if (!$participante) {
            $existente = Participante::where('cpf', $dados['cpf'])->first();
            if ($existente && $existente->user_id && $existente->user_id !== $user->id) {
                throw new \DomainException('Este CPF já está vinculado a outra conta. Entre com a conta correta ou procure a organização do evento.');
            }
            $participante = $existente ?? new Participante(['cpf' => $dados['cpf']]);
            $participante->nome_completo = $existente?->nome_completo ?: $dados['nome_completo'];
        }

        $participante->fill([
            'user_id' => $user->id,
            'email' => $user->email,
            'tipo_vinculo' => $dados['tipo_vinculo'],
            'matricula' => in_array($dados['tipo_vinculo'], ['aluno', 'servidor']) ? ($dados['matricula'] ?? null) : null,
            'turma_id' => $dados['tipo_vinculo'] === 'aluno' ? ($dados['turma_id'] ?? null) : null,
        ])->save();

        return $participante;
    }
}
