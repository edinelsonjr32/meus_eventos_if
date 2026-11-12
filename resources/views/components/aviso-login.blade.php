@props(['acao' => 'preencher e enviar este formulário'])
{{-- Aviso para visitantes: o formulário pode ser visto, mas preencher/enviar exige conta. Não aparece para quem está logado. --}}
@guest
    <div class="alert alert-warning border-0 shadow-sm d-flex flex-column flex-md-row align-items-md-center gap-3 mb-4" role="alert" style="background:#fffbeb">
        <div class="flex-grow-1"><div class="fw-bold"><i class="fa-solid fa-lock me-1"></i> Entre ou crie sua conta para continuar</div>
            <div class="small">Você pode ver o formulário abaixo, mas para {{ $acao }} é preciso estar logado. Depois de entrar, você volta para esta página.</div></div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('login') }}" class="btn btn-primary-custom btn-sm fw-bold"><i class="fa-solid fa-right-to-bracket me-1"></i> Entrar</a>
            <a href="{{ route('register') }}" class="btn btn-outline-success btn-sm fw-bold"><i class="fa-solid fa-user-plus me-1"></i> Criar conta</a>
        </div>
    </div>
@endguest
