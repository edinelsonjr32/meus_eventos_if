<x-guest-layout>
    <div class="text-center mb-4">
        <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
            <i class="fa-regular fa-envelope-open fs-3"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Confirme seu e-mail</h4>
        <p class="text-secondary small mb-0">Obrigado por se cadastrar! Clique no link que enviamos ao seu e-mail para confirmar o endereço. Se não recebeu, podemos enviar outro.</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success small border-0 bg-success bg-opacity-10 text-success mb-4 rounded-3">
            <i class="fa-solid fa-check-circle me-1"></i> Enviamos um novo link de confirmação para o e-mail do seu cadastro.
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-brand w-100 py-2">Reenviar e-mail de confirmação</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center mt-4 pt-3 border-top">
        @csrf
        <button type="submit" class="btn btn-link text-decoration-none fw-bold text-success"><i class="fa-solid fa-right-from-bracket me-1"></i> Sair</button>
    </form>
</x-guest-layout>
