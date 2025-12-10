<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Frequencia;
use App\Models\Inscricao;
use App\Models\Turma;
use App\Models\Participante;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon; // Importante para calcular a data
use Illuminate\Validation\Rule;

class AtividadeController extends Controller
{
    // ... outros métodos (index, create, etc) ...

    /**
     * Salva a nova atividade no banco.
     */
    /**
     * Salva a nova atividade no banco.
     */
    public function store(Request $request, Evento $evento)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'tipo' => 'required|string',
            'data_inicio' => 'required|date',
            'carga_horaria' => 'required|integer|min:1',
            'vagas' => 'nullable|integer|min:0',
        ]);

        // CORREÇÃO 1: Cast para (int) para evitar TypeError no Carbon
        $horas = (int) $validated['carga_horaria'];

        // CORREÇÃO 2: Cálculo seguro da data_fim
        $inicio = Carbon::parse($validated['data_inicio']);
        $fim = $inicio->copy()->addHours($horas);

        $evento->atividades()->create([
            'titulo' => $validated['titulo'],
            'tipo' => $validated['tipo'],
            'data_inicio' => $validated['data_inicio'],
            'data_fim' => $fim, // Agora o Model aceitará este campo
            'carga_horaria' => $horas,
            'vagas' => $request->vagas,
            'token_frequencia' => Str::random(10),
        ]);

        return back()->with('success', 'Atividade adicionada com sucesso à programação!');
    }

    /**
     * Exibe a lista unificada de participantes (Inscritos + Presentes).
     */
    public function participantes(Atividade $atividade)
    {
        // 1. Busca quem já tem presença confirmada (Tabela Frequencias)
        $frequencias = $atividade->frequencias()
            ->with('participante.turma')
            ->get()
            ->keyBy('participante_id'); // Indexa pelo ID do participante para busca rápida

        // 2. Busca quem se inscreveu antecipadamente (Tabela Atividade_Inscricoes)
        $inscritos = $atividade->inscritos()
            ->with('turma')
            ->get();

        // 3. Monta a Lista Unificada
        $lista = collect();

        // A. Processa os Pré-Inscritos
        foreach ($inscritos as $inscrito) {
            $temPresenca = $frequencias->has($inscrito->id);

            $lista->push((object)[
                'participante' => $inscrito,
                'frequencia' => $temPresenca ? $frequencias[$inscrito->id] : null,
                'status' => $temPresenca ? 'presente' : 'inscrito', // Status para a View
                'data_registro' => $temPresenca ? $frequencias[$inscrito->id]->created_at : $inscrito->pivot->created_at,
                'tipo_participacao' => $temPresenca ? $frequencias[$inscrito->id]->tipo_participacao : 'ouvinte' // Default
            ]);
        }

        // B. Adiciona quem está presente mas NÃO se inscreveu antes (Chegou na hora/Manual)
        foreach ($frequencias as $id => $freq) {
            // Se este ID não estava na lista de inscritos, adiciona agora
            if (!$inscritos->contains('id', $id)) {
                $lista->push((object)[
                    'participante' => $freq->participante,
                    'frequencia' => $freq,
                    'status' => 'presente',
                    'data_registro' => $freq->created_at,
                    'tipo_participacao' => $freq->tipo_participacao
                ]);
            }
        }

        // Ordena por nome
        $lista = $lista->sortBy('participante.nome_completo');

        return view('admin.atividades.participantes', compact('atividade', 'lista'));
    }

    /**
     * Tela de cadastro manual (Sem Modal).
     */
    public function createManual(\App\Models\Atividade $atividade)
    {
        // Garante que as turmas estão carregadas junto com o curso para exibição
        $turmas = \App\Models\Turma::with('curso')->get()->sortBy('nome_completo');
        return view('admin.atividades.create-manual', compact('atividade', 'turmas'));
    }

    /**
     * Salva o participante e registra a presença manualmente.
     */
    public function storeManual(Request $request, Atividade $atividade)
    {
        // 1. Validações
        $request->validate([
            'nome_completo' => 'required|string',
            'cpf' => 'required|string',
            'email' => 'required|email',
            'tipo_vinculo' => 'required',

            // Regras Condicionais:
            'matricula' => [
                Rule::requiredIf(fn() => in_array($request->tipo_vinculo, ['aluno', 'servidor'])),
                'nullable',
                'string',
                'max:50'
            ],
            // Só é exigido se for 'aluno'
            'turma_id' => 'required_if:tipo_vinculo,aluno|nullable|exists:turmas,id',
        ]);

        // 2. Cria ou Atualiza o Participante
        // O `updateOrCreate` garante que o registro do participante esteja atualizado ou seja novo.
        $participante = Participante::updateOrCreate(
            ['cpf' => $request->cpf],
            [
                'nome_completo' => $request->nome_completo,
                'email' => $request->email,
                'tipo_vinculo' => $request->tipo_vinculo,
                'matricula' => in_array($request->tipo_vinculo, ['aluno', 'servidor']) ? $request->matricula : null,
                'turma_id' => $request->tipo_vinculo === 'aluno' ? $request->turma_id : null,
                // user_id é null aqui, a menos que você queira sincronizar com User.
            ]
        );

        // --- NOVA LÓGICA DE EVENTO E INSCRIÇÃO ---

        // 3. Obtém o Evento
        // Assumimos que a Atividade tem um relacionamento 'evento'
        $evento = $atividade->evento;

        if (!$evento) {
            return back()->with('error', 'Erro: A atividade não está vinculada a um evento.')->withInput();
        }

        // 4. Verifica e Cria Inscrição no Evento
        $inscricaoExistente = Inscricao::where('participante_id', $participante->id)
            ->where('evento_id', $evento->id)
            ->first();

        // Se o participante NÃO estiver inscrito no evento, fazemos a inscrição automática
        if (!$inscricaoExistente) {
            Inscricao::create([
                'evento_id' => $evento->id,
                'participante_id' => $participante->id,
                'data_inscricao' => Carbon::now(),
                // Define o status ou tipo de acordo com a lógica do seu sistema (ex: 'manual', 'confirmada')
                'status_inscricao' => 'manual',
            ]);
            $message = 'Participante inscrito no Evento e Presença confirmada na Atividade!';
        } else {
            $message = 'Presença confirmada com sucesso!';
        }

        // --- FIM DA NOVA LÓGICA ---

        // 5. Registra a Presença (Frequencia)
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

        // 6. Redirecionamento com Feedback
        return redirect()->route('admin.atividades.participantes', $atividade->id)
            ->with('success', $message);
    }

    public function destroyFrequencia($id)
    {
        Frequencia::findOrFail($id)->delete();
        return back()->with('success', 'Presença removida.');
    }

    public function destroy(Atividade $atividade)
    {
        // Validação de segurança
        if ($atividade->frequencias()->count() > 0) {
            return back()->with('error', 'Não é possível excluir: Existem alunos com presença registrada nesta atividade.');
        }

        $atividade->delete();
        return back()->with('success', 'Atividade removida da programação.');
    }

    public function updateRole(Request $request, $id)
    {
        $freq = Frequencia::findOrFail($id);
        $freq->update(['tipo_participacao' => $request->role]);
        return back()->with('success', 'Função atualizada.');
    }

    public function exportar(Atividade $atividade)
    {
        $nomeArquivo = 'Lista-' . Str::slug($atividade->titulo) . '.csv';
        $frequencias = $atividade->frequencias()->with('participante.turma')->get();

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$nomeArquivo",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function () use ($frequencias) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF"); // BOM para Excel
            fputcsv($file, ['Nome', 'CPF', 'Email', 'Vínculo', 'Matrícula', 'Turma', 'Função', 'Data'], ';');

            foreach ($frequencias as $row) {
                fputcsv($file, [
                    $row->participante->nome_completo,
                    $row->participante->cpf,
                    $row->participante->email,
                    $row->participante->tipo_vinculo,
                    $row->participante->matricula,
                    $row->participante->turma->nome_completo ?? '-',
                    $row->tipo_participacao,
                    $row->created_at->format('d/m/Y H:i')
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Realiza o Check-in rápido de um participante já inscrito.
     */
    public function storeCheckin(Atividade $atividade, Participante $participante)
    {
        \App\Models\Frequencia::firstOrCreate(
            ['participante_id' => $participante->id, 'atividade_id' => $atividade->id],
            [
                'data_registro' => now(),
                'hash_validacao' => \Illuminate\Support\Str::uuid(),
                'tipo_participacao' => 'ouvinte'
            ]
        );

        return back()->with('success', 'Check-in realizado com sucesso!');
    }
}
