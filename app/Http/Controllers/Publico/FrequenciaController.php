<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Atividade;
use App\Models\Participante;
use App\Models\Frequencia;
use App\Models\Inscricao;
use App\Models\Turma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FrequenciaController extends Controller
{
    /**
     * Exibe o formulário de registro.
     */
    public function index($token)
    {
        $atividade = Atividade::where('token_frequencia', $token)->firstOrFail();

        // Busca todas as turmas carregando o curso junto (Eager Loading)
        // Ordena para facilitar a busca do aluno (Ex: ADS aparece antes de Téc...)
        $turmas = Turma::with('curso')->get()->sortBy(function ($turma) {
            return $turma->curso->nome . $turma->ano;
        });

        return view('publico.registrar-frequencia', compact('atividade', 'turmas'));
    }

    /**
     * Processa o registro de presença.
     */
    public function store(Request $request, $slug)
    {
        // Busca o evento pelo slug
        $evento = Evento::where('slug', $slug)->firstOrFail();

        // 1. Definição de Regras de Validação
        $rules = [
            'nome_completo' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'size:14'], // Assumindo formato 000.000.000-00 (14 caracteres)
            'tipo_vinculo' => ['required', Rule::in('aluno', 'servidor', 'externo')],

            // Matrícula é obrigatória se for aluno ou servidor
            'matricula' => [
                Rule::requiredIf(fn() => in_array($request->tipo_vinculo, ['aluno', 'servidor'])),
                'nullable',
                'string',
                'max:50'
            ],

            // Turma_id é obrigatória se for aluno
            'turma_id' => ['required_if:tipo_vinculo,aluno', 'nullable', 'exists:turmas,id'],

            'email' => ['required', 'email'] // Email é sempre required para o Participante

            // Regras de 'password' e validação 'unique:users' REMOVIDAS.
        ];

        $request->validate($rules);

        // =================================================================
        // VALIDAÇÃO DE DUPLICIDADE DE INSCRIÇÃO (CPF no Evento)
        // =================================================================
        $jaInscrito = Inscricao::where('evento_id', $evento->id)
            ->whereHas('participante', function ($q) use ($request) {
                // Verifica se já existe uma inscrição para este CPF neste evento
                $q->where('cpf', $request->cpf);
            })
            ->exists();

        if ($jaInscrito) {
            return back()
                ->with('error', 'Este CPF já possui uma inscrição confirmada neste evento.')
                ->withInput();
        }
        // =================================================================

        try {
            DB::beginTransaction();

            // Pega o ID do usuário logado (será NULL se ninguém estiver logado)
            $userId = Auth::id();

            // 2. Cria ou Atualiza o Participante (Chave de busca: CPF)
            $dadosParticipante = [
                'nome_completo' => $request->nome_completo,
                'email' => $request->email,
                'tipo_vinculo' => $request->tipo_vinculo,
                'matricula' => in_array($request->tipo_vinculo, ['aluno', 'servidor']) ? $request->matricula : null,
                'turma_id' => $request->tipo_vinculo === 'aluno' ? $request->turma_id : null,

                // Vincula o user_id APENAS se o usuário estiver logado.
                'user_id' => $userId,
            ];

            // Atualiza o Participante se o CPF já existir, ou cria um novo
            $participante = Participante::updateOrCreate(
                ['cpf' => $request->cpf],
                $dadosParticipante
            );

            // 3. Cria a Inscrição (Vínculo entre Evento e Participante)
            Inscricao::create([
                'evento_id' => $evento->id,
                'participante_id' => $participante->id,
                'data_inscricao' => now()
            ]);

            DB::commit();

            return redirect()->route('evento.publico.sucesso', $slug)
                ->with('participante', $participante->nome_completo);
        } catch (\Exception $e) {
            DB::rollBack();
            // Em um ambiente de produção, substitua $e->getMessage() por uma mensagem genérica
            return back()->with('error', 'Erro ao processar inscrição: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Fórmula de Haversine para calcular distância em metros entre dois pontos GPS.
     */
    private function calcularDistancia($lat1, $lon1, $lat2, $lon2)
    {
        if (($lat1 == $lat2) && ($lon1 == $lon2)) {
            return 0;
        }

        $lat1 = floatval($lat1);
        $lon1 = floatval($lon1);
        $lat2 = floatval($lat2);
        $lon2 = floatval($lon2);

        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;

        // Converte milhas para Metros
        return round($miles * 1.609344 * 1000);
    }
}
