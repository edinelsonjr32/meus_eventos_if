<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarTrabalhoRequest;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Services\TrabalhoWorkflow;
use App\Support\FormularioTrabalho;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Área do autor: submeter, editar, acompanhar e baixar o próprio trabalho.
 */
class TrabalhoController extends Controller
{
    public function __construct(private TrabalhoWorkflow $workflow)
    {
    }

    public function index(Request $request)
    {
        $trabalhos = Trabalho::with(['evento.trabalhoConfig', 'autores', 'certificados', 'premiacao'])
            ->doAutor($request->user())
            ->latest()
            ->get();

        // Eventos com chamada de trabalhos ativa (aberta, ainda não iniciada ou encerrada)
        $eventos = Evento::whereHas('trabalhoConfig', fn ($q) => $q->where('ativo', true))
            ->with('trabalhoConfig')
            ->orderBy('data_inicio')
            ->get();

        // Quantos trabalhos (não retirados) o usuário já tem em cada evento, para aplicar o limite por usuário
        // (somente os que EU submeti: coautores não contam no limite)
        $meusPorEvento = $trabalhos->where('submetido_por', $request->user()->id)->where('status', '!=', Trabalho::RETIRADO)->groupBy('evento_id')->map->count();

        return view('trabalhos.index', compact('trabalhos', 'eventos', 'meusPorEvento'));
    }

    public function create(Request $request, string $slug)
    {
        $evento = Evento::where('slug', $slug)->firstOrFail();

        if ($erro = $this->bloqueioDeNovaSubmissao($evento, $request)) {
            return redirect()->route('evento.publico.show', $evento->slug)->with('error', $erro);
        }

        return view('trabalhos.form', $this->dadosDoFormulario($evento, new Trabalho(['status' => Trabalho::RASCUNHO]), $request));
    }

    public function store(SalvarTrabalhoRequest $request, string $slug)
    {
        $evento = $request->evento();

        if ($erro = $this->bloqueioDeNovaSubmissao($evento, $request)) {
            return redirect()->route('evento.publico.show', $evento->slug)->with('error', $erro);
        }

        $trabalho = $this->workflow->salvar($evento, $request->user(), $request->validated(), null, $request->file('arquivo'));

        return $this->concluir($request, $trabalho, 'Rascunho salvo. Você pode continuar depois em "Meus Trabalhos".');
    }

    public function edit(Request $request, Trabalho $trabalho)
    {
        $this->authorize('update', $trabalho);

        if ($erro = $this->bloqueioDeEdicao($trabalho)) {
            return redirect()->route('trabalhos.show', $trabalho)->with('error', $erro);
        }

        return view('trabalhos.form', $this->dadosDoFormulario($trabalho->evento, $trabalho->load(['autores', 'arquivoAtual']), $request));
    }

    public function update(SalvarTrabalhoRequest $request, Trabalho $trabalho)
    {
        $this->authorize('update', $trabalho);

        if ($erro = $this->bloqueioDeEdicao($trabalho)) {
            return redirect()->route('trabalhos.show', $trabalho)->with('error', $erro);
        }

        $trabalho = $this->workflow->salvar($trabalho->evento, $request->user(), $request->validated(), $trabalho, $request->file('arquivo'));

        return $this->concluir($request, $trabalho, 'Alterações salvas.');
    }

    public function show(Trabalho $trabalho)
    {
        $this->authorize('view', $trabalho);

        $trabalho->load(['evento.trabalhoConfig', 'autores', 'arquivos', 'historico', 'area', 'certificados.autor', 'avaliacoes' => fn ($q) => $q->select(['id', 'trabalho_id', 'status', 'parecer', 'nota_final', 'rodada'])]);

        return view('trabalhos.show', compact('trabalho'));
    }

