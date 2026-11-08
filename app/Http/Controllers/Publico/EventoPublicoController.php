<?php

namespace App\Http\Controllers\Publico;

use App\Rules\Cpf;
use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Inscricao;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth; // Importante para verificar login
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EventoPublicoController extends Controller
{
    /**
     * Exibe a Landing Page do Evento.
     */
    public function show($slug)
    {

        $evento = Evento::where('slug', $slug)->firstOrFail();

        // 1. Carrega e ordena todas as atividades
        $atividades = $evento->atividades()
            ->orderBy('data_inicio', 'asc') // Ordena primeiro por data completa e hora
            ->get();

        // 2. Agrupa as atividades por dia (YYYY-MM-DD) para estruturar o cronograma
        $cronogramaPorDia = $atividades->groupBy(function ($atividade) {
            // Usa o Carbon para formatar e agrupar pela data
            return $atividade->data_inicio->format('Y-m-d');
        });

        // Verifica se o usuário está inscrito (para botões de inscrição na view)
        $participanteLogado = Auth::check() ? Participante::where('user_id', Auth::id())->first() : null;
        $inscrito = $participanteLogado ? Inscricao::where('evento_id', $evento->id)->where('participante_id', $participanteLogado->id)->exists() : false;

        // Carrega turmas para o select do formulário
        $turmas = Turma::with('curso')->get()->sortBy('nome_completo');

        return view('publico.eventos.show', compact('evento', 'cronogramaPorDia', 'inscrito', 'turmas', 'participanteLogado'));
    }



    /** Guarda o evento como destino e leva ao login ou ao cadastro (a inscrição exige conta). */
    public function acesso(Request $request, string $slug, string $modo)
    {
        $evento = Evento::where('slug', $slug)->firstOrFail();

        if (Auth::check()) {
            return redirect()->route('evento.publico.show', $evento->slug);
        }

        session(['url.intended' => route('evento.publico.show', $evento->slug) . '#inscricao']);

        return redirect()->route($modo === 'cadastrar' ? 'register' : 'login');
    }

    /** Destino pós-login: mostra o formulário de inscrição do evento. */
    public function inscrever(string $slug)
    {
        $evento = Evento::where('slug', $slug)->firstOrFail();

        return redirect()->to(route('evento.publico.show', $evento->slug) . '#inscricao');
    }

    /**
     * Processa a inscrição. Exige conta logada: a inscrição fica sempre ligada ao mesmo participante da conta
     * (mesmo CPF, nome e e-mail), o que mantém credenciamento e frequência consistentes.
     */
    public function store(Request $request, $slug)
    {
        $evento = Evento::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        $participante = Participante::where('user_id', $user->id)->first();

        $request->validate([
            // Com cadastro de participante existente, CPF e nome vêm da conta (não editáveis aqui)
            'nome_completo' => [$participante ? 'nullable' : 'required', 'string', 'max:255'],
            'cpf' => [$participante ? 'nullable' : 'required', 'string', 'size:14', new Cpf],
            'tipo_vinculo' => ['required', Rule::in('aluno', 'servidor', 'externo')],
            'matricula' => [
                Rule::requiredIf(fn () => in_array($request->tipo_vinculo, ['aluno', 'servidor'])),
                'nullable', 'string', 'max:50',
            ],
            'turma_id' => ['required_if:tipo_vinculo,aluno', 'nullable', 'exists:turmas,id'],
        ]);

        try {
            $inscricao = DB::transaction(function () use ($request, $evento, $user, &$participante) {
                if (!$participante) {
                    // CPF já cadastrado: só pode ser assumido se não pertencer a outra conta
                    $existente = Participante::where('cpf', $request->cpf)->first();
                    if ($existente && $existente->user_id && $existente->user_id !== $user->id) {
                        throw new \DomainException('Este CPF já está vinculado a outra conta. Entre com a conta correta ou procure a organização do evento.');
                    }
                    $participante = $existente ?? new Participante(['cpf' => $request->cpf]);
                    $participante->nome_completo = $existente?->nome_completo ?: $request->nome_completo;
                }

                $participante->fill([
                    'user_id' => $user->id,
                    'email' => $user->email,   // sempre o e-mail da conta
                    'tipo_vinculo' => $request->tipo_vinculo,
                    'matricula' => in_array($request->tipo_vinculo, ['aluno', 'servidor']) ? $request->matricula : null,
                    'turma_id' => $request->tipo_vinculo === 'aluno' ? $request->turma_id : null,
                ])->save();

                if (Inscricao::where('evento_id', $evento->id)->where('participante_id', $participante->id)->exists()) {
                    throw new \DomainException('Você já possui uma inscrição confirmada neste evento.');
                }

                return Inscricao::create(['evento_id' => $evento->id, 'participante_id' => $participante->id, 'data_inscricao' => now()]);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Não foi possível concluir a inscrição. Tente novamente.')->withInput();
        }

        return redirect()->route('evento.publico.sucesso', $slug)->with('participante', $participante->nome_completo);
    }

    /**
     * Tela de Sucesso.
     */
    public function sucesso($slug)
    {
        $evento = Evento::where('slug', $slug)->firstOrFail();
        return view('publico.eventos.sucesso', compact('evento'));
    }
}
