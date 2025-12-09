<x-guest-layout>

    <div class="text-center mb-4">
        <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
            <i class="fa-solid fa-right-to-bracket fs-3"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Bem-vindo de volta!</h4>
        <p class="text-secondary small mb-0">Insira suas credenciais para acessar o sistema.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success small border-0 bg-success bg-opacity-10 text-success mb-4 rounded-3">
            <i class="fa-solid fa-check-circle me-1"></i> {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label small fw-bold text-uppercase text-secondary">Email</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-secondary"><i class="fa-regular fa-envelope"></i></span>
                <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="seu@email.com" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            @error('email')
                <small class="text-danger d-block mt-1">{{ $message }}</small>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold text-uppercase text-secondary">Senha</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-secondary"><i class="fa-solid fa-lock"></i></span>
                <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="Sua senha" required autocomplete="current-password">
            </div>
            @error('password')
                <small class="text-danger d-block mt-1">{{ $message }}</small>
            @enderror
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember_me">
                <label class="form-check-label small text-secondary" for="remember_me">
                    Lembrar-me
                </label>
            </div>
            @if (Route::has('password.request'))
                <a class="text-decoration-none small text-success fw-bold" href="{{ route('password.request') }}">
                    Esqueceu a senha?
                </a>
            @endif
        </div>

        <button type="submit" class="btn-primary-custom shadow-lg text-uppercase tracking-wide w-100">
            Entrar
        </button>

        <div class="text-center mt-4 pt-3 border-top">
            <span class="text-secondary small">Ainda não tem conta?</span>
            <a href="{{ route('register') }}" class="text-decoration-none fw-bold text-success ms-1">
                Cadastre-se aqui
            </a>
        </div>
    </form>
</x-guest-layout>
