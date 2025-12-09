<?php

namespace App\Http\Controllers\Inscrito;

use App\Http\Controllers\Controller;
use App\Models\Atividade;
use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgendaController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $participante = $user->participante;

        if (!$participante) {
            return redirect()->route('profile.edit')
                ->with('warning', 'Para acessar sua agenda, complete seu cadastro informando seu CPF.');
        }

        $eventosInscritos = Evento::whereHas('inscricoes', function ($q) use ($participante) {
            $q->where('participante_id', $participante->id);
        })
            ->with(['atividades' => function ($q) {
                $q->orderBy('data_inicio');
            }])
            ->get();

        return view('inscrito.dashboard', compact('eventosInscritos', 'participante'));
    }

    public function toggleAtividade(Atividade $atividade)
    {
        $participante = Auth::user()->participante;

        if (!$participante) {
            return redirect()->route('profile.edit')->with('error', 'Complete seu cadastro primeiro.');
        }

        // Verifica se já está inscrito (para sair)
        $jaInscrito = $participante->atividadesInscritas()->where('atividade_id', $atividade->id)->exists();

        if ($jaInscrito) {
            // Se já está inscrito, remove a inscrição (Sair)
            $participante->atividadesInscritas()->detach($atividade->id);
            return back()->with('info', 'Inscrição cancelada. Vaga liberada.');
        } else {
            // TENTATIVA DE INSCRIÇÃO (Entrar)

            // 1. Validação de Vagas
            $totalInscritos = $atividade->inscritos()->count();
            if (!is_null($atividade->vagas) && $totalInscritos >= $atividade->vagas) {
                return back()->with('error', 'Desculpe, as vagas para esta atividade estão esgotadas.');
            }

            // 2. Validação de Choque de Horário (NOVO)
            // Busca se existe alguma atividade na agenda do aluno que conflite com os horários da nova
            $conflito = $participante->atividadesInscritas()
                ->where('evento_id', $atividade->evento_id) // Opcional: conflito só dentro do mesmo evento? Geralmente sim.
                ->where(function ($query) use ($atividade) {
                    // Lógica de Interseção: (InicioA < FimB) E (FimA > InicioB)
                    $query->where('data_inicio', '<', $atividade->data_fim)
                        ->where('data_fim', '>', $atividade->data_inicio);
                })
                ->first();

            if ($conflito) {
                return back()->with('error', "Conflito de horário! Você já está inscrito na atividade '{$conflito->titulo}' ({$conflito->data_inicio->format('H:i')} - {$conflito->data_fim->format('H:i')}) neste mesmo período.");
            }

            // Se passou em tudo, inscreve
            $participante->atividadesInscritas()->attach($atividade->id);
            return back()->with('success', 'Inscrição confirmada na atividade!');
        }
    }
}
