<x-guest-layout>
    <div class="text-center mb-4">
        <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
            <i class="fa-solid fa-key fs-3"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Esqueceu a senha?</h4>
        <p class="text-secondary small mb-0">Sem problema. Informe o e-mail da sua conta e enviaremos um link para você criar uma nova senha.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success small border-0 bg-success bg-opacity-10 text-success mb-4 rounded-3">
            <i class="fa-solid fa-check-circle me-1"></i> {{ session('status') }}
            <div class="mt-1 text-secondary">Não chegou? Confira a caixa de spam e aguarde alguns minutos.</div>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label small fw-bold text-uppercase text-secondary">Email</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-secondary"><i class="fa-regular fa-envelope"></i></span>
                <input id="email" type="email" name="email" class="form-control border-start-0 ps-0 @error('email') is-invalid @enderror" placeholder="seu@email.com" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            @error('email')
                <small class="text-danger d-block mt-1">{{ $message }}</small>
            @enderror
        </div>

        <button type="submit" class="btn btn-brand w-100 py-2">
            <i class="fa-regular fa-paper-plane me-1"></i> Enviar link de redefinição
        </button>

        <div class="text-center mt-4 pt-3 border-top">
            <a href="{{ route('login') }}" class="text-decoration-none fw-bold text-success"><i class="fa-solid fa-arrow-left me-1"></i> Voltar ao login</a>
        </div>
    </form>
</x-guest-layout>
