<?php

namespace App\Http\Controllers\Admin;

use App\Rules\Cpf;
use App\Http\Controllers\Controller;
use App\Models\Atividade;
use App\Models\Local;
use App\Services\LocalService;
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
        $this->authorize('gerenciar', $evento);

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'tipo' => 'required|string',
            'responsavel' => 'nullable|string|max:255',
            'local_id' => ['nullable', 'integer', Rule::exists('locais', 'id')->where('evento_id', $evento->id)->where('ativo', true)],
            'ignorar_conflito' => 'nullable|boolean',
            'data_inicio' => 'required|date',
            'carga_horaria' => 'required|integer|min:1|max:1000',
            'vagas' => 'nullable|integer|min:0',
        ], ['local_id.exists' => 'Escolha um local ativo cadastrado neste evento.']);

        // CORREÇÃO 1: Cast para (int) para evitar TypeError no Carbon
        $horas = (int) $validated['carga_horaria'];

        // CORREÇÃO 2: Cálculo seguro da data_fim
        $inicio = Carbon::parse($validated['data_inicio']);
        $fim = $inicio->copy()->addHours($horas);

        if ($erro = $this->conflitoDeLocal($evento->id, $validated['local_id'] ?? null, $inicio, $fim, $validated['vagas'] ?? null, $request->boolean('ignorar_conflito'))) {
            return back()->withInput()->withErrors(['local_id' => $erro]);
        }

        $evento->atividades()->create([
            'titulo' => $validated['titulo'],
            'tipo' => $validated['tipo'],
            'responsavel' => $validated['responsavel'] ?? null,
            'local_id' => $validated['local_id'] ?? null,
            'local' => null,
            'data_inicio' => $validated['data_inicio'],
            'data_fim' => $fim, // Agora o Model aceitará este campo
            'carga_horaria' => $horas,
            'vagas' => $validated['vagas'] ?? null,
            'token_frequencia' => Str::random(10),
        ]);

        return back()->with('success', 'Atividade adicionada com sucesso à programação!');
    }

    /** Mensagem de erro se o local já está ocupado no horário ou não comporta as vagas (null = tudo certo ou conflito ignorado). */
    private function conflitoDeLocal(int $eventoId, $localId, Carbon $inicio, Carbon $fim, $vagas, bool $ignorar, ?int $atividadeId = null): ?string
    {
        if ($ignorar || !$localId) {
            return null;
        }
        try {
            app(LocalService::class)->garantirLivre($eventoId, (int) $localId, $inicio, $fim, $atividadeId);
            app(LocalService::class)->garantirCapacidade((int) $localId, $vagas ? (int) $vagas : null);
        } catch (\DomainException $e) {
            return $e->getMessage();
        }

        return null;
    }

    public const TIPOS = ['palestra' => 'Palestra', 'minicurso' => 'Minicurso', 'oficina' => 'Oficina', 'mesa_redonda' => 'Mesa Redonda'];

    public function edit(Atividade $atividade)
    {
        $this->authorize('gerenciar', $atividade->evento);

        return view('admin.atividades.edit', [
            'atividade' => $atividade,
            'evento' => $atividade->evento,
            'tipos' => self::TIPOS,
            'locais' => $atividade->evento->locais()->where(fn ($q) => $q->where('ativo', true)->orWhere('id', $atividade->local_id))->get(),
            'inscritos' => $atividade->inscritos()->count(),
            'equipe' => $atividade->evento->equipe()->with('participante')->get()->pluck('participante.nome_completo')->filter()->unique()->sort()->values(),
        ]);
    }

    /** Atualiza os dados da atividade; o fim é recalculado pela carga horária e o certificado reflete a C.H. nova. */
    public function update(Request $request, Atividade $atividade)
    {
        $this->authorize('gerenciar', $atividade->evento);

        $dados = $request->validate([
            'titulo' => 'required|string|max:255',
            'tipo' => ['required', Rule::in(array_keys(self::TIPOS))],
            'responsavel' => 'nullable|string|max:255',
            'local_id' => ['nullable', 'integer', Rule::exists('locais', 'id')->where('evento_id', $atividade->evento_id)->where(fn ($q) => $q->where('ativo', true)->orWhere('id', $atividade->local_id))],
            'ignorar_conflito' => 'nullable|boolean',
            'data_inicio' => 'required|date',
            'carga_horaria' => 'required|integer|min:1|max:1000',
            'vagas' => 'nullable|integer|min:0',
        ], ['local_id.exists' => 'Escolha um local ativo cadastrado neste evento.']);

        $inscritos = $atividade->inscritos()->count();
        if (!empty($dados['vagas']) && $dados['vagas'] < $inscritos) {
            return back()->withInput()->withErrors(['vagas' => "Já existem {$inscritos} inscrito(s): as vagas não podem ser menores que isso (use 0 para ilimitado)."]);
        }

        $inicio = Carbon::parse($dados['data_inicio']);
        $fimNovo = $inicio->copy()->addHours((int) $dados['carga_horaria']);
        if ($erro = $this->conflitoDeLocal($atividade->evento_id, $dados['local_id'] ?? null, $inicio, $fimNovo, $dados['vagas'] ?? null, $request->boolean('ignorar_conflito'), $atividade->id)) {
            return back()->withInput()->withErrors(['local_id' => $erro]);
        }

        $atividade->update([
            'titulo' => $dados['titulo'],
            'tipo' => $dados['tipo'],
            'responsavel' => $dados['responsavel'] ?? null,
            'local_id' => $dados['local_id'] ?? null,
            'local' => null,
            'data_inicio' => $inicio,
            'data_fim' => $inicio->copy()->addHours((int) $dados['carga_horaria']),
            'carga_horaria' => (int) $dados['carga_horaria'],
            'vagas' => $dados['vagas'] ?? null,
        ]);

        return redirect()->route('admin.eventos.show', $atividade->evento_id)->with('success', 'Atividade atualizada.');
    }

    /** PDF com uma página por atividade: cabeçalho do evento, QR Code de frequência, título e responsável. */
    public function qrcodesPdf(Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $paginas = $evento->atividades()->orderBy('data_inicio')->get()->map(fn (Atividade $a) => [
            'atividade' => $a,
            'qr' => 'data:image/svg+xml;base64,' . base64_encode((string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(360)->margin(0)->generate(route('frequencia.form', $a->token_frequencia))),
        ]);

        if ($paginas->isEmpty()) {
            return back()->with('error', 'Este evento ainda não tem atividades para gerar QR Codes.');
        }

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.atividades.qrcodes-pdf', ['evento' => $evento, 'paginas' => $paginas])
            ->setPaper('a4', 'portrait')
            ->download('QRCodes-frequencia-' . Str::slug($evento->titulo) . '.pdf');
    }

    /**
     * Exibe a lista unificada de participantes (Inscritos + Presentes).
     */
    public function participantes(Atividade $atividade)
    {
        $this->authorize('gerenciar', $atividade->evento);

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
        $this->authorize('gerenciar', $atividade->evento);

        // Garante que as turmas estão carregadas junto com o curso para exibição
        $turmas = \App\Models\Turma::with('curso')->get()->sortBy('nome_completo');
        return view('admin.atividades.create-manual', compact('atividade', 'turmas'));
    }

    /**
     * Salva o participante e registra a presença manualmente.
     */
    public function storeManual(Request $request, Atividade $atividade)
    {
        $this->authorize('gerenciar', $atividade->evento);

        // 1. Validações
        $request->validate([
            'nome_completo' => 'required|string',
            'cpf' => ['required', 'string', 'size:14', new Cpf],
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
        $freq = Frequencia::with('atividade.evento')->findOrFail($id);
        $this->authorize('gerenciar', $freq->atividade->evento);
        $freq->delete();
        return back()->with('success', 'Presença removida.');
    }

    /** Remove da atividade um participante inscrito que ainda não registrou presença. */
    public function destroyInscrito(Atividade $atividade, Participante $participante)
    {
        $this->authorize('gerenciar', $atividade->evento);

        if ($atividade->frequencias()->where('participante_id', $participante->id)->exists()) {
            return back()->with('error', 'Este participante já registrou presença: remova a presença primeiro.');
        }
        if (!$atividade->inscritos()->where('participantes.id', $participante->id)->exists()) {
            return back()->with('error', 'Este participante não está inscrito nesta atividade.');
        }

        $atividade->inscritos()->detach($participante->id);

        return back()->with('success', "{$participante->nome_completo} foi removido(a) da atividade.");
    }

    public function destroy(Atividade $atividade)
    {
        $this->authorize('gerenciar', $atividade->evento);

        // Validação de segurança
        if ($atividade->frequencias()->count() > 0) {
            return back()->with('error', 'Não é possível excluir: Existem alunos com presença registrada nesta atividade.');
        }

        $atividade->delete();
        return back()->with('success', 'Atividade removida da programação.');
    }

    public function updateRole(Request $request, $id)
    {
        $freq = Frequencia::with('atividade.evento')->findOrFail($id);
        $this->authorize('gerenciar', $freq->atividade->evento);
        $freq->update(['tipo_participacao' => $request->role]);
        return back()->with('success', 'Função atualizada.');
    }

    public function exportar(Atividade $atividade)
    {
        $this->authorize('gerenciar', $atividade->evento);

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
        $this->authorize('gerenciar', $atividade->evento);

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
