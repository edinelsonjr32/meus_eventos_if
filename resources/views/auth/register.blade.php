<x-guest-layout>

    <div class="text-center mb-4">
        <h4 class="fw-bold text-dark mb-1">Crie sua Conta</h4>
        <p class="text-secondary small mb-0">Informe seu CPF para recuperar seu histórico de certificados.</p>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label small fw-bold text-uppercase text-secondary">Nome Completo</label>
            <input type="text" name="name" class="form-control" placeholder="Seu nome oficial" value="{{ old('name') }}" required autofocus autocomplete="name">
            @error('name') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold text-uppercase text-secondary">CPF</label>
            <input type="text" name="cpf" class="form-control cpf-mask" placeholder="000.000.000-00" value="{{ old('cpf') }}" required>
            <div class="form-text small text-muted">Usaremos seu CPF para vincular certificados anteriores.</div>
            @error('cpf') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold text-uppercase text-secondary">Email</label>
            <input type="email" name="email" class="form-control" placeholder="seu@email.com" value="{{ old('email') }}" required autocomplete="username">
            @error('email') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold text-uppercase text-secondary">Senha</label>
            <input type="password" name="password" class="form-control" placeholder="Mínimo 8 caracteres" required autocomplete="new-password">
            @error('password') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
        </div>

        <div class="mb-4">
            <label class="form-label small fw-bold text-uppercase text-secondary">Confirmar Senha</label>
            <input type="password" name="password_confirmation" class="form-control" placeholder="Repita a senha" required autocomplete="new-password">
            @error('password_confirmation') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
        </div>

        <button type="submit" class="btn-primary-custom shadow-lg text-uppercase tracking-wide">
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
