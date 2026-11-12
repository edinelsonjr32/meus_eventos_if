<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChamadaVoluntario;
use App\Models\DemandaVoluntario;
use App\Models\Evento;
use App\Models\Voluntario;
use App\Services\VoluntarioService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Gestão de chamadas, inscritos, atividades (demandas) e certificados dos voluntários de um evento. */
class VoluntarioGestaoController extends Controller
{
    public function __construct(private VoluntarioService $service)
    {
    }

    public function index(Request $request, Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $status = $request->input('status');
        $voluntarios = Voluntario::with(['usuario', 'participante', 'chamada', 'demandas'])->where('evento_id', $evento->id)
            ->when($status && isset(Voluntario::STATUS[$status]), fn ($q) => $q->where('status', $status))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('usuario', fn ($u) => $u->where('name', 'like', '%' . $request->q . '%')->orWhere('email', 'like', '%' . $request->q . '%')))
            ->orderByRaw("FIELD(status, 'inscrito', 'aprovado', 'lista_espera', 'reprovado', 'desistente')")->latest()->get();

        $certificados = \App\Models\Equipe::where('evento_id', $evento->id)->where('funcao', 'Voluntário')->get()->keyBy('participante_id');

        return view('admin.voluntarios.index', [
            'evento' => $evento,
            'chamadas' => $evento->chamadasVoluntarios()->withCount('voluntarios')->latest()->get(),
            'voluntarios' => $voluntarios,
            'demandas' => $evento->demandasVoluntarios()->withCount('voluntarios')->with('voluntarios.usuario')->orderBy('data_inicio')->get(),
            'aprovados' => Voluntario::with('usuario')->where('evento_id', $evento->id)->where('status', 'aprovado')->get()->sortBy(fn ($v) => $v->usuario->name)->values(),
            'locais' => $evento->locais()->where('ativo', true)->get(),
            'atividades' => $evento->atividades()->orderBy('data_inicio')->get(),
            'contagem' => Voluntario::where('evento_id', $evento->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'status' => $status, 'certificados' => $certificados,
        ]);
    }

    // ---------- Chamadas ----------
    public function storeChamada(Request $request, Evento $evento)
    {
        $this->authorize('gerenciar', $evento);
        $evento->chamadasVoluntarios()->create($this->validarChamada($request) + ['criado_por' => $request->user()->id]);

        return back()->with('aba', 'chamadas')->with('success', 'Chamada de voluntários criada.');
    }

    public function updateChamada(Request $request, Evento $evento, ChamadaVoluntario $chamada)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($chamada->evento_id === $evento->id, 404);
        $chamada->update($this->validarChamada($request));

