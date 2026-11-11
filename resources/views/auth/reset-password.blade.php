<x-guest-layout>
    <div class="text-center mb-4">
        <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
            <i class="fa-solid fa-lock-open fs-3"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Crie uma nova senha</h4>
        <p class="text-secondary small mb-0">Escolha uma senha com pelo menos 8 caracteres.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="mb-3">
            <label for="email" class="form-label small fw-bold text-uppercase text-secondary">Email</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-secondary"><i class="fa-regular fa-envelope"></i></span>
                <input id="email" type="email" name="email" class="form-control border-start-0 ps-0 @error('email') is-invalid @enderror" value="{{ old('email', $request->email) }}" required autocomplete="username">
            </div>
            @error('email')
                <small class="text-danger d-block mt-1">{{ $message }}</small>
            @enderror
        </div>

        <x-campo-senha name="password" label="Nova senha" placeholder="Mínimo 8 caracteres" autocomplete="new-password" :autofocus="true" />
        <div class="mb-4">
            <x-campo-senha name="password_confirmation" label="Confirmar nova senha" placeholder="Repita a senha" autocomplete="new-password" />
        </div>

        <button type="submit" class="btn-primary-custom shadow-lg text-uppercase tracking-wide w-100">
            <i class="fa-solid fa-check me-1"></i> Salvar nova senha
        </button>

        <div class="text-center mt-4 pt-3 border-top">
            <a href="{{ route('login') }}" class="text-decoration-none fw-bold text-success"><i class="fa-solid fa-arrow-left me-1"></i> Voltar ao login</a>
        </div>
    </form>
</x-guest-layout>
