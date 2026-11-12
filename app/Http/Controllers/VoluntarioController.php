<?php

namespace App\Http\Controllers;

use App\Models\ChamadaVoluntario;
use App\Models\Voluntario;
use App\Services\VoluntarioService;
use Illuminate\Http\Request;

/** Área do voluntário: inscrever-se, ver atividades e responsável, desistir e baixar o certificado. */
class VoluntarioController extends Controller
{
    public function __construct(private VoluntarioService $service, private \App\Services\ParticipanteDaConta $contas)
    {
    }

    public function meu(Request $request)
    {
        $inscricoes = Voluntario::with(['evento', 'chamada', 'demandas'])->where('user_id', $request->user()->id)->latest()->get();
        $chamadas = ChamadaVoluntario::abertas()->with('evento')->whereHas('evento')->orderBy('fim')->get();
        $certificados = $inscricoes->mapWithKeys(fn ($v) => [$v->id => $this->service->certificadoDe($v)]);
        $participante = $this->contas->daConta($request->user());

        return view('voluntariado.meu', compact('inscricoes', 'chamadas', 'certificados', 'participante'));
    }

    public function create(Request $request, ChamadaVoluntario $chamada)
    {
        if (!$chamada->estaAberta()) {
            return redirect()->route('voluntariado.meu')->with('error', 'Esta chamada não está recebendo inscrições no momento.');
        }
        if (!$this->contas->daConta($request->user())) {
            return redirect()->route('voluntariado.meu')->with('error', 'Complete seu cadastro (CPF) em "Minha Conta" antes de se inscrever.');
        }

        return view('voluntariado.inscrever', ['chamada' => $chamada->load('evento'), 'participante' => $this->contas->daConta($request->user())]);
    }

    public function store(Request $request, ChamadaVoluntario $chamada)
    {
        $dados = $request->validate([
            'motivacao' => 'required|string|min:20|max:2000', 'disponibilidade' => 'required|string|max:1000',
            'habilidades' => 'nullable|string|max:1000', 'telefone' => 'nullable|string|max:30',
        ], ['motivacao.required' => 'Conte por que você quer ser voluntário(a).', 'motivacao.min' => 'Escreva um pouco mais (pelo menos 20 caracteres).', 'disponibilidade.required' => 'Informe os dias e horários em que você pode ajudar.']);

        try {
            $this->service->inscrever($request->user(), $chamada, $dados);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('voluntariado.meu')->with('success', 'Inscrição enviada! A organização vai analisar e você será avisado(a) por e-mail.');
    }

    public function desistir(Request $request, Voluntario $voluntario)
    {
        abort_unless($voluntario->user_id === $request->user()->id, 403);
        $this->service->desistir($voluntario);

        return back()->with('success', 'Você desistiu do voluntariado neste evento.');
    }

    public function certificado(Request $request, Voluntario $voluntario)
    {
        abort_unless($voluntario->user_id === $request->user()->id, 403);

        $membro = $this->service->certificadoDe($voluntario);
        abort_unless($membro && $membro->certificadoEmitido(), 404, 'O certificado ainda não foi emitido pela organização.');

        return redirect()->route('certificado.equipe.download', $membro->hash_validacao);
    }
}
