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
        // endereço canônico (título + id) para SEO: só o número ou título antigo redireciona
        if ($url = $chamada->urlCanonicaSe((string) $request->segment(3), 'voluntariado.create')) {
            return redirect($url, 301);
        }

        // Visitante: vê o formulário (bloqueado) com aviso de login; volta para cá depois de entrar
        if (!$request->user()) {
            if (!$chamada->estaAberta()) {
                return redirect()->route('evento.publico.show', $chamada->evento->slug)->with('error', 'Esta chamada não está recebendo inscrições no momento.');
            }
            $request->session()->put('url.intended', $request->fullUrl());

            return view('voluntariado.inscrever', ['chamada' => $chamada->load('evento'), 'participante' => null, 'dias' => $chamada->evento->diasDeVoluntariado(), 'visitante' => true]);
        }

        if (!$chamada->estaAberta()) {
            return redirect()->route('voluntariado.meu')->with('error', 'Esta chamada não está recebendo inscrições no momento.');
        }
        if (!$this->contas->daConta($request->user())) {
            return redirect()->route('voluntariado.meu')->with('error', 'Complete seu cadastro (CPF) em "Minha Conta" antes de se inscrever.');
        }

        return view('voluntariado.inscrever', ['chamada' => $chamada->load('evento'), 'participante' => $this->contas->daConta($request->user()), 'dias' => $chamada->evento->diasDeVoluntariado()]);
    }

    public function store(Request $request, ChamadaVoluntario $chamada)
    {
        // Campos com formatação (editor): HTML limpo no servidor; o tamanho conta o texto visível
        $request->merge([
            'motivacao' => \App\Support\HtmlSeguro::limparSimples($request->input('motivacao')),
            'habilidades' => \App\Support\HtmlSeguro::limparSimples($request->input('habilidades')),
        ]);
        $texto = fn (int $min, int $max, string $rotulo) => function ($a, $v, $fail) use ($min, $max, $rotulo) {
            $n = \App\Support\HtmlSeguro::tamanho((string) $v);
            if ($n === 0 && $min === 0) {
                return;
            }
            if ($n < $min) {
                $fail("{$rotulo} deve ter pelo menos {$min} caracteres.");
            } elseif ($n > $max) {
                $fail("{$rotulo} pode ter no máximo {$max} caracteres (atual: {$n}).");
            }
        };

        $dados = $request->validate([
            'motivacao' => ['required', 'string', 'max:20000', $texto(20, 2000, 'A motivação')],
            'disponibilidade' => 'required|array|min:1', 'disponibilidade.*' => 'array', 'disponibilidade.*.*' => [\Illuminate\Validation\Rule::in(array_keys(\App\Models\Voluntario::PERIODOS))],
            'habilidades' => ['nullable', 'string', 'max:10000', $texto(0, 1000, 'As habilidades')],
            'telefone' => ['nullable', 'string', 'max:20', function ($a, $v, $fail) { if ($v !== null && !in_array(strlen(preg_replace('/\D/', '', $v)), [10, 11], true)) { $fail('Informe o telefone com DDD, por exemplo (93) 91234-5678.'); } }],
        ], ['motivacao.required' => 'Conte por que você quer ser voluntário(a).', 'disponibilidade.required' => 'Marque os dias e períodos em que você pode ajudar.', 'disponibilidade.min' => 'Marque ao menos um dia e período em que você pode ajudar.', 'disponibilidade.*.*.in' => 'Período de disponibilidade inválido.']);

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
