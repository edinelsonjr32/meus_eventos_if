<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChamadaProposta;
use App\Models\Evento;
use App\Models\Proposta;
use App\Models\PropostaApoio;
use App\Services\PropostaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Gestão das chamadas e das propostas de minicurso/oficina de um evento (administrador do evento). */
class PropostaGestaoController extends Controller
{
    public function __construct(private PropostaService $service)
    {
    }

    public function index(Request $request, Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $status = $request->input('status');
        $propostas = Proposta::with(['proponente', 'participante', 'atividade', 'chamada'])->where('evento_id', $evento->id)
            ->when($status && isset(Proposta::STATUS[$status]), fn ($q) => $q->where('status', $status))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('titulo', 'like', '%' . $request->q . '%')->orWhereHas('proponente', fn ($u) => $u->where('name', 'like', '%' . $request->q . '%'))))
            ->orderByRaw("FIELD(status, 'enviada', 'em_analise', 'ajustes', 'aprovada', 'reprovada', 'retirada')")->latest()->get();

        $contagem = Proposta::where('evento_id', $evento->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.propostas.index', [
            'evento' => $evento, 'chamadas' => $evento->chamadasPropostas()->withCount('propostas')->latest()->get(),
            'propostas' => $propostas, 'contagem' => $contagem, 'status' => $status,
        ]);
    }

    public function storeChamada(Request $request, Evento $evento)
    {
        $this->authorize('gerenciar', $evento);
        $dados = $this->validarChamada($request);

        $evento->chamadasPropostas()->create($dados + ['criado_por' => $request->user()->id]);

        return back()->with('success', 'Chamada de propostas criada.');
    }

    public function updateChamada(Request $request, Evento $evento, ChamadaProposta $chamada)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($chamada->evento_id === $evento->id, 404);

        $chamada->update($this->validarChamada($request));

        return back()->with('success', 'Chamada atualizada.');
    }

    public function destroyChamada(Evento $evento, ChamadaProposta $chamada)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($chamada->evento_id === $evento->id, 404);

        if ($chamada->propostas()->exists()) {
            return back()->with('error', 'Esta chamada já recebeu propostas. Desative-a em vez de excluir.');
        }
        $chamada->delete();

        return back()->with('success', 'Chamada excluída.');
    }

    public function show(Evento $evento, Proposta $proposta)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($proposta->evento_id === $evento->id, 404);

        $proposta->load(['proponente', 'participante', 'apoios.participante', 'atividade', 'chamada']);

        return view('admin.propostas.show', ['evento' => $evento, 'proposta' => $proposta, 'tipos' => ChamadaProposta::TIPOS]);
    }

    public function decisao(Request $request, Evento $evento, Proposta $proposta)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($proposta->evento_id === $evento->id, 404);

        $dados = $request->validate([
            'decisao' => ['required', Rule::in(['em_analise', 'ajustes', 'reprovada', 'aprovada'])],
            'parecer' => 'nullable|string|max:3000',
            'data_inicio' => 'nullable|date', 'data_fim' => 'nullable|date',
            'local' => 'nullable|string|max:255', 'espaco' => 'nullable|string|max:255',
            'equipamentos' => 'nullable|string|max:2000', 'observacoes_organizacao' => 'nullable|string|max:2000',
            'vagas' => 'nullable|integer|min:1|max:2000', 'carga_horaria' => 'nullable|integer|min:1|max:200',
            'ignorar_conflito' => 'nullable|boolean',
        ]);

        try {
            $this->service->decidir($proposta, $request->user(), $dados['decisao'], $dados);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $mensagens = ['aprovada' => 'Proposta aprovada e vinculada à programação do evento.', 'reprovada' => 'Proposta não aprovada. O proponente foi avisado.', 'ajustes' => 'Ajustes solicitados. O proponente foi avisado.', 'em_analise' => 'Proposta marcada como em análise.'];

        return redirect()->route('admin.propostas.show', [$evento, $proposta])->with('success', $mensagens[$dados['decisao']]);
    }

    /** Adiciona a pessoa de apoio como participante inscrita no evento. */
    public function apoioAoEvento(Request $request, Evento $evento, PropostaApoio $apoio)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($apoio->proposta->evento_id === $evento->id, 404);

        // CPF/e-mail podem ser completados na hora
        if ($request->filled('cpf') || $request->filled('email')) {
            $request->validate(['cpf' => ['nullable', 'string', 'size:14', new \App\Rules\Cpf], 'email' => 'nullable|email|max:255']);
            $apoio->update(array_filter(['cpf' => \App\Rules\Cpf::formatar((string) $request->cpf) ?: null, 'email' => $request->email ? mb_strtolower($request->email) : null]));
        }

        try {
            $participante = $this->service->adicionarApoioAoEvento($apoio->fresh(), $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$participante->nome_completo} foi adicionado(a) como participante do evento.");
    }

    /** Emite (ou atualiza) o certificado de ministrante do proponente, depois que a atividade terminou. */
    public function certificado(Evento $evento, Proposta $proposta)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($proposta->evento_id === $evento->id, 404);

        try {
            $this->service->emitirCertificadoMinistrante($evento, $proposta->proponente, auth()->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Certificado de ministrante emitido.');
    }

    private function validarChamada(Request $request): array
    {
        $dados = $request->validate([
            'titulo' => 'required|string|max:255', 'descricao' => 'nullable|string|max:20000',
            'tipos_aceitos' => 'nullable|array', 'tipos_aceitos.*' => Rule::in(array_keys(ChamadaProposta::TIPOS)),
            'inicio' => 'nullable|date', 'fim' => 'nullable|date|after_or_equal:inicio', 'ativa' => 'nullable|boolean',
            'max_por_usuario' => 'nullable|integer|min:1|max:20',
        ], ['titulo.required' => 'Informe o título da chamada.', 'fim.after_or_equal' => 'O encerramento deve ser depois da abertura.']);

        $dados['descricao'] = \App\Support\HtmlSeguro::limpar($dados['descricao'] ?? null);
        $dados['tipos_aceitos'] = array_values($dados['tipos_aceitos'] ?? array_keys(ChamadaProposta::TIPOS));
        $dados['ativa'] = $request->boolean('ativa', true);
        $dados['max_por_usuario'] = $dados['max_por_usuario'] ?? 3;

        return $dados;
    }
}
