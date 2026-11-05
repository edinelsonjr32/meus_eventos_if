<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarTrabalhoRequest;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Services\TrabalhoWorkflow;
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
        $trabalhos = Trabalho::with(['evento', 'autores'])
            ->where('submetido_por', $request->user()->id)
            ->latest()
            ->get();

        return view('trabalhos.index', compact('trabalhos'));
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

        $trabalho->load(['evento.trabalhoConfig', 'autores', 'arquivos', 'historico.usuario', 'area']);

        return view('trabalhos.show', compact('trabalho'));
    }

    public function arquivo(Trabalho $trabalho)
    {
        $this->authorize('view', $trabalho);

        $arquivo = $trabalho->arquivoAtual;
        abort_unless($arquivo && Storage::disk('trabalhos')->exists($arquivo->path), 404, 'Arquivo não encontrado.');

        $nome = ($trabalho->codigo ?: "rascunho-{$trabalho->id}") . '.pdf';

        return Storage::disk('trabalhos')->response($arquivo->path, $nome, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
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
        if (in_array($trabalho->status, [Trabalho::AGUARDANDO_CORRECAO, Trabalho::DEVOLVIDO_TRIAGEM], true)) {
            return null;
        }

        return optional($trabalho->evento->trabalhoConfig)->estaAberta()
            ? null
            : 'O período de submissão deste evento foi encerrado.';
    }

    private function dadosDoFormulario(Evento $evento, Trabalho $trabalho, Request $request): array
    {
        $config = $evento->trabalhoConfig;

        // Autor principal pré-preenchido com os dados de quem está submetendo
        $autores = old('autores');
        if ($autores === null) {
            $autores = $trabalho->exists
                ? $trabalho->autores->map->only(['nome_completo', 'cpf', 'email', 'telefone', 'instituicao', 'vinculo', 'titulacao', 'lattes', 'orcid', 'tipo_autor', 'apresentador', 'correspondente'])->all()
                : [];
        }
        if (empty($autores)) {
            $participante = $request->user()->participante;
            $autores = [[
                'nome_completo' => $request->user()->name, 'cpf' => $participante?->cpf, 'email' => $request->user()->email,
                'vinculo' => $participante?->tipo_vinculo, 'tipo_autor' => 'autor_principal', 'apresentador' => true, 'correspondente' => true,
            ]];
        }

        return [
            'evento' => $evento,
            'trabalho' => $trabalho,
            'config' => $config,
            'areas' => $evento->areasTematicas,
            'autores' => array_values($autores),
        ];
    }
}
