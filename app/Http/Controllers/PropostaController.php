<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarPropostaRequest;
use App\Models\ChamadaProposta;
use App\Models\Equipe;
use App\Models\Evento;
use App\Models\Proposta;
use App\Services\ParticipanteDaConta;
use App\Services\PropostaService;
use Illuminate\Http\Request;

/** Área do proponente: enviar, acompanhar e editar propostas de minicurso/oficina; baixar o certificado de ministrante. */
class PropostaController extends Controller
{
    public function __construct(private PropostaService $service, private ParticipanteDaConta $contas)
    {
    }

    public function minhas(Request $request)
    {
        $user = $request->user();

        $propostas = Proposta::with(['evento', 'chamada', 'atividade', 'apoios'])->where('user_id', $user->id)->latest()->get();
        $chamadas = ChamadaProposta::abertas()->with('evento')->whereHas('evento')->orderBy('fim')->get();

        // Certificados de ministrante já emitidos para esta pessoa
        $participante = $this->contas->daConta($user);
        $certificados = $participante ? Equipe::with('evento')->where('participante_id', $participante->id)->where('funcao', 'Ministrante')->whereNotNull('certificado_emitido_em')->get()->keyBy('evento_id') : collect();

        return view('propostas.minhas', compact('propostas', 'chamadas', 'certificados', 'participante'));
    }

    public function create(Request $request, ChamadaProposta $chamada)
    {
        if ($erro = $this->bloqueio($request, $chamada)) {
            return redirect()->route('propostas.minhas')->with('error', $erro);
        }

        return view('propostas.form', ['chamada' => $chamada->load('evento'), 'proposta' => new Proposta(['tipo' => array_key_first($chamada->tiposAceitos()), 'requer_laboratorio' => false]), 'apoios' => old('apoios', [])]);
    }

    public function store(SalvarPropostaRequest $request, ChamadaProposta $chamada)
    {
        $dados = $request->validated();

        try {
            $proposta = $this->service->enviar($request->user(), $chamada, $dados, $dados['apoios'] ?? []);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('propostas.show', $proposta)->with('success', 'Proposta enviada! Você será avisado por e-mail quando a organização decidir.');
    }

    public function show(Request $request, Proposta $proposta)
    {
        $this->dono($request, $proposta);
        $proposta->load(['evento', 'chamada', 'atividade', 'apoios']);

        $certificado = $proposta->atividadeConcluida()
            ? Equipe::where('evento_id', $proposta->evento_id)->where('funcao', 'Ministrante')->whereNotNull('certificado_emitido_em')
                ->where('participante_id', optional($this->contas->daConta($request->user()))->id)->first()
            : null;

        return view('propostas.show', compact('proposta', 'certificado'));
    }

    public function edit(Request $request, Proposta $proposta)
    {
        $this->dono($request, $proposta);
        if (!$proposta->editavel()) {
            return redirect()->route('propostas.show', $proposta)->with('error', 'Esta proposta não pode mais ser editada.');
        }

        return view('propostas.form', [
            'chamada' => $proposta->chamada->load('evento'), 'proposta' => $proposta,
            'apoios' => old('apoios', $proposta->apoios->map(fn ($a) => ['nome' => $a->nome, 'email' => $a->email, 'cpf' => $a->cpf])->all()),
        ]);
    }

    public function update(SalvarPropostaRequest $request, Proposta $proposta)
    {
        $this->dono($request, $proposta);
        $dados = $request->validated();

        try {
            $this->service->atualizar($proposta, $dados, $dados['apoios'] ?? []);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('propostas.show', $proposta)->with('success', 'Proposta atualizada e reenviada para a organização.');
    }

    public function retirar(Request $request, Proposta $proposta)
    {
        $this->dono($request, $proposta);

        try {
            $this->service->retirar($proposta);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('propostas.minhas')->with('success', 'Proposta retirada.');
    }

    /** O ministrante baixa o certificado depois que a atividade termina (emitido na hora, se ainda não existir). */
    public function certificado(Request $request, Evento $evento)
    {
        try {
            $membro = $this->service->emitirCertificadoMinistrante($evento, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('certificado.equipe.download', $membro->hash_validacao);
    }

    private function bloqueio(Request $request, ChamadaProposta $chamada): ?string
    {
        if (!$chamada->estaAberta()) {
            return 'Esta chamada não está recebendo propostas no momento.';
        }

        return $this->contas->daConta($request->user()) ? null : 'Complete seu cadastro (CPF) em "Minha Conta" antes de enviar uma proposta.';
    }

    private function dono(Request $request, Proposta $proposta): void
    {
        abort_unless($proposta->user_id === $request->user()->id, 403);
    }
}
