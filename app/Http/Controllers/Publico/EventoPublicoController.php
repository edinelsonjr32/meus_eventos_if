<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Inscricao;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth; // Importante para verificar login
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EventoPublicoController extends Controller
{
    /**
     * Exibe a Landing Page do Evento.
     */
    public function show($slug)
    {

        $evento = Evento::where('slug', $slug)->firstOrFail();

        // 1. Carrega e ordena todas as atividades
        $atividades = $evento->atividades()
            ->orderBy('data_inicio', 'asc') // Ordena primeiro por data completa e hora
            ->get();

        // 2. Agrupa as atividades por dia (YYYY-MM-DD) para estruturar o cronograma
        $cronogramaPorDia = $atividades->groupBy(function ($atividade) {
            // Usa o Carbon para formatar e agrupar pela data
            return $atividade->data_inicio->format('Y-m-d');
        });

        // Verifica se o usuário está inscrito (para botões de inscrição na view)
        $participanteLogado = Auth::check() ? Participante::where('user_id', Auth::id())->first() : null;
        $inscrito = $participanteLogado ? Inscricao::where('evento_id', $evento->id)->where('participante_id', $participanteLogado->id)->exists() : false;

        // Carrega turmas para o select do formulário
        $turmas = Turma::with('curso')->get()->sortBy('nome_completo');

        return view('publico.eventos.show', compact('evento', 'cronogramaPorDia', 'inscrito', 'turmas'));
    }



    /**
     * Processa a Inscrição no Evento (Com Vínculo de Usuário).
     */
    public function store(Request $request, $slug)
    {


        $evento = Evento::where('slug', $slug)->firstOrFail();



        // 1. Definição de Regras de Validação
        $rules = [
            'nome_completo' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'size:14'],
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
        // VALIDAÇÃO DE DUPLICIDADE (CPF NO EVENTO) - MANTIDA
        // =================================================================
        $jaInscrito = Inscricao::where('evento_id', $evento->id)
            ->whereHas('participante', function ($q) use ($request) {
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

            $userId = Auth::id(); // Pega o ID do usuário logado (será NULL se ninguém estiver logado)

            // 2. Cria/Atualiza Participante (O foco principal)
            $dadosParticipante = [
                'nome_completo' => $request->nome_completo,
                'email' => $request->email,
                'tipo_vinculo' => $request->tipo_vinculo,
                'matricula' => in_array($request->tipo_vinculo, ['aluno', 'servidor']) ? $request->matricula : null,
                'turma_id' => $request->tipo_vinculo === 'aluno' ? $request->turma_id : null,

            ];
            

            // Atualiza o Participante se o CPF já existir, ou cria um novo
            $participante = Participante::updateOrCreate(
                ['cpf' => $request->cpf],
                $dadosParticipante
            );

            // 3. Cria a Inscrição
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
            return back()->with('error', 'Erro ao processar inscrição: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Tela de Sucesso.
     */
    public function sucesso($slug)
    {
        $evento = Evento::where('slug', $slug)->firstOrFail();
        return view('publico.eventos.sucesso', compact('evento'));
    }
}
