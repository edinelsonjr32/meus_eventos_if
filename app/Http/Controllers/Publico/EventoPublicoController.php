<?php

namespace App\Http\Controllers\Publico;

use App\Rules\Cpf;
use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Inscricao;
use App\Models\Turma;
use App\Models\User;
use App\Services\ParticipanteDaConta;
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

        // Depois de entrar ou criar a conta a pessoa vai para a própria dashboard (de onde acessa os eventos)
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

        $contas = app(ParticipanteDaConta::class);
        $request->validate($contas->regras($contas->daConta($user) !== null, $request->tipo_vinculo));

        try {
            $participante = null;
            DB::transaction(function () use ($request, $evento, $user, $contas, &$participante) {
                $participante = $contas->resolver($user, $request->only(['cpf', 'nome_completo', 'tipo_vinculo', 'matricula', 'turma_id']));

                if (Inscricao::where('evento_id', $evento->id)->where('participante_id', $participante->id)->exists()) {
                    throw new \DomainException('Você já possui uma inscrição confirmada neste evento.');
                }

                Inscricao::create(['evento_id' => $evento->id, 'participante_id' => $participante->id, 'data_inscricao' => now()]);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Não foi possível concluir a inscrição. Tente novamente.')->withInput();
        }

        // Inscrição concluída: volta para a própria dashboard, com o aviso de confirmação
        return redirect()->route(\App\Support\DestinoPosAutenticacao::rotaDashboard($user))->with('success', 'Inscrição confirmada em "' . $evento->titulo . '", ' . $participante->nome_completo . '!');
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
