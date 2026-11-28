<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atividade;
use App\Models\AtividadeEquipe;
use App\Services\EquipeDaAtividade;
use Illuminate\Http\Request;

/** Presença e certificado de ministrantes e ajudantes de uma atividade. */
class AtividadeEquipeController extends Controller
{
    public function __construct(private EquipeDaAtividade $equipe)
    {
    }

    public function presenca(Request $request, Atividade $atividade)
    {
        $this->authorize('gerenciar', $atividade->evento);
        $chave = $request->validate(['chave' => ['required', 'string', 'regex:/^(proponente|(apoio|resp|reg):\d+)$/']])['chave'];

        try {
            $registro = $this->equipe->lancarPresenca($atividade, $chave, $request->user());
        } catch (\DomainException $e) {
            return $this->voltar($atividade, 'error', $e->getMessage());
        }

        return $this->voltar($atividade, 'success', "Presença de {$registro->participante->nome_completo} lançada: certificado liberado.");
    }

    public function certificado(Atividade $atividade, AtividadeEquipe $registro)
    {
        $this->conferir($atividade, $registro);

        try {
            $this->equipe->emitir($registro);
        } catch (\DomainException $e) {
            return $this->voltar($atividade, 'error', $e->getMessage());
        }

        return $this->voltar($atividade, 'success', "Certificado de {$registro->participante->nome_completo} emitido.");
    }

    public function destroy(Atividade $atividade, AtividadeEquipe $registro)
    {
        $this->conferir($atividade, $registro);
        $nome = $registro->participante->nome_completo;
        $registro->delete();

        return $this->voltar($atividade, 'success', "Presença de {$nome} desfeita; o certificado deixou de valer.");
    }

    private function conferir(Atividade $atividade, AtividadeEquipe $registro): void
    {
        $this->authorize('gerenciar', $atividade->evento);
        abort_unless($registro->atividade_id === $atividade->id, 404);
    }

    private function voltar(Atividade $atividade, string $tipo, string $mensagem)
    {
        return redirect()->to(route('admin.atividades.edit', $atividade) . '#presenca-equipe')->with($tipo, $mensagem);
    }
}
