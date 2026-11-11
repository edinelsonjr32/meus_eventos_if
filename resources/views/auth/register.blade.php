<x-guest-layout>

    <div class="text-center mb-4">
        <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
            <i class="fa-solid fa-user-plus fs-3"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Crie sua Conta</h4>
        <p class="text-secondary small mb-0">Informe seu CPF para recuperar seu histórico de certificados.</p>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label small fw-bold text-uppercase text-secondary">Nome Completo</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-secondary"><i class="fa-regular fa-user"></i></span>
                <input type="text" name="name" class="form-control border-start-0 ps-0" placeholder="Seu nome oficial" value="{{ old('name') }}" required autofocus autocomplete="name">
            </div>
            @error('name') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold text-uppercase text-secondary">CPF</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-secondary"><i class="fa-regular fa-id-card"></i></span>
                <input type="text" name="cpf" class="form-control border-start-0 ps-0 cpf-mask" placeholder="000.000.000-00" value="{{ old('cpf') }}" required inputmode="numeric">
            </div>
            <div class="form-text small text-muted">Usaremos seu CPF para vincular certificados anteriores.</div>
            @error('cpf') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold text-uppercase text-secondary">Email</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-secondary"><i class="fa-regular fa-envelope"></i></span>
                <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="seu@email.com" value="{{ old('email') }}" required autocomplete="username">
            </div>
            @error('email') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
        </div>

        <x-campo-senha name="password" label="Senha" placeholder="Mínimo 8 caracteres" autocomplete="new-password" />

        <div class="mb-4">
            <x-campo-senha name="password_confirmation" label="Confirmar Senha" placeholder="Repita a senha" autocomplete="new-password" />
        </div>

        <button type="submit" class="btn btn-brand w-100 py-2">
            Cadastrar-se
        </button>

        <div class="text-center mt-4 pt-3 border-top">
            <span class="text-secondary small">Já possui uma conta?</span>
            <a href="{{ route('login') }}" class="text-decoration-none fw-bold text-success ms-1">Entrar agora</a>
        </div>
    </form>

    <script>
        $(document).ready(function(){
            $('.cpf-mask').mask('000.000.000-00');
        });
    </script>
</x-guest-layout>
