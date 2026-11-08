<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Anais;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use App\Services\AnaisPdfService;
use App\Services\CitacaoAbnt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Anais públicos: somente trabalhos aprovados, apresentados e autorizados pelos autores,
 * e somente depois que a gestão publica a edição.
 */
class AnaisController extends Controller
{
    public function __construct(private AnaisPdfService $pdf, private CitacaoAbnt $citacao)
    {
    }

    /** @return array{0:Evento,1:Anais} */
    private function publicados(string $slug): array
    {
        $evento = Evento::where('slug', $slug)->firstOrFail();
        $anais = $evento->anais;
        abort_unless($anais && $anais->publicado(), 404);

        return [$evento, $anais];
    }

    private function trabalho(Evento $evento, string $codigo): Trabalho
    {
        return Anais::elegiveis($evento)
            ->with(['autores', 'area', 'arquivoAnais', 'arquivoAtual', 'evento.trabalhoConfig', 'certificados'])
            ->where('codigo', $codigo)
            ->firstOrFail();
    }

    public function index(Request $request, string $slug)
    {
        [$evento, $anais] = $this->publicados($slug);

        $query = Anais::elegiveis($evento)->with(['autores', 'area', 'arquivoAnais', 'arquivoAtual', 'evento.trabalhoConfig']);

        if ($area = $request->input('area')) {
            $query->where('area_tematica_id', $area);
        }
        if (($tipo = $request->input('tipo')) && array_key_exists($tipo, TrabalhoConfig::TIPOS)) {
            $query->where('tipo_trabalho', $tipo);
        }
        if (($mod = $request->input('modalidade')) && array_key_exists($mod, Trabalho::MODALIDADES)) {
            $query->where('modalidade_apresentacao', $mod);
        }
        if ($busca = trim((string) $request->input('q'))) {
            $like = '%' . addcslashes($busca, '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('titulo', 'like', $like)
                    ->orWhere('palavras_chave', 'like', $like)
                    ->orWhereHas('autores', fn ($a) => $a->where('nome_completo', 'like', $like));
            });
        }

        $request->input('ordem') === 'area'
            ? $query->orderBy('area_tematica_id')->orderBy('titulo')
            : $query->orderBy('titulo');

        return view('publico.anais.index', [
            'evento' => $evento,
            'anais' => $anais,
            'trabalhos' => $query->paginate(15)->withQueryString(),
            'areas' => $evento->areasTematicas,
            'citacao' => $this->citacao,
            'pdfs' => $this->pdf,
        ]);
    }

    public function show(string $slug, string $codigo)
    {
        [$evento, $anais] = $this->publicados($slug);
        $trabalho = $this->trabalho($evento, $codigo);

        return view('publico.anais.show', [
            'evento' => $evento,
            'anais' => $anais,
            'trabalho' => $trabalho,
            'citacao' => $anais->dadosVisiveis() ? $this->citacao->gerar($trabalho, $anais) : null,
            'temPdf' => $this->pdf->arquivoPublico($trabalho) !== null,
        ]);
    }

    public function pdfTrabalho(string $slug, string $codigo)
    {
        [$evento] = $this->publicados($slug);
        $trabalho = $this->trabalho($evento, $codigo);

        $arquivo = $this->pdf->arquivoPublico($trabalho);
        abort_unless($arquivo && Storage::disk('trabalhos')->exists($arquivo->path), 404);

        return Storage::disk('trabalhos')->response($arquivo->path, Str::slug($trabalho->titulo) . '-' . $trabalho->codigo . '.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function pdfAnais(string $slug)
    {
        [, $anais] = $this->publicados($slug);
        abort_unless($anais->dadosVisiveis() && $anais->pdfPronto() && Storage::disk('trabalhos')->exists($anais->pdf_path), 404);

        return Storage::disk('trabalhos')->response($anais->pdf_path, 'anais-' . Str::slug($anais->evento->titulo) . '.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * O autor informa CPF e e-mail cadastrados no trabalho para obter o seu certificado.
     * A resposta de falha é genérica (não revela se o CPF ou o e-mail existem).
     */
    public function certificado(Request $request, string $slug, string $codigo)
    {
        [$evento] = $this->publicados($slug);
        $trabalho = $this->trabalho($evento, $codigo);

        $dados = $request->validate(['cpf' => ['required', 'string', 'max:14'], 'email' => ['required', 'email', 'max:255']]);

        $autor = $trabalho->autores->first(fn ($a) => $a->cpf === $dados['cpf'] && mb_strtolower($a->email) === mb_strtolower($dados['email']));
        $certificado = $autor ? $trabalho->certificados->first(fn ($c) => $c->trabalho_autor_id === $autor->id && !$c->revogado()) : null;

        if (!$certificado) {
            return back()->withInput($request->only('cpf'))->with('erro_certificado', 'Não foi possível localizar um certificado com esses dados. Verifique o CPF e o e-mail informados na submissão.');
        }

        return redirect()->route('certificado.trabalho.download', $certificado->hash_validacao);
    }
}