        return back()->with('aba', 'chamadas')->with('success', 'Chamada atualizada.');
    }

    public function destroyChamada(Evento $evento, ChamadaVoluntario $chamada)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($chamada->evento_id === $evento->id, 404);
        if ($chamada->voluntarios()->exists()) {
            return back()->with('aba', 'chamadas')->with('error', 'Esta chamada já tem inscritos. Desative-a em vez de excluir.');
        }
        $chamada->delete();

        return back()->with('aba', 'chamadas')->with('success', 'Chamada excluída.');
    }

    // ---------- Seleção ----------
    public function decisao(Request $request, Evento $evento, Voluntario $voluntario)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($voluntario->evento_id === $evento->id, 404);

        $dados = $request->validate(['status' => ['required', Rule::in(['aprovado', 'lista_espera', 'reprovado', 'inscrito'])], 'parecer' => 'nullable|string|max:3000']);

        try {
            $this->service->decidir($voluntario, $request->user(), $dados['status'], $dados['parecer'] ?? null);
        } catch (\DomainException $e) {
            return back()->with('abrir', $voluntario->id)->with('aba', request('aba', 'inscritos'))->with('error', $e->getMessage());
        }

        return back()->with('abrir', $voluntario->id)->with('aba', request('aba', 'inscritos'))->with('success', 'Situação do voluntário atualizada.');
    }

    // ---------- Demandas (atividades dos voluntários) ----------
    public function storeDemanda(Request $request, Evento $evento)
    {
        $this->authorize('gerenciar', $evento);
        $evento->demandasVoluntarios()->create($this->validarDemanda($request, $evento));

        return back()->with('aba', 'atividades')->with('success', 'Atividade de voluntários criada.');
    }

    public function updateDemanda(Request $request, Evento $evento, DemandaVoluntario $demanda)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($demanda->evento_id === $evento->id, 404);
        $demanda->update($this->validarDemanda($request, $evento));

        return back()->with('aba', 'atividades')->with('success', 'Atividade atualizada.');
    }

    public function destroyDemanda(Evento $evento, DemandaVoluntario $demanda)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($demanda->evento_id === $evento->id, 404);
        $demanda->delete();

        return back()->with('aba', 'atividades')->with('success', 'Atividade excluída (os voluntários foram desvinculados).');
    }

    public function alocar(Request $request, Evento $evento, Voluntario $voluntario)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($voluntario->evento_id === $evento->id, 404);

        $dados = $request->validate(['demanda_id' => ['required', Rule::exists('demandas_voluntarios', 'id')->where('evento_id', $evento->id)], 'ignorar_conflito' => 'nullable|boolean']);

        try {
            $this->service->alocar($voluntario, DemandaVoluntario::findOrFail($dados['demanda_id']), $request->boolean('ignorar_conflito'));
        } catch (\DomainException $e) {
            return back()->with('abrir', $voluntario->id)->with('aba', request('aba', 'inscritos'))->with('error', $e->getMessage());
        }

        return back()->with('abrir', $voluntario->id)->with('aba', request('aba', 'inscritos'))->with('success', 'Voluntário vinculado à atividade.');
    }

    /** Cria a demanda de voluntários das atividades do evento que ainda não têm uma (as novas já nascem com ela). */
    public function gerarDemandas(Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $criadas = $evento->atividades()->get()->filter(fn ($a) => DemandaVoluntario::criarPara($a))->count();

        return back()->with('aba', 'atividades')->with($criadas ? 'success' : 'error', $criadas ? "{$criadas} demanda(s) de voluntários criada(s) a partir das atividades do evento." : 'Todas as atividades do evento já têm demanda de voluntários.');
    }

    /** Vincula um voluntário aprovado a uma atividade, a partir do cartão da própria atividade. */
    public function alocarNaDemanda(Request $request, Evento $evento, DemandaVoluntario $demanda)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($demanda->evento_id === $evento->id, 404);

        $dados = $request->validate(['voluntario_id' => ['required', Rule::exists('voluntarios', 'id')->where('evento_id', $evento->id)], 'ignorar_conflito' => 'nullable|boolean'], ['voluntario_id.required' => 'Escolha o voluntário.']);

        try {
            $this->service->alocar(Voluntario::findOrFail($dados['voluntario_id']), $demanda, $request->boolean('ignorar_conflito'));
        } catch (\DomainException $e) {
            return back()->with('aba', 'atividades')->with('error', $e->getMessage());
        }

        return back()->with('aba', 'atividades')->with('success', 'Voluntário vinculado à atividade.');
    }

    public function desalocar(Evento $evento, Voluntario $voluntario, DemandaVoluntario $demanda)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($voluntario->evento_id === $evento->id && $demanda->evento_id === $evento->id, 404);
        $this->service->desalocar($voluntario, $demanda);

        return back()->with('abrir', $voluntario->id)->with('aba', request('aba', 'inscritos'))->with('success', 'Voluntário removido da atividade.');
    }

    // ---------- Certificado ----------
    public function certificado(Request $request, Evento $evento, Voluntario $voluntario)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($voluntario->evento_id === $evento->id, 404);

        $request->validate(['carga_horaria' => 'nullable|integer|min:1|max:1000']);

        try {
            $this->service->emitirCertificado($voluntario, $request->user(), $request->filled('carga_horaria') ? (int) $request->carga_horaria : null);
        } catch (\DomainException $e) {
            return back()->with('abrir', $voluntario->id)->with('aba', request('aba', 'inscritos'))->with('error', $e->getMessage());
        }

        return back()->with('abrir', $voluntario->id)->with('aba', request('aba', 'inscritos'))->with('success', 'Certificado de voluntário gerado.');
    }

    private function validarChamada(Request $request): array
    {
        $dados = $request->validate([
            'titulo' => 'required|string|max:255', 'descricao' => 'nullable|string|max:20000', 'requisitos' => 'nullable|string|max:3000',
            'vagas' => 'nullable|integer|min:1|max:2000', 'inicio' => 'nullable|date', 'fim' => 'nullable|date|after_or_equal:inicio', 'ativa' => 'nullable|boolean',
        ], ['titulo.required' => 'Informe o título da chamada.', 'fim.after_or_equal' => 'O encerramento deve ser depois da abertura.']);

        $dados['descricao'] = filled($dados['descricao'] ?? null) ? trim(strip_tags($dados['descricao'])) : null;
        $dados['ativa'] = $request->boolean('ativa', true);

        return $dados;
    }

    private function validarDemanda(Request $request, Evento $evento): array
    {
        $dados = $request->validate([
            'titulo' => 'required|string|max:255', 'descricao' => 'nullable|string|max:3000',
            'atividade_id' => ['nullable', Rule::exists('atividades', 'id')->where('evento_id', $evento->id)],
            'data_inicio' => 'nullable|date', 'data_fim' => 'nullable|date|after:data_inicio',
            'local_id' => ['nullable', 'integer', Rule::exists('locais', 'id')->where('evento_id', $evento->id)], 'vagas' => 'nullable|integer|min:1|max:500', 'carga_horaria' => 'required|numeric|min:0.5|max:200',
            'responsavel_nome' => 'nullable|string|max:255', 'responsavel_contato' => 'nullable|string|max:255',
        ], ['titulo.required' => 'Informe o nome da atividade.', 'data_fim.after' => 'O término deve ser depois do início.', 'carga_horaria.required' => 'Informe a carga horária.']);

        // o texto do local acompanha o cadastro (preenchido pelo modelo quando há local_id)
        $dados['local'] = null;

        return $dados;
    }
}
