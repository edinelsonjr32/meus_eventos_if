<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Equipe;
use App\Models\Participante;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class EquipeController extends Controller
{
    /**
     * Lista a equipe do evento.
     */
    public function index(Evento $evento)
    {
        $membros = $evento->equipe()->with('participante')->get();
        return view('admin.equipes.index', compact('evento', 'membros'));
    }

    /**
     * Adiciona um membro à equipe.
     */
    public function store(Request $request, Evento $evento)
    {
        if ($evento->criado_por !== Auth::id()) abort(403);

        $request->validate([
            'cpf_busca' => 'required|string',
            'funcao' => 'required|string',
            'carga_horaria' => 'nullable|integer|min:1', // A validação já garante que é um número (ou vazio)
        ]);

        $termo = $request->cpf_busca;
        $cpfLimpo = preg_replace('/[^0-9]/', '', $termo);

        $participante = Participante::where(function ($query) use ($termo, $cpfLimpo) {
            $query->where('cpf', $termo)
                ->orWhere('cpf', $cpfLimpo);
        })->first();

        if (!$participante) {
            return back()->with('error', 'Participante não encontrado! Ele precisa se cadastrar no sistema antes de ser adicionado à equipe.');
        }

        // Verifica duplicidade
        $jaExiste = Equipe::where('evento_id', $evento->id)
            ->where('participante_id', $participante->id)
            ->exists();

        if ($jaExiste) {
            return back()->with('warning', 'Esta pessoa já faz parte da equipe deste evento.');
        }

        // CORREÇÃO: Trata a string vazia ou nula para garantir o valor padrão (20)
        $cargaHoraria = empty($request->carga_horaria) ? 20 : (int)$request->carga_horaria;

        Equipe::create([
            'evento_id' => $evento->id,
            'participante_id' => $participante->id,
            'funcao' => $request->funcao,
            'carga_horaria' => $cargaHoraria, // Usa o valor tratado
            'hash_certificado' => Str::uuid()
        ]);

        return back()->with('success', "{$participante->nome_completo} adicionado à equipe com sucesso!");
    }
    /**
     * Remove um membro.
     */
    public function destroy(Equipe $equipe)
    {
        $equipe->delete();
        return back()->with('success', 'Membro removido da equipe.');
    }
}
