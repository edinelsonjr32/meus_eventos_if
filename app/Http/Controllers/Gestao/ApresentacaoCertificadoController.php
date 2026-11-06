<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\Atividade;
use App\Models\CertificadoTrabalho;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Services\ApresentacaoService;
use App\Services\CertificadoTrabalhoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Apresentação dos trabalhos e emissão de certificados (gestor, dono e co-administradores). */
class ApresentacaoCertificadoController extends Controller
{
    public function __construct(private ApresentacaoService $apresentacao, private CertificadoTrabalhoService $certificados)
    {
    }

    public function index(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $query = $evento->trabalhos()->where('status', 'aprovado')->with(['autores', 'sessao', 'certificados', 'evento.trabalhoConfig']);

        match ($request->input('apresentado')) {
            'sim' => $query->where('apresentado', true),
            'nao' => $query->where('apresentado', false),
            default => null,
        };
        if ($busca = trim((string) $request->input('q'))) {
            $like = '%' . addcslashes($busca, '%_\\') . '%';
            $query->where(fn ($q) => $q->where('titulo', 'like', $like)->orWhere('codigo', 'like', $like)
                ->orWhereHas('autores', fn ($a) => $a->where('nome_completo', 'like', $like)));
        }

        $trabalhos = $query->orderBy('codigo')->paginate(30)->withQueryString();
        $trabalhos->getCollection()->each(fn ($t) => $t->motivo = $this->certificados->motivoInelegivel($t));

        $totais = $evento->trabalhos()->where('status', 'aprovado')->selectRaw('count(*) as aprovados, sum(apresentado) as apresentados')->first();

        return view('gestao.trabalhos.certificados', [
            'evento' => $evento,
            'trabalhos' => $trabalhos,
            'atividades' => $evento->atividades()->orderBy('data_inicio')->get(),
            'config' => $evento->trabalhoConfig,
            'aprovados' => (int) $totais->aprovados,
            'apresentados' => (int) $totais->apresentados,
        ]);
    }

    /** Ações em lote: marcar/desmarcar apresentado, definir sessão, emitir, sincronizar com a frequência. */
    public function lote(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $dados = $request->validate([
            'acao' => ['required', Rule::in(['apresentado', 'nao_apresentado', 'sessao', 'emitir', 'emitir_todos', 'sincronizar'])],
            'trabalhos' => ['nullable', 'array'],
            'trabalhos.*' => ['integer'],
            'atividade_id' => ['nullable', 'integer'],
        ]);

        $acao = $dados['acao'];
        $ok = 0;
        $erros = [];

        if ($acao === 'sincronizar') {
            $ok = $this->apresentacao->sincronizarEvento($evento);

            return back()->with('success', "{$ok} apresentação(ões) confirmada(s) pela frequência.");
        }

        if ($acao === 'emitir_todos') {
            [$ok, $erros] = $this->certificados->emitirEvento($evento, $request->user());

            return back()->with($ok ? 'success' : 'error', $ok ? "{$ok} certificado(s) emitido(s)." : 'Nenhum certificado novo foi emitido.')->with('erros_lote', $erros);
        }

        if (empty($dados['trabalhos'])) {
            return back()->with('error', 'Selecione ao menos um trabalho.');
        }

        $atividade = null;
        if ($acao === 'sessao' && !empty($dados['atividade_id'])) {
            $atividade = Atividade::where('evento_id', $evento->id)->find($dados['atividade_id']);
            if (!$atividade) {
                return back()->with('error', 'Sessão inválida para este evento.');
            }
        }

        // Somente trabalhos deste evento (ignora ids de outros eventos)
        foreach ($evento->trabalhos()->whereIn('id', $dados['trabalhos'])->with('evento.trabalhoConfig')->get() as $trabalho) {
            try {
                match ($acao) {
                    'apresentado' => $this->apresentacao->marcar($trabalho, true, $request->user()),
                    'nao_apresentado' => $this->apresentacao->marcar($trabalho, false, $request->user()),
                    'sessao' => $this->apresentacao->definirSessao($trabalho, $atividade),
                    'emitir' => $this->certificados->emitir($trabalho, $request->user()),
                };
                $ok++;
            } catch (\DomainException $e) {
                $erros[] = $e->getMessage();
            }
        }

        return back()->with($ok ? 'success' : 'error', $ok ? "{$ok} trabalho(s) atualizado(s)." : 'Nenhuma alteração realizada.')->with('erros_lote', $erros);
    }

    public function apresentacao(Request $request, Trabalho $trabalho)
    {
        $this->authorize('gerirTrabalhos', $trabalho->evento);

        $dados = $request->validate([
            'acao' => ['required', Rule::in(['marcar', 'desmarcar', 'sessao'])],
            'atividade_id' => ['nullable', 'integer'],
        ]);

        try {
            match ($dados['acao']) {
                'marcar' => $this->apresentacao->marcar($trabalho, true, $request->user()),
                'desmarcar' => $this->apresentacao->marcar($trabalho, false, $request->user()),
                'sessao' => $this->apresentacao->definirSessao($trabalho, !empty($dados['atividade_id']) ? Atividade::find($dados['atividade_id']) : null),
            };
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Apresentação atualizada.');
    }

    public function emitir(Request $request, Trabalho $trabalho)
    {
        $this->authorize('gerirTrabalhos', $trabalho->evento);

        try {
            $novos = $this->certificados->emitir($trabalho, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $novos->isEmpty() ? 'Todos os certificados deste trabalho já foram emitidos.' : "{$novos->count()} certificado(s) emitido(s) e enviado(s) por e-mail.");
    }

    public function revogar(Request $request, CertificadoTrabalho $certificado)
    {
        $this->authorize('gerirTrabalhos', $certificado->trabalho->evento);

        $dados = $request->validate(['motivo' => ['required', 'string', 'max:250']], ['motivo.required' => 'Informe o motivo da revogação.']);

        try {
            $this->certificados->revogar($certificado, $request->user(), $dados['motivo']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Certificado revogado.');
    }
}
