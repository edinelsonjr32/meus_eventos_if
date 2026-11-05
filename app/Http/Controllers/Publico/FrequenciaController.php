<?php

namespace App\Http\Controllers\Publico;

use App\Rules\Cpf;
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
     * Devolve um token CSRF novo (e renova o cookie de sessão).
     * Usado pelo front para se recuperar de um 419 sem perder o formulário.
     */
    public function csrf($token)
    {
        Atividade::where('token_frequencia', $token)->firstOrFail();

        return response()->json(['token' => csrf_token()])
            ->header('Cache-Control', 'no-store, private');
    }

    private function falha(Request $request, string $mensagem, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $mensagem], $status);
        }

        return back()->with('error', $mensagem)->withInput();
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
            return $this->falha($request, 'Evento não encontrado para esta atividade.', 404);
        }

        // 2. Validação dos dados recebidos
        $rules = [
            'nome_completo' => ['required', 'string', 'max:255'],
            'cpf'           => ['required', 'string', 'size:14', new Cpf],
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

        // 2.1 Validação de localização (somente se o evento tiver coordenadas configuradas)
        if (filled($evento->latitude) && filled($evento->longitude)) {
            if (!is_numeric($request->user_lat) || !is_numeric($request->user_lng)) {
                return $this->falha($request, 'Não foi possível obter sua localização. Permita o acesso ao GPS e tente novamente.', 422);
            }

            $distancia = $this->calcularDistancia($request->user_lat, $request->user_lng, $evento->latitude, $evento->longitude);
            $raio = (int) ($evento->raio_permitido ?: 300);

            if ($distancia > $raio) {
                return $this->falha($request, "Você está a {$distancia} m do local do evento (máximo permitido: {$raio} m).", 403);
            }
        }

        // 3. Verifica colisão de E-mail (Segurança)
        // Impede usar um email que já pertence a OUTRO CPF
        $emailEmUsoPorOutro = Participante::where('email', $request->email)
            ->where('cpf', '!=', $request->cpf)
            ->exists();

        if ($emailEmUsoPorOutro) {
            return $this->falha($request, 'Este e-mail já está em uso por outro CPF.', 422);
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
            if ($request->expectsJson()) {
                session()->flash('participante', $participante->nome_completo);
                session()->flash('mensagem_extra', 'Presença confirmada na atividade: ' . $atividade->titulo);
                return response()->json(['redirect' => route('evento.publico.sucesso', $evento->slug)]);
            }

            return redirect()->route('evento.publico.sucesso', $evento->slug)
                ->with('participante', $participante->nome_completo)
                ->with('mensagem_extra', 'Presença confirmada na atividade: ' . $atividade->titulo);
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Erro ao registrar presença: ' . $e->getMessage()], 500);
            }
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
        $dist = acos(max(-1, min(1, $dist)));
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;

        // Converte milhas para Metros
        return round($miles * 1.609344 * 1000);
    }
}
