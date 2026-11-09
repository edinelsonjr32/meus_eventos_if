<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Equipe;
use App\Services\CertificadoEquipeService;

/** Validação e download (por código aleatório) do certificado da equipe de organização. */
class CertificadoEquipeController extends Controller
{
    public function __construct(private CertificadoEquipeService $servico)
    {
    }

    public function validar(string $hash)
    {
        $membro = Equipe::with(['participante', 'evento'])->where('hash_validacao', strtoupper($hash))->first();

        return view('publico.validar-certificado-equipe', compact('membro', 'hash'));
    }

    public function download(string $hash)
    {
        $membro = Equipe::with(['participante', 'evento.assinaturas'])->where('hash_validacao', strtoupper($hash))->firstOrFail();

        return $this->servico->gerarPdf($membro)->stream($this->servico->nomeArquivo($membro));
    }
}
