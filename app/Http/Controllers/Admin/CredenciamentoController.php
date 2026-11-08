<?php

namespace App\Http\Controllers\Admin;

use App\Rules\Cpf;
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
        $this->authorize('credenciar', $evento);

        $totalInscritos = $evento->inscricoes()->count();
        $totalCredenciados = $evento->inscricoes()->whereNotNull('checkin_at')->count();

        // CORREÇÃO ESSENCIAL: Carrega as turmas com o relacionamento curso
        // Isso garante que o JSON passado para o Alpine tenha 'curso' dentro de 'turma'
        $turmas = Turma::with('curso')->get()->sortBy('nome_completo');

        return view('admin.credenciamento.index', compact('evento', 'totalInscritos', 'totalCredenciados', 'turmas'));
    }

    public function create(Evento $evento)
    {
        $this->authorize('credenciar', $evento);

        // Carrega todas as turmas e cursos
        $turmas = Turma::with('curso')->get()->sortBy('nome_completo');

        return view('admin.credenciamento.create-manual', compact('evento', 'turmas'));
    }

    public function storeManual(Request $request, Evento $evento)
    {
        $this->authorize('credenciar', $evento);

        // 1. Validação Completa (Igual à que estava no modal)
        $request->validate([
            'nome_completo' => 'required|string|max:255',
            'cpf' => ['required', 'string', 'size:14', new Cpf],
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
        $this->authorize('credenciar', $evento);

        $term = trim((string) $request->get('q'));
        // Somente dígitos, para comparar CPF independente de máscara (123.456.789-00 / 12345678900)
        $digitos = preg_replace('/\D/', '', $term);
        // Escapa curingas do LIKE digitados pelo usuário
        $like = '%' . addcslashes($term, '%_\\') . '%';

        $resultados = $evento->inscricoes()
            ->with(['participante'])
            ->whereHas('participante', function ($q) use ($like, $digitos) {
                $q->where(function ($q) use ($like, $digitos) {
                    $q->where('nome_completo', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('cpf', 'like', $like)
                        ->orWhere('matricula', 'like', $like);

                    if ($digitos !== '') {
                        $q->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(TRIM(cpf), '.', ''), '-', ''), ' ', '') LIKE ?",
                            ["%{$digitos}%"]
                        );
                    }
                });
            })
            ->limit(10)
            ->get();

        return response()->json($resultados);
    }

    /**
     * Marca a chegada de forma ATÔMICA: só o primeiro check-in grava; repetir (duplo clique, outra aba, outro operador)
     * não muda a hora nem conta de novo. Devolve se foi realmente novo e quando o participante chegou.
     *
     * @return array{novo:bool,checkin_at:\Illuminate\Support\Carbon}
     */
    private function registrarChegada(Evento $evento, Participante $participante): array
    {
        $inscricao = Inscricao::where('evento_id', $evento->id)->where('participante_id', $participante->id)->firstOrFail();

        $novo = Inscricao::whereKey($inscricao->id)->whereNull('checkin_at')->update(['checkin_at' => now()]) === 1;

        return ['novo' => $novo, 'checkin_at' => $inscricao->fresh()->checkin_at];
    }

    /** Contadores do evento (sempre do banco: a tela não conta sozinha). */
    private function contadores(Evento $evento): array
    {
        $total = $evento->inscricoes()->count();
        $feitos = $evento->inscricoes()->whereNotNull('checkin_at')->count();

        return ['total' => $total, 'credenciados' => $feitos, 'pendentes' => $total - $feitos];
    }

    /**
     * Realiza o Check-in Global (Chegou no evento)
     */
    public function checkin(Evento $evento, Participante $participante)
    {
        $this->authorize('credenciar', $evento);

        ['novo' => $novo, 'checkin_at' => $quando] = $this->registrarChegada($evento, $participante);

        return response()->json([
            'success' => true,
            'novo' => $novo,
            'ja_realizado' => !$novo,
            'message' => $novo ? 'Check-in realizado!' : "{$participante->nome_completo} já havia feito check-in às {$quando->format('H:i')}.",
            'checkin_at' => $quando->format('H:i'),
        ] + $this->contadores($evento));
    }

    /** Contadores atuais (a tela os consulta após cada ação). */
    public function stats(Evento $evento)
    {
        $this->authorize('credenciar', $evento);

        return response()->json($this->contadores($evento));
    }

    /**
     * Gera a Etiqueta para Impressão
     */
    public function etiqueta(Evento $evento, Participante $participante)
    {
        $this->authorize('credenciar', $evento);

        // Imprimir a etiqueta também credencia (uma única vez, sem alterar a hora de quem já chegou)
        $this->registrarChegada($evento, $participante);

        return view('admin.credenciamento.etiqueta', compact('evento', 'participante'));
    }

    /**
     * Lista de TODOS os inscritos com o status (check-in ou pendente), filtros e busca.
     */
    public function lista(Request $request, Evento $evento)
    {
        $this->authorize('credenciar', $evento);

        $status = in_array($request->input('status'), ['checkin', 'pendente'], true) ? $request->input('status') : null;
        $inscricoes = $this->consultaLista($request, $evento, $status)->paginate(50)->withQueryString();

        return view('admin.credenciamento.lista', [
            'evento' => $evento,
            'inscricoes' => $inscricoes,
            'status' => $status,
            'busca' => trim((string) $request->input('q')),
        ] + $this->contadores($evento));
    }

    /** Exporta a lista (CSV) respeitando status e busca. */
    public function exportarLista(Request $request, Evento $evento)
    {
        $this->authorize('credenciar', $evento);

        $status = in_array($request->input('status'), ['checkin', 'pendente'], true) ? $request->input('status') : null;
        $linhas = $this->consultaLista($request, $evento, $status)->get();

        return response()->streamDownload(function () use ($linhas) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Nome', 'CPF', 'E-mail', 'Vínculo', 'Matrícula', 'Curso / Turma', 'Status', 'Check-in em', 'Inscrito em'], ';');
            foreach ($linhas as $i) {
                $p = $i->participante;
                fputcsv($f, [$p->nome_completo, $p->cpf, $p->email, $p->tipo_vinculo, $p->matricula, $p->turma ? ($p->turma->curso->nome ?? '') . ' ' . $p->turma->nome_completo : '',
                    $i->checkin_at ? 'Check-in' : 'Pendente', $i->checkin_at?->format('d/m/Y H:i'), $i->data_inscricao ? \Illuminate\Support\Carbon::parse($i->data_inscricao)->format('d/m/Y H:i') : ''], ';');
            }
            fclose($f);
        }, 'inscritos-' . \Illuminate\Support\Str::slug($evento->titulo) . '-' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function consultaLista(Request $request, Evento $evento, ?string $status)
    {
        $busca = trim((string) $request->input('q'));
        $digitos = preg_replace('/\D/', '', $busca);
        $like = '%' . addcslashes($busca, '%_\\') . '%';

        return $evento->inscricoes()->with(['participante.turma.curso'])
            ->join('participantes', 'participantes.id', '=', 'inscricoes.participante_id')
            ->select('inscricoes.*')
            ->when($status === 'checkin', fn ($q) => $q->whereNotNull('inscricoes.checkin_at'))
            ->when($status === 'pendente', fn ($q) => $q->whereNull('inscricoes.checkin_at'))
            ->when($busca !== '', fn ($q) => $q->where(function ($w) use ($like, $digitos) {
                $w->where('participantes.nome_completo', 'like', $like)->orWhere('participantes.email', 'like', $like)->orWhere('participantes.matricula', 'like', $like)->orWhere('participantes.cpf', 'like', $like);
                if ($digitos !== '') {
                    $w->orWhereRaw("REPLACE(REPLACE(REPLACE(TRIM(participantes.cpf), '.', ''), '-', ''), ' ', '') LIKE ?", ["%{$digitos}%"]);
                }
            }))
            ->orderBy('participantes.nome_completo');
    }
    /**
     * Cadastra um novo participante na hora (Check-in Imediato).
     */
    public function store(Request $request, Evento $evento)
    {
        $this->authorize('credenciar', $evento);

        // 1. Validação (Adicionando campos e regras condicionais)
        $validated = $request->validate([
            'nome_completo' => 'required|string|max:255',
            'cpf' => ['required', 'string', 'size:14', new Cpf],
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
