<?php

namespace App\Http\Controllers\Avaliador;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalvarAvaliacaoRequest;
use App\Models\TrabalhoAvaliacao;
use App\Services\AvaliacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Área do avaliador: vê somente as próprias avaliações e os trabalhos atribuídos.
 * Com avaliação cega, a identificação dos autores nunca é carregada nas telas.
 */
class AvaliacaoController extends Controller
{
    public function __construct(private AvaliacaoService $servico)
    {
    }

    public function index(Request $request)
    {
        $avaliacoes = TrabalhoAvaliacao::with([
            'trabalho' => fn ($q) => $q->withMax('avaliacoes', 'rodada'),   // rodada vigente sem consulta por item
            'trabalho.evento.trabalhoConfig',
        ])
            ->where('avaliador_id', $request->user()->id)
            ->where('status', '!=', 'recusada')
            ->latest('id')
            ->get()
            // só a rodada vigente de cada trabalho
            ->filter(fn ($a) => $a->rodada === $a->trabalho->rodadaAtual());

        // Atribuições feitas na triagem: o avaliador só as vê depois que a gestão encaminha o trabalho para avaliação
        $aguardando = TrabalhoAvaliacao::with(['trabalho.evento'])
            ->where('avaliador_id', $request->user()->id)->where('status', 'pendente')
            ->whereHas('trabalho', fn ($q) => $q->whereIn('status', ['submetido', 'em_triagem', 'resubmetido']))
            ->latest('id')->get();

        return view('avaliador.index', [
            'aguardando' => $aguardando,
            'abertas' => $avaliacoes->filter(fn ($a) => in_array($a->status, ['pendente', 'em_andamento']) && $a->trabalho->status === 'em_avaliacao'),
            'concluidas' => $avaliacoes->filter(fn ($a) => $a->status === 'concluida'),
        ]);
    }

    public function show(Request $request, TrabalhoAvaliacao $avaliacao)
    {
        $this->authorize('view', $avaliacao);

        $avaliacao->load(['notas', 'trabalho.evento.trabalhoConfig', 'trabalho.area']);
        $trabalho = $avaliacao->trabalho;
        $cega = (bool) $trabalho->evento->trabalhoConfig?->avaliacao_cega;

        // Avaliação cega: os autores só são carregados quando a identificação é permitida
        $autores = $cega ? collect() : $trabalho->autores;

        return view('avaliador.avaliar', [
            'avaliacao' => $avaliacao,
            'trabalho' => $trabalho,
            'config' => $trabalho->evento->trabalhoConfig,
            'criterios' => $this->servico->criteriosAtivos($trabalho->evento),
            'notas' => $avaliacao->notas->keyBy('criterio_id'),
            'autores' => $autores,
            'cega' => $cega,
            'editavel' => $request->user()?->can('avaliar', $avaliacao) ?? false,
        ]);
    }

    public function salvar(SalvarAvaliacaoRequest $request, TrabalhoAvaliacao $avaliacao)
    {
        $this->authorize('avaliar', $avaliacao);

        try {
            $this->servico->salvar($avaliacao, $request->validated());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rascunho da avaliação salvo.');
    }

    public function concluir(SalvarAvaliacaoRequest $request, TrabalhoAvaliacao $avaliacao)
    {
        $this->authorize('avaliar', $avaliacao);

        try {
            $this->servico->concluir($avaliacao, $request->user(), $request->validated());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('avaliador.avaliacoes.index')->with('success', 'Avaliação concluída. Obrigado!');
    }

    public function recusar(Request $request, TrabalhoAvaliacao $avaliacao)
    {
        $this->authorize('avaliar', $avaliacao);

        $dados = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:1000']], ['motivo.required' => 'Informe o motivo da recusa.']);

        try {
            $this->servico->recusar($avaliacao, $request->user(), $dados['motivo']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('avaliador.avaliacoes.index')->with('success', 'Avaliação recusada. A gestão foi informada.');
    }

    public function arquivo(TrabalhoAvaliacao $avaliacao)
    {
        $this->authorize('view', $avaliacao);
        abort_if($avaliacao->status === 'recusada', 403);

        $arquivo = $avaliacao->trabalho->arquivoAtual;
        abort_unless($arquivo && Storage::disk('trabalhos')->exists($arquivo->path), 404, 'Arquivo não encontrado.');

        // Nome neutro: nunca inclui título nem autores
        return Storage::disk('trabalhos')->response($arquivo->path, "trabalho-{$avaliacao->trabalho->codigo}.pdf", [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
