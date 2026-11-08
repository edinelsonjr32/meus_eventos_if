<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\CertificadoPremiacao;
use App\Services\PremiacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Validação pública (por código aleatório) e download dos certificados de premiação. */
class CertificadoPremiacaoController extends Controller
{
    public function __construct(private PremiacaoService $servico)
    {
    }

    public function validar(string $hash)
    {
        $certificado = CertificadoPremiacao::with(['autor', 'premiacao.trabalho.evento'])->where('hash_validacao', strtoupper($hash))->first();

        return view('publico.validar-certificado-premiacao', compact('certificado', 'hash'));
    }

    /** O autor só baixa depois da divulgação; a gestão pode baixar sempre. */
    public function download(Request $request, string $hash)
    {
        $certificado = CertificadoPremiacao::with(['autor', 'premiacao.trabalho.evento.trabalhoConfig'])->where('hash_validacao', strtoupper($hash))->firstOrFail();
        abort_if($certificado->revogado(), 410, 'Este certificado foi revogado.');

        $evento = $certificado->premiacao->trabalho->evento;
        $gestao = $request->user()?->can('gerirTrabalhos', $evento) ?? false;
        abort_unless($gestao || $this->servico->divulgada($evento), 403, 'A premiação ainda não foi divulgada.');

        $certificado->increment('downloads');

        return $this->servico->gerarPdf(collect([$certificado]))->stream('Premiacao_' . Str::slug($certificado->autor->nome_completo) . '.pdf');
    }
}
