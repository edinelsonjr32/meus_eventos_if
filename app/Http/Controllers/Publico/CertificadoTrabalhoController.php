<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\CertificadoTrabalho;
use App\Services\CertificadoTrabalhoService;
use Illuminate\Support\Str;

/** Validação e download públicos (por código aleatório) dos certificados de trabalhos. */
class CertificadoTrabalhoController extends Controller
{
    public function __construct(private CertificadoTrabalhoService $servico)
    {
    }

    public function validar(string $hash)
    {
        $certificado = CertificadoTrabalho::with(['autor', 'trabalho.evento'])->where('hash_validacao', strtoupper($hash))->first();

        return view('publico.validar-certificado-trabalho', compact('certificado', 'hash'));
    }

    public function download(string $hash)
    {
        $certificado = CertificadoTrabalho::with(['autor', 'trabalho.evento'])->where('hash_validacao', strtoupper($hash))->firstOrFail();

        abort_if($certificado->revogado(), 410, 'Este certificado foi revogado.');

        $certificado->increment('downloads');

        return $this->servico->gerarPdf($certificado)
            ->stream('Certificado_' . Str::slug($certificado->autor->nome_completo) . '_' . $certificado->trabalho->codigo . '.pdf');
    }
}
