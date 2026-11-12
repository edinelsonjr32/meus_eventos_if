<?php

namespace App\Http\Controllers;

use App\Models\ChamadaProposta;
use App\Models\Equipe;
use App\Models\Evento;
use App\Models\Proposta;
use App\Rules\Cpf;
use App\Services\ParticipanteDaConta;
use App\Services\PropostaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function store(Request $request, ChamadaProposta $chamada)
    {
        $dados = $request->validate($this->regras($chamada), $this->mensagens());

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

    public function update(Request $request, Proposta $proposta)
    {
        $this->dono($request, $proposta);
        $dados = $request->validate($this->regras($proposta->chamada), $this->mensagens());

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

    private function regras(ChamadaProposta $chamada): array
    {
        return [
            'tipo' => ['required', Rule::in(array_keys($chamada->tiposAceitos()))],
            'titulo' => 'required|string|max:200',
            'resumo' => 'required|string|min:80|max:3000',
            'objetivos' => 'nullable|string|max:3000', 'publico_alvo' => 'nullable|string|max:255',
            'conteudo_programatico' => 'nullable|string|max:5000', 'metodologia' => 'nullable|string|max:3000',
            'carga_horaria_prevista' => 'required|integer|min:1|max:40', 'vagas_sugeridas' => 'required|integer|min:1|max:500',
            'requer_laboratorio' => 'nullable|boolean', 'espaco_solicitado' => 'nullable|string|max:255',
            'equipamentos_solicitados' => 'nullable|string|max:2000', 'observacoes' => 'nullable|string|max:2000',
            'apoios' => 'nullable|array|max:5',
            'apoios.*.nome' => 'nullable|string|max:255', 'apoios.*.email' => 'nullable|email|max:255', 'apoios.*.cpf' => ['nullable', 'string', 'max:14', new Cpf],
        ];
    }

    private function mensagens(): array
    {
        return [
            'resumo.min' => 'O resumo deve ter pelo menos 80 caracteres: descreva o que será ensinado.',
            'carga_horaria_prevista.max' => 'A carga horária máxima é de 40 horas.',
            'apoios.max' => 'Informe no máximo 5 pessoas de apoio.',
        ];
    }
}
