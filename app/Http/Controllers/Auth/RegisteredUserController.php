<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Participante; // Importante
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create()
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(Request $request): RedirectResponse
    {
        // 1. Validações
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'cpf' => ['required', 'string', 'size:14'], // Formato 000.000.000-00
        ]);

        // 2. Verificação de Segurança do CPF
        // Verifica se já existe um participante com este CPF vinculado a OUTRO usuário
        $cpfEmUso = Participante::where('cpf', $request->cpf)
            ->whereNotNull('user_id')
            ->exists();

        if ($cpfEmUso) {
            return back()->withErrors(['cpf' => 'Este CPF já está vinculado a uma conta de usuário existente. Faça login.'])->withInput();
        }

        // 3. Criação do Usuário
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user', // Define papel padrão
        ]);

        event(new Registered($user));

        // 4. LÓGICA DE VÍNCULO (RESGATE DE HISTÓRICO)
        // Busca se esse CPF já participou de algo no passado (sem ter conta)
        $participante = Participante::where('cpf', $request->cpf)->first();

        if ($participante) {
            // CENÁRIO A: Já participou antes. Atualizamos o registro existente.
            // Isso faz com que todos os certificados e inscrições antigas apareçam na nova conta.
            $participante->update([
                'user_id' => $user->id,
                'nome_completo' => $user->name, // Atualiza nome para garantir sincronia
                'email' => $user->email,
            ]);
        } else {
            // CENÁRIO B: Nunca participou. Cria perfil novo.
            Participante::create([
                'user_id' => $user->id,
                'cpf' => $request->cpf,
                'nome_completo' => $user->name,
                'email' => $user->email,
                'tipo_vinculo' => 'externo' // Padrão inicial
            ]);
        }

        // 5. Login e Redirecionamento
        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
