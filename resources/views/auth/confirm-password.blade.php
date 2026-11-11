<x-guest-layout>
    <div class="text-center mb-4">
        <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
            <i class="fa-solid fa-shield-halved fs-3"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Confirme sua senha</h4>
        <p class="text-secondary small mb-0">Esta é uma área protegida. Confirme sua senha para continuar.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="mb-4">
            <x-campo-senha name="password" label="Senha" placeholder="Sua senha" autocomplete="current-password" :autofocus="true" />
        </div>

        <button type="submit" class="btn btn-brand w-100 py-2">Confirmar</button>
    </form>
</x-guest-layout>
