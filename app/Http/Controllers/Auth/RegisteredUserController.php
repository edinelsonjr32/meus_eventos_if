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
        // O e-mail já é verificado como único na tabela 'users'.
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'cpf' => ['required', 'string', 'size:14'], // Apenas validação de formato e obrigatoriedade do CPF
        ]);

        // O Passo 2 de verificação de CPF em uso foi removido.
        // A única verificação de duplicidade é no e-mail (em 'users').

        // 3. Criação do Usuário
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user', // Define papel padrão
        ]);

        event(new Registered($user));

        // 4. LÓGICA DE VÍNCULO (RESGATE DE HISTÓRICO)
        // BUSCA: Busca se há um Participante que usou o mesmo e-mail,
        // mas que ainda não possui um user_id vinculado (registro antigo/sem conta).
        $participante = Participante::where('email', $request->email)
            ->whereNull('user_id') // Importante: Verifica se não está vinculado a outro User
            ->first();
            
        // A busca por CPF foi removida, usando o e-mail como elo.
        // O CPF é usado apenas se for necessário vincular certificados
        // de alguém que usou *outro* e-mail, mas o mesmo CPF,
        // mas para simplificar, usaremos o e-mail como chave principal.

        if ($participante) {
            // CENÁRIO A: Já participou antes (registro existe por e-mail, sem user_id).
            // Atualizamos o registro existente para VINCULÁ-LO ao novo usuário.
            // Isso faz com que todos os certificados e inscrições antigas apareçam na nova conta.
            $participante->update([
                'user_id' => $user->id, // ESTE É O VÍNCULO CHAVE
                'nome_completo' => $user->name,
                'cpf' => $request->cpf, // Opcional: Garante que o CPF do participante esteja atualizado
            ]);
        } else {
            // CENÁRIO B: Nunca participou com esse e-mail OU participou com e-mail já vinculado.
            // Cria perfil novo de Participante VINCULADO imediatamente.
            Participante::create([
                'user_id' => $user->id, // ESTE É O VÍNCULO CHAVE
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
