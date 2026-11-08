<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\CertificadoPremiacao;
use App\Models\Evento;
use App\Models\Premiacao;
use App\Services\PremiacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Premiação por categoria (1º a 3º), certificados para impressão e divulgação aos autores. */
class PremiacaoGestaoController extends Controller
{
    public function __construct(private PremiacaoService $servico)
    {
    }

    public function index(Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);
        abort_unless($evento->trabalhoConfig, 404, 'Crie a chamada de trabalhos antes de configurar a premiação.');

        $categorias = $this->servico->categorias($evento);
        $rankings = collect($categorias)->map(fn ($rotulo, $tipo) => $this->servico->ranking($evento, $tipo));

        return view('gestao.trabalhos.premiacao', [
            'evento' => $evento,
            'config' => $evento->trabalhoConfig,
            'categorias' => $categorias,
            'rankings' => $rankings,
            'atuais' => $this->servico->atuais($evento),
            'sugestao' => $this->servico->sugestao($evento),
            'divulgada' => $this->servico->divulgada($evento),
            'comApresentacao' => (bool) $evento->trabalhoConfig->avaliacao_apresentacao_ativa,
        ]);
    }

    public function salvar(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $dados = $request->validate(['escolhas' => ['nullable', 'array'], 'escolhas.*' => ['nullable', 'array'], 'escolhas.*.*' => ['nullable', 'integer']]);

        try {
            $premiacoes = $this->servico->salvar($evento, $dados['escolhas'] ?? [], $request->user());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Premiação salva ({$premiacoes->count()} colocação(ões)). Os certificados já estão disponíveis para impressão.");
    }

    public function sugerir(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        try {
            $premiacoes = $this->servico->aplicarSugestao($evento, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $premiacoes->isEmpty()
            ? back()->with('warning', 'Nenhum trabalho elegível ainda: só concorrem trabalhos aprovados nas duas partes e com nota geral.')
            : back()->with('success', 'Premiação apurada pelo ranking (nota geral). Confira, ajuste se preciso e divulgue.');
    }

    public function divulgar(Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        if ($evento->premiacoes()->doesntExist()) {
            return back()->with('error', 'Apure a premiação antes de divulgar.');
        }
        $this->servico->divulgar($evento);

        return back()->with('success', 'Premiação divulgada: os autores premiados passam a ver o certificado em seu perfil.');
    }

    public function ocultar(Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);
        $this->servico->ocultar($evento);

        return back()->with('success', 'Premiação ocultada: o certificado volta a ser visível só para a gestão.');
    }

    public function modelo(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);
        abort_unless($evento->trabalhoConfig, 404);

        $dados = $request->validate([
            'cert_premio_cabecalho' => ['nullable', 'string', 'max:1000'],
            'cert_premio_corpo' => ['nullable', 'string', 'max:5000'],
            'cert_premio_rodape' => ['nullable', 'string', 'max:1000'],
        ]);
        $evento->trabalhoConfig->update($dados);

        return back()->with('success', 'Modelo do certificado de premiação salvo.');
    }

    /** Imprime os certificados: todos do evento, de uma categoria (tipo) ou de uma colocação (premiacao). */
    public function imprimir(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $certificados = CertificadoPremiacao::query()->whereNull('revogado_em')
            ->whereHas('premiacao', function ($q) use ($evento, $request) {
                $q->where('evento_id', $evento->id)
                    ->when($request->filled('tipo'), fn ($w) => $w->where('tipo_trabalho', $request->input('tipo')))
                    ->when($request->filled('premiacao'), fn ($w) => $w->whereKey((int) $request->input('premiacao')));
            })
            ->with(['premiacao', 'autor'])->get()
            ->sortBy(fn ($c) => [$c->premiacao->tipo_trabalho, $c->premiacao->colocacao, $c->autor->ordem ?? 0, $c->id])->values();

        abort_if($certificados->isEmpty(), 404, 'Nenhum certificado de premiação para imprimir.');

        return $this->servico->gerarPdf($certificados)->stream('Premiacao_' . Str::slug($evento->titulo) . '.pdf');
    }

    /** Certificado individual (autor premiado), para a gestão. */
    public function certificado(CertificadoPremiacao $certificado)
    {
        $evento = $certificado->premiacao->evento;
        $this->authorize('gerirTrabalhos', $evento);
        abort_if($certificado->revogado(), 410, 'Este certificado foi revogado.');

        return $this->servico->gerarPdf(collect([$certificado]))->stream('Premiacao_' . Str::slug($certificado->autor->nome_completo) . '.pdf');
    }
}
