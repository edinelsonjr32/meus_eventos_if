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
            'cpf' => ['required', 'string', 'size:14'],
        ]);

        // 2. Criação do Usuário (Conta de Acesso)
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
        ]);

        event(new Registered($user));

        // 3. LÓGICA DE VÍNCULO (CORRIGIDA PARA EVITAR DUPLICIDADE DE CPF)

        // Tentamos encontrar um participante existente pelo CPF.
        // O CPF é o identificador mais forte para "quem é a pessoa".
        $participante = Participante::where('cpf', $request->cpf)->first();

        if ($participante) {
            // CENÁRIO A: O CPF já existe na base de participantes.
            // Isso significa que a pessoa já participou de eventos antes (com ou sem conta).

            // Atualizamos o registro existente para vinculá-lo ao novo User.
            // ATENÇÃO: Se o participante já tinha outro user_id, isso irá sobrescrever (vincular à nova conta),
            // ou você pode adicionar uma verificação `if (!$participante->user_id)` se quiser proteger contas antigas.

            $participante->update([
                'user_id' => $user->id,       // Vincula à nova conta criada
                'nome_completo' => $user->name, // Atualiza nome (opcional)
                'email' => $user->email,      // Atualiza email para bater com a conta (importante para consistência)
            ]);
        } else {
            // CENÁRIO B: O CPF nunca foi registrado em eventos.
            // Podemos criar um novo registro de Participante sem medo de duplicidade.

            Participante::create([
                'user_id' => $user->id,
                'cpf' => $request->cpf,
                'nome_completo' => $user->name,
                'email' => $user->email,
                'tipo_vinculo' => 'externo' // Padrão inicial
            ]);
        }

        // 4. Login e Redirecionamento
        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
