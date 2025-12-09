<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Participante;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // 1. Validação Básica do User
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            // Validação do CPF: obrigatório e no formato correto
            'cpf' => ['required', 'string', 'size:14'],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // 2. Lógica de Vínculo com Participante
        $cpf = $request->input('cpf');

        // Verifica se este CPF já está sendo usado por OUTRO usuário diferente do atual
        $cpfEmUsoPorOutro = Participante::where('cpf', $cpf)
            ->whereNotNull('user_id')
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($cpfEmUsoPorOutro) {
            // Retorna com erro se o CPF já é de outra conta
            return Redirect::route('profile.edit')->withErrors(['cpf' => 'Este CPF já está vinculado a outra conta de usuário.']);
        }

        // Tenta encontrar um participante existente com este CPF (que não tenha usuário ou seja o próprio)
        $participante = Participante::where('cpf', $cpf)->first();

        if ($participante) {
            // CENÁRIO A: O participante já existia (ex: foi a um evento no passado).
            // Atualizamos os dados e garantimos o vínculo com o usuário atual.
            $participante->update([
                'user_id' => $user->id,
                'nome_completo' => $user->name, // Mantém sincronizado
                'email' => $user->email,         // Mantém sincronizado
            ]);
        } else {
            // CENÁRIO B: CPF novo no sistema. Cria o registro do participante.
            Participante::create([
                'user_id' => $user->id,
                'cpf' => $cpf,
                'nome_completo' => $user->name,
                'email' => $user->email,
                'tipo_vinculo' => 'externo' // Padrão, pode ser alterado depois se necessário
            ]);
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }
    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
