<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Designação de papéis por evento (co-administrador, gestor de trabalhos, avaliador).
 */
class EventoPapelController extends Controller
{
    public const PAPEIS = [
        Evento::PAPEL_GESTOR_TRABALHOS => 'Gestor de Trabalhos',
        Evento::PAPEL_AVALIADOR => 'Avaliador',
        Evento::PAPEL_RECEPCAO => 'Recepção (check-in e credenciamento)',
        Evento::PAPEL_EDITOR => 'Co-administrador',
    ];

    public function index(Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $designacoes = DB::table('evento_user')
            ->join('users', 'users.id', '=', 'evento_user.user_id')
            ->where('evento_user.evento_id', $evento->id)
            ->orderBy('evento_user.role')->orderBy('users.name')
            ->get(['users.id as user_id', 'users.name', 'users.email', 'users.role as perfil', 'evento_user.role as papel']);

        $papeis = self::PAPEIS;
        $ehDono = $evento->criado_por === auth()->id();

        return view('admin.eventos.papeis', compact('evento', 'designacoes', 'papeis', 'ehDono'));
    }

    public function store(Request $request, Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $dados = $request->validate([
            'email' => ['required', 'email'],
            'papel' => ['required', Rule::in(array_keys(self::PAPEIS))],
        ]);

        // Somente o dono do evento designa co-administradores
        if ($dados['papel'] === Evento::PAPEL_EDITOR && $evento->criado_por !== $request->user()->id) {
            abort(403, 'Somente o criador do evento pode designar co-administradores.');
        }

        $usuario = User::where('email', mb_strtolower($dados['email']))->first();
        if (!$usuario) {
            return back()->withInput()->with('error', 'Nenhum usuário cadastrado com este e-mail. A pessoa precisa criar uma conta antes.');
        }

        if ($usuario->id === $evento->criado_por) {
            return back()->withInput()->with('error', 'O criador do evento já possui acesso total.');
        }

        if ($dados['papel'] === Evento::PAPEL_EDITOR && $usuario->role !== 'admin') {
            return back()->withInput()->with('error', 'Co-administradores precisam ter o perfil de Administrador.');
        }

        $existe = DB::table('evento_user')->where([
            'evento_id' => $evento->id, 'user_id' => $usuario->id, 'role' => $dados['papel'],
        ])->exists();

        if ($existe) {
            return back()->with('warning', 'Este usuário já possui este papel no evento.');
        }

        DB::table('evento_user')->insert([
            'evento_id' => $evento->id,
            'user_id' => $usuario->id,
            'role' => $dados['papel'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', "{$usuario->name} designado como " . self::PAPEIS[$dados['papel']] . '.');
    }

    public function destroy(Request $request, Evento $evento, User $user, string $papel)
    {
        $this->authorize('gerenciar', $evento);

        abort_unless(array_key_exists($papel, self::PAPEIS), 404);

        if ($papel === Evento::PAPEL_EDITOR && $evento->criado_por !== $request->user()->id) {
            abort(403, 'Somente o criador do evento pode remover co-administradores.');
        }

        DB::table('evento_user')->where([
            'evento_id' => $evento->id, 'user_id' => $user->id, 'role' => $papel,
        ])->delete();

        return back()->with('success', 'Papel removido.');
    }
}
