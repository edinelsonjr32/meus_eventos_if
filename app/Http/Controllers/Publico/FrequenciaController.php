<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Atividade;
use App\Models\Participante;
use App\Models\Frequencia;
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
        // 1. Identifica a Atividade e o Evento
        $atividade = Atividade::where('token_frequencia', $token)->with('evento')->firstOrFail();
        $evento = $atividade->evento;

        // =================================================================
        // LÓGICA DE GEO-FENCING (CERCA VIRTUAL)
        // =================================================================
        if ($evento->latitude && $evento->longitude) {

            $latUser = $request->input('user_lat');
            $lngUser = $request->input('user_lng');

            // Verifica se o navegador enviou as coordenadas
            if (!$latUser || !$lngUser) {
                return back()
                    ->withErrors(['gps' => 'Localização obrigatória não detectada. Por favor, habilite o GPS e permita o acesso no navegador.'])
                    ->withInput();
            }

            // Calcula a distância
            $distanciaMetros = $this->calcularDistancia(
                $evento->latitude,
                $evento->longitude,
                $latUser,
                $lngUser
            );

            // Valida o Raio
            $raioMaximo = $evento->raio_permitido ?? 300; // Padrão 300m

            if ($distanciaMetros > $raioMaximo) {
                return back()
                    ->withErrors(['gps' => "Você está a {$distanciaMetros} metros do local. O limite é {$raioMaximo}m. Aproxime-se do evento para confirmar presença."])
                    ->withInput();
            }
        }
        // =================================================================

        // 2. Validação dos Dados Pessoais (Regras do IFPA)
        $request->validate([
            'nome_completo' => 'required|string|max:255',
            'cpf' => 'required|string|size:14',
            'email' => 'required|email',
            'tipo_vinculo' => 'required|in:aluno,servidor,externo',
            'matricula' => [
                Rule::requiredIf(fn() => in_array($request->tipo_vinculo, ['aluno', 'servidor'])),
                'nullable',
                'string',
                'max:50'
            ],
            'turma_id' => 'required_if:tipo_vinculo,aluno|nullable|exists:turmas,id',
        ]);

        // 3. Busca ou Cria o Participante
        // Se o usuário estiver logado, aproveita para vincular o ID dele
        $dadosParticipante = [
            'nome_completo' => $request->nome_completo,
            'email' => $request->email,
            'tipo_vinculo' => $request->tipo_vinculo,
            'matricula' => in_array($request->tipo_vinculo, ['aluno', 'servidor']) ? $request->matricula : null,
            'turma_id' => $request->tipo_vinculo === 'aluno' ? $request->turma_id : null,
        ];

        if (Auth::check()) {
            $dadosParticipante['user_id'] = Auth::id();
        }

        $participante = Participante::updateOrCreate(
            ['cpf' => $request->cpf],
            $dadosParticipante
        );

        // 4. Registra a Frequência (Evita duplicidade com firstOrCreate)
        Frequencia::firstOrCreate(
            [
                'participante_id' => $participante->id,
                'atividade_id' => $atividade->id
            ],
            [
                'data_registro' => now(),
                'hash_validacao' => Str::uuid(),
                'tipo_participacao' => 'ouvinte'
            ]
        );

        return redirect()->route('frequencia.sucesso')
            ->with('mensagem', 'Presença confirmada com sucesso! (Localização Validada)');
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