    public function arquivo(Trabalho $trabalho)
    {
        $this->authorize('baixarArquivo', $trabalho);

        $arquivo = $trabalho->arquivoAtual;
        abort_unless($arquivo && Storage::disk('trabalhos')->exists($arquivo->path), 404, 'Arquivo não encontrado.');

        $nome = ($trabalho->codigo ?: "rascunho-{$trabalho->id}") . '.pdf';

        return Storage::disk('trabalhos')->response($arquivo->path, $nome, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * O autor autoriza (ou revoga) a publicação do trabalho nos Anais mesmo depois da aprovação: sem essa
     * autorização o trabalho concluído não aparece nos Anais. Só autores consentem; a gestão apenas solicita.
     */
    public function autorizarPublicacao(Request $request, Trabalho $trabalho)
    {
        abort_unless($trabalho->ehAutor($request->user()), 403);
        abort_unless(in_array($trabalho->status, ['aprovado', 'aguardando_correcao', 'avaliado', 'resubmetido'], true), 422, 'A autorização só pode ser alterada em trabalhos já avaliados.');

        $autoriza = $request->boolean('autoriza');
        $trabalho->update(['autoriza_publicacao' => $autoriza, 'autorizou_em' => $autoriza ? now() : null]);
        $this->workflow->registrar($trabalho, $request->user(), $trabalho->status, $trabalho->status, $autoriza ? 'Publicação nos Anais autorizada pelo autor' : 'Autorização de publicação nos Anais revogada pelo autor');

        return back()->with('success', $autoriza ? 'Publicação autorizada. O trabalho aparece nos Anais quando estiverem publicados.' : 'Autorização revogada: o trabalho não será publicado nos Anais.');
    }

    public function retirar(Request $request, Trabalho $trabalho)
    {
        $this->authorize('retirar', $trabalho);

        $this->workflow->retirar($trabalho, $request->user(), $request->input('motivo'));

        return redirect()->route('trabalhos.index')->with('success', 'Trabalho retirado.');
    }

    /** Salva como rascunho ou conclui o envio, conforme o botão usado. */
    private function concluir(SalvarTrabalhoRequest $request, Trabalho $trabalho, string $mensagemRascunho)
    {
        if ($request->submeter()) {
            $trabalho = $this->workflow->submeter($trabalho, $request->user());

            return redirect()->route('trabalhos.show', $trabalho)
                ->with('success', "Trabalho submetido com sucesso! Protocolo {$trabalho->codigo}. Enviamos a confirmação por e-mail aos autores.");
        }

        return redirect()->route('trabalhos.edit', $trabalho)->with('success', $mensagemRascunho);
    }

    private function bloqueioDeNovaSubmissao(Evento $evento, Request $request): ?string
    {
        $config = $evento->trabalhoConfig;

        if (!$config || !$config->estaAberta()) {
            return 'A submissão de trabalhos não está aberta para este evento.';
        }

        if ($config->max_trabalhos_por_usuario) {
            $total = Trabalho::where('evento_id', $evento->id)
                ->where('submetido_por', $request->user()->id)
                ->where('status', '!=', Trabalho::RETIRADO)
                ->count();

            if ($total >= $config->max_trabalhos_por_usuario) {
                return "Você atingiu o limite de {$config->max_trabalhos_por_usuario} trabalho(s) neste evento.";
            }
        }

        return null;
    }

    private function bloqueioDeEdicao(Trabalho $trabalho): ?string
    {
        // Devoluções e correções solicitadas pela gestão podem ser enviadas mesmo após o fim da chamada
        if ($trabalho->status === Trabalho::AGUARDANDO_CORRECAO) {
            return $trabalho->prazo_correcao && $trabalho->prazo_correcao->isPast()
                ? 'O prazo de correção terminou em ' . $trabalho->prazo_correcao->format('d/m/Y H:i') . '. Procure a gestão do evento.'
                : null;
        }

        if ($trabalho->status === Trabalho::DEVOLVIDO_TRIAGEM) {
            return null;
        }

        return optional($trabalho->evento->trabalhoConfig)->estaAberta()
            ? null
            : 'O período de submissão deste evento foi encerrado.';
    }

    private function dadosDoFormulario(Evento $evento, Trabalho $trabalho, Request $request): array
    {
        return FormularioTrabalho::dados($evento, $trabalho, $request->user());
    }
}
