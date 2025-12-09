<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Inscricao;
use App\Models\Participante;
use App\Models\Turma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CredenciamentoController extends Controller
{
    /**
     * Tela Principal do Quiosque (Recepção)
     */
    public function index(Evento $evento)
    {
        if ($evento->criado_por !== Auth::id()) abort(403);

        $totalInscritos = $evento->inscricoes()->count();
        $totalCredenciados = $evento->inscricoes()->whereNotNull('checkin_at')->count();

        // CORREÇÃO ESSENCIAL: Carrega as turmas com o relacionamento curso
        // Isso garante que o JSON passado para o Alpine tenha 'curso' dentro de 'turma'
        $turmas = Turma::with('curso')->get()->sortBy('nome_completo');

        return view('admin.credenciamento.index', compact('evento', 'totalInscritos', 'totalCredenciados', 'turmas'));
    }

    public function create(Evento $evento)
    {
        if ($evento->criado_por !== Auth::id()) abort(403);

        // Carrega todas as turmas e cursos
        $turmas = Turma::with('curso')->get()->sortBy('nome_completo');

        return view('admin.credenciamento.create-manual', compact('evento', 'turmas'));
    }

    public function storeManual(Request $request, Evento $evento)
    {
        // 1. Validação Completa (Igual à que estava no modal)
        $request->validate([
            'nome_completo' => 'required|string|max:255',
            'cpf' => 'required|string|max:14',
            'email' => 'required|email',
            'tipo_vinculo' => 'required|in:aluno,servidor,externo',

            'matricula' => [
                \Illuminate\Validation\Rule::requiredIf(fn() => in_array($request->tipo_vinculo, ['aluno', 'servidor'])),
                'nullable',
                'string',
                'max:50'
            ],
            'turma_id' => 'required_if:tipo_vinculo,aluno|nullable|exists:turmas,id',
        ]);

        // 2. Verifica Duplicidade (CPF)
        $participanteExistente = Participante::where('cpf', $request->cpf)->first();

        if ($participanteExistente) {
            // Se o participante já existe, verifica se já está inscrito neste evento
            $jaInscrito = $evento->inscricoes()->where('participante_id', $participanteExistente->id)->exists();
            if ($jaInscrito) {
                return back()->with('error', 'Este CPF já está inscrito no evento e não apareceu na busca. Tente buscá-lo novamente, ou remova ele da equipe.');
            }
        }

        // 3. Cria/Atualiza Participante
        $participante = Participante::updateOrCreate(
            ['cpf' => $request->cpf],
            [
                'nome_completo' => $request->nome_completo,
                'email' => $request->email,
                'tipo_vinculo' => $request->tipo_vinculo,
                'matricula' => in_array($request->tipo_vinculo, ['aluno', 'servidor']) ? $request->matricula : null,
                'turma_id' => $request->tipo_vinculo === 'aluno' ? $request->turma_id : null,
            ]
        );

        // 4. Cria Inscrição e Credencia (Check-in Imediato)
        \App\Models\Inscricao::create([
            'evento_id' => $evento->id,
            'participante_id' => $participante->id,
            'data_inscricao' => now(),
            'checkin_at' => now() // Credenciamento imediato
        ]);

        return redirect()->route('admin.eventos.credenciamento.index', $evento->id)
            ->with('success', "Participante {$participante->nome_completo} cadastrado e credenciado com sucesso!");
    }
    /**
     * Busca AJAX para o campo de pesquisa
     */
    public function search(Request $request, Evento $evento)
    {
        $term = $request->get('q');

        $resultados = $evento->inscricoes()
            ->with(['participante'])
            ->whereHas('participante', function ($q) use ($term) {
                $q->where('nome_completo', 'like', "%{$term}%")
                    ->orWhere('cpf', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->limit(10)
            ->get();

        return response()->json($resultados);
    }

    /**
     * Realiza o Check-in Global (Chegou no evento)
     */
    public function checkin(Evento $evento, Participante $participante)
    {
        $inscricao = Inscricao::where('evento_id', $evento->id)
            ->where('participante_id', $participante->id)
            ->firstOrFail();

        // Marca a hora da chegada se ainda não marcou
        if (!$inscricao->checkin_at) {
            $inscricao->update(['checkin_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Check-in realizado!',
            'checkin_at' => $inscricao->checkin_at->format('H:i')
        ]);
    }

    /**
     * Gera a Etiqueta para Impressão
     */
    public function etiqueta(Evento $evento, Participante $participante)
    {
        // Se ainda não fez checkin, faz agora
        $inscricao = Inscricao::where('evento_id', $evento->id)
            ->where('participante_id', $participante->id)
            ->firstOrFail();

        if (!$inscricao->checkin_at) {
            $inscricao->update(['checkin_at' => now()]);
        }

        return view('admin.credenciamento.etiqueta', compact('evento', 'participante'));
    }
    /**
     * Cadastra um novo participante na hora (Check-in Imediato).
     */
    public function store(Request $request, Evento $evento)
    {
        // 1. Validação (Adicionando campos e regras condicionais)
        $validated = $request->validate([
            'nome_completo' => 'required|string|max:255',
            'cpf' => 'required|string|max:14',
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

        // 2. Cria ou Atualiza o Participante (Pelo CPF)
        $participante = \App\Models\Participante::updateOrCreate(
            ['cpf' => $validated['cpf']],
            [
                'nome_completo' => $validated['nome_completo'],
                'email' => $validated['email'],
                'tipo_vinculo' => $validated['tipo_vinculo'],

                // CORREÇÃO: Salvando campos Acadêmicos condicionalmente
                'matricula' => in_array($request->tipo_vinculo, ['aluno', 'servidor']) ? $request->matricula : null,
                'turma_id' => $request->tipo_vinculo === 'aluno' ? $request->turma_id : null,
            ]
        );

        // 3. Cria a Inscrição e já marca o Check-in
        $inscricao = $evento->inscricoes()->updateOrCreate(
            ['participante_id' => $participante->id],
            [
                'data_inscricao' => now(),
                'checkin_at' => now()
            ]
        );

        // Retorna formato compatível com a lista AlpineJS
        return response()->json([
            'success' => true,
            'message' => 'Participante cadastrado e credenciado!',
            'item' => [
                'participante_id' => $participante->id,
                'participante' => $participante,
                'checkin_at' => $inscricao->checkin_at
            ]
        ]);
    }
}
