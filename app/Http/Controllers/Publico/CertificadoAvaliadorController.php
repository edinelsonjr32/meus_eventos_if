<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\CertificadoAvaliador;
use App\Services\CertificadoAvaliadorService;

/** Validação e download (por código aleatório) do certificado de avaliador. */
class CertificadoAvaliadorController extends Controller
{
    public function validar(string $hash)
    {
        $certificado = $this->valido($hash);

        return view('publico.validar-certificado-avaliador', compact('certificado', 'hash'));
    }

    public function download(string $hash, CertificadoAvaliadorService $servico)
    {
        $certificado = $this->valido($hash);
        abort_unless($certificado, 404);

        return $servico->gerarPdf($certificado)->stream("Certificado-avaliador-{$certificado->fase}.pdf");
    }

    private function valido(string $hash): ?CertificadoAvaliador
    {
        $c = CertificadoAvaliador::with(['avaliador', 'evento'])->where('hash_validacao', strtoupper($hash))->whereNull('revogado_em')->first();

        return $c && $c->evento ? $c : null;
    }
}
