<?php

namespace App\Services;

use App\Models\Participante;
use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Validation\Rule;

/**
 * Garante que inscrição, frequência e credenciamento usem sempre o MESMO participante da conta logada
 * (mesmo CPF, nome e e-mail), evitando cadastros duplicados ou divergentes.
 *
 * O tipo de cadastro (aluno, servidor, externo) é escolhido no cadastro da conta e só muda em "Minha Conta".
 * Cadastros antigos que constam como externo sem confirmação (ou alunos/servidores incompletos) são "pendentes":
 * a pessoa confirma ou corrige UMA vez, na próxima frequência/inscrição.
 */
class ParticipanteDaConta
{
    public function daConta(User $user): ?Participante
    {
        return Participante::where('user_id', $user->id)->first();
    }

    /** Falta a pessoa confirmar (externo ainda não confirmado) ou completar (aluno sem turma/matrícula, servidor sem matrícula) o tipo de cadastro. */
    public function pendente(?Participante $p): bool
    {
        if (!$p) {
            return false;
        }

        return match ($p->tipo_vinculo) {
            'externo' => $p->vinculo_confirmado_em === null,
            'aluno' => blank($p->matricula) || !$p->turma_id,
            'servidor' => blank($p->matricula),
            default => true,
        };
    }

    /** O pendente é um externo ainda sem confirmação (a pergunta é "você é mesmo externo?"); senão é cadastro incompleto. */
    public function pendenteEhExternoNaoConfirmado(?Participante $p): bool
    {
        return $p && $p->tipo_vinculo === 'externo' && $p->vinculo_confirmado_em === null;
    }

    /**
     * O que a pessoa decidiu no formulário quando há pendência: 'externo' (confirma) ou 'corrigir' (informa os dados).
     * Sem o campo (clientes antigos), vale o tipo enviado: externo confirma; qualquer outro corrige.
     */
    public function confirmacao(?Participante $p, array $entrada): ?string
    {
        if (!$this->pendente($p)) {
            return null;
        }
        if (!$this->pendenteEhExternoNaoConfirmado($p)) {
            return 'corrigir';   // aluno/servidor incompleto: precisa completar os dados
        }
        $c = $entrada['confirmacao_vinculo'] ?? null;
        if (in_array($c, ['externo', 'corrigir'], true)) {
            return $c;
        }

        return ($entrada['tipo_vinculo'] ?? 'externo') === 'externo' ? 'externo' : 'corrigir';
    }

    /**
     * Regras dos campos preenchidos no formulário. CPF e nome só são pedidos quando a conta ainda não tem cadastro;
     * tipo, matrícula e turma só são pedidos quando serão gravados (conta nova ou pendência a corrigir).
     *
     * @param  array<string,mixed>  $entrada  dados enviados (para decidir entre confirmar e corrigir)
     */
    public function regras(bool $contaTemCadastro, ?string $tipoVinculo, ?Participante $participante = null, array $entrada = []): array
    {
        $aplica = !$contaTemCadastro || $this->confirmacao($participante, $entrada) === 'corrigir';
        // Sem tipo no envio (cadastro incompleto): vale o tipo que a pessoa já tem
        $tipo = $tipoVinculo ?? ($entrada['tipo_vinculo'] ?? ($contaTemCadastro ? $participante?->tipo_vinculo : null));

        return [
            'nome_completo' => [$contaTemCadastro ? 'nullable' : 'required', 'string', 'max:255'],
            'cpf' => [$contaTemCadastro ? 'nullable' : 'required', 'string', 'size:14', new Cpf],
            'confirmacao_vinculo' => ['nullable', Rule::in(['externo', 'corrigir'])],
            'tipo_vinculo' => [!$contaTemCadastro ? 'required' : 'nullable', Rule::in(['aluno', 'servidor', 'externo'])],
            'matricula' => [
                Rule::requiredIf(fn () => $aplica && in_array($tipo, ['aluno', 'servidor'])),
                'nullable', 'string', 'max:50',
            ],
            'turma_id' => [Rule::requiredIf(fn () => $aplica && $tipo === 'aluno'), 'nullable', 'exists:turmas,id'],
        ];
    }

    /**
     * Devolve (criando ou ligando, se preciso) o participante da conta.
     * - Conta sem cadastro: grava tipo, matrícula e turma informados (a pessoa escolheu: fica confirmado).
     * - Cadastro com pendência: confirma como externo ou grava os dados corrigidos (uma vez).
     * - Cadastro em dia: tipo, matrícula e turma NÃO mudam por aqui (só em "Minha Conta").
     * CPF e nome de um cadastro existente nunca são sobrescritos; o e-mail é sempre o da conta.
     *
     * @param  array{cpf?:?string,nome_completo?:?string,tipo_vinculo?:?string,matricula?:?string,turma_id?:mixed,confirmacao_vinculo?:?string}  $dados
     * @throws \DomainException CPF pertence a outra conta
     */
    public function resolver(User $user, array $dados): Participante
    {
        $participante = $this->daConta($user);
        $novo = false;

        if (!$participante) {
            // Conta ainda sem cadastro de participante: CPF e nome são indispensáveis (antes faltar o CPF gerava erro 500)
            if (blank($dados['cpf'] ?? null) || blank($dados['nome_completo'] ?? null)) {
                throw new \DomainException('Informe seu CPF e nome completo para concluir o cadastro.');
            }

            $existente = Participante::where('cpf', $dados['cpf'])->first();
            if ($existente && $existente->user_id && $existente->user_id !== $user->id) {
                throw new \DomainException('Este CPF já está vinculado a outra conta. Entre com a conta correta ou procure a organização do evento.');
            }
            $participante = $existente ?? new Participante(['cpf' => $dados['cpf']]);
            $participante->nome_completo = $existente?->nome_completo ?: $dados['nome_completo'];
            $novo = true;
        }

        $participante->fill(['user_id' => $user->id, 'email' => $user->email]);

        $decisao = $novo ? 'corrigir' : $this->confirmacao($participante, $dados);
        if ($decisao === 'externo') {
            $this->aplicarVinculo($participante, 'externo', null, null);
        } elseif ($decisao === 'corrigir') {
            $this->aplicarVinculo($participante, $dados['tipo_vinculo'] ?? $participante->tipo_vinculo ?? 'externo', $dados['matricula'] ?? null, $dados['turma_id'] ?? null);
        }

        $participante->save();

        return $participante;
    }

    /** Grava o tipo de cadastro e limpa o que não se aplica a ele; marca como confirmado pela própria pessoa. */
    public function aplicarVinculo(Participante $p, string $tipo, ?string $matricula, $turmaId): void
    {
        $p->fill([
            'tipo_vinculo' => $tipo,
            'matricula' => in_array($tipo, ['aluno', 'servidor']) ? $matricula : null,
            'turma_id' => $tipo === 'aluno' ? $turmaId : null,
            'vinculo_confirmado_em' => now(),
        ]);
    }
}
