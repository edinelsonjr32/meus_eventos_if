<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\AtividadeEquipe;
use App\Services\EquipeDaAtividade;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Certificado de ministrante/ajudante: retirada pela própria pessoa, validação e download pelo código. */
class CertificadoAtividadeController extends Controller
{
    public function __construct(private EquipeDaAtividade $equipe)
    {
    }

    /** A própria pessoa, com a presença já lançada, emite e baixa. */
    public function meu(Request $request, AtividadeEquipe $registro)
    {
        abort_unless($registro->pertenceA($request->user()), 404);

        try {
            $this->equipe->emitir($registro);
        } catch (\DomainException $e) {
            return redirect()->route('inscrito.dashboard')->with('error', $e->getMessage());
        }

        return redirect()->route('certificado.atividade.download', $registro->hash_validacao);
    }

    public function validar(string $hash)
    {
        $registro = $this->emitido($hash);

        return view('publico.validar-certificado-atividade', compact('registro', 'hash'));
    }

    public function download(string $hash)
    {
        $registro = $this->emitido($hash);
        abort_unless($registro, 404);

        return $this->equipe->gerarPdf($registro)->stream('Certificado-' . $registro->funcao . '_' . Str::slug($registro->participante->nome_completo) . '.pdf');
    }

    private function emitido(string $hash): ?AtividadeEquipe
    {
        $registro = AtividadeEquipe::with(['participante', 'atividade.evento'])->where('hash_validacao', strtoupper($hash))->whereNotNull('certificado_emitido_em')->first();

        return $registro && $registro->atividade?->emiteCertificado() && $registro->atividade->evento ? $registro : null;
    }
}
