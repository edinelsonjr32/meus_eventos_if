<?php

namespace App\Http\Controllers\Avaliador;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalvarApresentacaoRequest;
use App\Models\ApresentacaoAvaliacao;
use App\Services\AvaliacaoApresentacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Área do avaliador para as apresentações (feira de trabalhos): vê só as próprias avaliações. */
class ApresentacaoAvaliacaoController extends Controller
{
    public function __construct(private AvaliacaoApresentacaoService $servico)
    {
    }

    public function index(Request $request)
    {
        $todas = ApresentacaoAvaliacao::with(['trabalho.evento.trabalhoConfig', 'trabalho.sessao'])
            ->where('avaliador_id', $request->user()->id)->where('status', '!=', 'recusada')
            ->get()
            ->sortBy(fn ($a) => $a->trabalho->sessao?->data_inicio?->timestamp ?? PHP_INT_MAX);

        // Atribuídas, mas ainda indisponíveis (avaliação das apresentações desativada no evento)
        $aguardando = $todas->filter(fn ($a) => $a->aberta() && !$this->servico->editavel($a));

        return view('avaliador.apresentacoes', [
            'aguardando' => $aguardando,
            'abertas' => $todas->filter(fn ($a) => $a->aberta() && $this->servico->editavel($a)),
            'concluidas' => $todas->filter(fn ($a) => $a->status === 'concluida'),
        ]);
    }

    public function show(Request $request, ApresentacaoAvaliacao $avaliacao)
    {
        $this->authorize('view', $avaliacao);

        $avaliacao->load(['notas', 'trabalho.evento.trabalhoConfig', 'trabalho.area', 'trabalho.sessao']);
        $trabalho = $avaliacao->trabalho;
        $cega = (bool) $trabalho->evento->trabalhoConfig?->avaliacao_cega;

        return view('avaliador.apresentacao-avaliar', [
            'avaliacao' => $avaliacao,
            'trabalho' => $trabalho,
            'criterios' => $this->servico->criteriosAtivos($trabalho->evento),
            'notas' => $avaliacao->notas->keyBy('criterio_id'),
            'autores' => $cega ? collect() : $trabalho->autores,
            'cega' => $cega,
            'editavel' => $request->user()->can('avaliar', $avaliacao),
        ]);
    }

    public function salvar(SalvarApresentacaoRequest $request, ApresentacaoAvaliacao $avaliacao)
    {
        $this->authorize('avaliar', $avaliacao);
        try {
            $this->servico->salvar($avaliacao, $request->validated());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rascunho salvo.');
    }

    public function concluir(SalvarApresentacaoRequest $request, ApresentacaoAvaliacao $avaliacao)
    {
        $this->authorize('avaliar', $avaliacao);
        try {
            $this->servico->concluir($avaliacao, $request->validated());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('avaliador.apresentacoes.index')->with('success', 'Avaliação da apresentação concluída. Obrigado!');
    }

    public function recusar(Request $request, ApresentacaoAvaliacao $avaliacao)
    {
        $this->authorize('avaliar', $avaliacao);
        $dados = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:1000']], ['motivo.required' => 'Informe o motivo da recusa.']);
        try {
            $this->servico->recusar($avaliacao, $dados['motivo']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('avaliador.apresentacoes.index')->with('success', 'Avaliação recusada. A gestão foi informada.');
    }

    public function arquivo(ApresentacaoAvaliacao $avaliacao)
    {
        $this->authorize('view', $avaliacao);
        abort_if($avaliacao->status === 'recusada', 403);

        $arquivo = $avaliacao->trabalho->arquivoAtual;
        abort_unless($arquivo && Storage::disk('trabalhos')->exists($arquivo->path), 404, 'Arquivo não encontrado.');

        return Storage::disk('trabalhos')->response($arquivo->path, "trabalho-{$avaliacao->trabalho->codigo}.pdf", [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
