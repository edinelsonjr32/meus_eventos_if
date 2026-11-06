<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;

/** Cria (ou promove) um administrador sem usar senhas padrão: a senha é digitada no momento. */
class CriarAdmin extends Command
{
    protected $signature = 'usuario:criar-admin {email : E-mail do administrador}
                            {--nome= : Nome completo (padrão: parte do e-mail)}
                            {--senha= : Senha (evite: fica no histórico do terminal; sem esta opção ela é pedida de forma oculta)}';

    protected $description = 'Cria o primeiro administrador (ou promove um usuário existente) com senha informada na hora';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => 'required|email|max:255'])->fails()) {
            $this->error('E-mail inválido.');

            return self::FAILURE;
        }

        $usuario = User::where('email', $email)->first();

        if ($usuario) {
            if ($usuario->role === 'admin') {
                $this->info("{$email} já é administrador.");
            } elseif ($this->input->isInteractive() && !$this->confirm("O usuário {$email} já existe. Promover a administrador?")) {
                return self::FAILURE;
            }
        }

        $senha = $this->option('senha');
        if ($senha === null && ($usuario === null || $this->confirm('Definir uma nova senha para este usuário?', false))) {
            if (!$this->input->isInteractive()) {
                $this->error('Sem terminal interativo: informe a senha com --senha=...');

                return self::FAILURE;
            }
            $senha = password('Senha do administrador (mínimo 12 caracteres)', required: true);
            if ($senha !== password('Repita a senha', required: true)) {
                $this->error('As senhas não conferem.');

                return self::FAILURE;
            }
        }

        if ($senha !== null && ($erro = $this->validarSenha($senha))) {
            $this->error($erro);

            return self::FAILURE;
        }

        $nome = $this->option('nome') ?: ($usuario?->name ?? ucfirst(explode('@', $email)[0]));

        $usuario ??= new User(['email' => $email]);
        $usuario->name = $usuario->exists ? $usuario->name : $nome;
        if ($senha !== null) {
            $usuario->password = $senha;   // cast 'hashed' do model
        }
        $usuario->forceFill(['role' => 'admin', 'email_verified_at' => $usuario->email_verified_at ?? now()])->save();

        $this->info("Administrador pronto: {$email}");

        return self::SUCCESS;
    }

    private function validarSenha(string $senha): ?string
    {
        return match (true) {
            mb_strlen($senha) < 12 => 'A senha deve ter ao menos 12 caracteres.',
            !preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha) => 'A senha deve misturar letras e números.',
            in_array(mb_strtolower($senha), ['admin@12345', 'senha@12345', 'password1234'], true) => 'Senha muito comum.',
            default => null,
        };
    }
}
