<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Atividade;
use App\Models\Evento;
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

    public function store(Request $request, $token)
    {
        // 1. Identificação: Busca a Atividade pelo token e o Evento pai
        $atividade = Atividade::where('token_frequencia', $token)->firstOrFail();
        $evento = $atividade->evento;

        if (!$evento) {
            return back()->with('error', 'Evento não encontrado para esta atividade.');
        }

        // 2. Validação dos dados recebidos
        $rules = [
            'nome_completo' => ['required', 'string', 'max:255'],
            'cpf'           => ['required', 'string', 'size:14'],
            'tipo_vinculo'  => ['required', Rule::in(['aluno', 'servidor', 'externo'])],
            'email'         => ['required', 'email'],

            'matricula' => [
                Rule::requiredIf(fn() => in_array($request->tipo_vinculo, ['aluno', 'servidor'])),
                'nullable',
                'string',
                'max:50'
            ],

            'turma_id' => ['required_if:tipo_vinculo,aluno', 'nullable', 'exists:turmas,id'],
        ];

        $request->validate($rules);

        // 3. Verifica colisão de E-mail (Segurança)
        // Impede usar um email que já pertence a OUTRO CPF
        $emailEmUsoPorOutro = Participante::where('email', $request->email)
            ->where('cpf', '!=', $request->cpf)
            ->exists();

        if ($emailEmUsoPorOutro) {
            return back()->with('error', 'Este e-mail já está em uso por outro CPF.');
        }

        try {
            DB::beginTransaction();

            $userId = Auth::id();

            // 4. Cria ou Atualiza o Participante (Dados Pessoais)
            $dadosParticipante = [
                'nome_completo' => $request->nome_completo,
                'email'         => $request->email,
                'tipo_vinculo'  => $request->tipo_vinculo,
                'matricula'     => in_array($request->tipo_vinculo, ['aluno', 'servidor']) ? $request->matricula : null,
                'turma_id'      => $request->tipo_vinculo === 'aluno' ? $request->turma_id : null,
            ];

            if ($userId) {
                $dadosParticipante['user_id'] = $userId;
            }

            $participante = Participante::updateOrCreate(
                ['cpf' => $request->cpf], // Chave de busca
                $dadosParticipante        // Dados a atualizar
            );

            // 5. Garante a Inscrição no Evento (Silencioso)
            // Se já existe, ele apenas retorna o registro. Se não existe, cria.
            // NÃO RETORNA ERRO, APENAS SEGUE O FLUXO.
            Inscricao::firstOrCreate(
                [
                    'evento_id' => $evento->id,
                    'participante_id' => $participante->id
                ],
                [
                    'data_inscricao' => now() // Só é usado se for criar um novo
                ]
            );

            // 6. Registra a Frequência na Atividade
            // firstOrCreate evita duplicar a presença se a pessoa escanear 2 vezes
            Frequencia::firstOrCreate(
                [
                    'atividade_id' => $atividade->id,
                    'participante_id' => $participante->id
                ],
                [
                    'data_registro' => now(),
                    'hash_validacao' => Str::uuid(), // Gera um código único para o certificado depois
                    'tipo_participacao' => 'ouvinte'
                ]
            );

            DB::commit();

            // Redireciona para sucesso
            return redirect()->route('evento.publico.sucesso', $evento->slug)
                ->with('participante', $participante->nome_completo)
                ->with('mensagem_extra', 'Presença confirmada na atividade: ' . $atividade->titulo);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->with('error', 'Erro ao registrar presença: ' . $e->getMessage())
                ->withInput();
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
