{{-- Layout das páginas de erro. Autossuficiente (CSS inline, sem CDN) para funcionar mesmo quando o resto do sistema falha.
     Não consulta banco nem sessão: pode ser exibido em erro 500 e em manutenção. --}}
@php
    $logado = false;
    if (($usaConta ?? false)) {
        try { $logado = auth()->check(); } catch (\Throwable $e) { $logado = false; }
    }
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('titulo') · {{ config('app.name', 'Meus Eventos IF') }}</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:linear-gradient(160deg,#f0fdf4 0%,#dcfce7 100%);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#1f2937;line-height:1.55}
        .cartao{width:100%;max-width:560px;background:#fff;border-radius:22px;padding:40px 36px 34px;text-align:center;box-shadow:0 18px 50px -18px rgba(6,78,59,.35);border-top:6px solid #10b981}
        .marca{font-size:.85rem;font-weight:700;letter-spacing:.04em;color:#047857;margin-bottom:18px;text-transform:uppercase}
        .codigo{font-size:4.6rem;font-weight:800;line-height:1;color:#064e3b;letter-spacing:-2px}
        .codigo small{display:block;font-size:2.4rem;line-height:1.2;margin-bottom:4px}
        h1{font-size:1.5rem;color:#064e3b;margin:14px 0 8px}
        p{color:#4b5563;margin:6px 0}
        .dica{margin-top:16px;padding:12px 16px;background:#f0fdf4;border-radius:12px;font-size:.93rem;color:#065f46;text-align:left}
        .acoes{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:26px}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 22px;border-radius:12px;font:600 1rem inherit;font-family:inherit;text-decoration:none;cursor:pointer;border:2px solid #059669;transition:.15s}
        .btn.p{background:#059669;color:#fff}.btn.p:hover{background:#047857;border-color:#047857}
        .btn.s{background:#fff;color:#047857}.btn.s:hover{background:#ecfdf5}
        .btn:focus-visible{outline:3px solid #34d399;outline-offset:3px}
        .rodape{margin-top:26px;font-size:.8rem;color:#6b7280}
        @media(max-width:480px){.cartao{padding:30px 22px}.codigo{font-size:3.8rem}.btn{width:100%;justify-content:center}}
    </style>
</head>
<body>
    <main class="cartao" role="main">
        <div class="marca">{{ config('app.name', 'Meus Eventos IF') }}</div>
        <div class="codigo" aria-hidden="true">@yield('codigo')</div>
        <h1>@yield('titulo')</h1>
        <p>@yield('mensagem')</p>
        @hasSection('dica')<div class="dica">@yield('dica')</div>@endif

        <div class="acoes">
            @hasSection('acao-principal')
                @yield('acao-principal')
            @else
                <button type="button" class="btn p" id="btn-voltar">&larr; Voltar</button>
            @endif
            @if($logado)
                <a class="btn s" href="{{ url('/dashboard') }}">Minha área</a>
            @else
                <a class="btn s" href="{{ url('/') }}">Página inicial</a>
            @endif
        </div>
        <p class="rodape">IFPA Campus Óbidos · @yield('rodape')</p>
    </main>
    <script>
        (function () {
            var b = document.getElementById('btn-voltar');
            if (!b) return;
            b.addEventListener('click', function () {
                // volta uma página se houver histórico do próprio site; senão, vai ao início
                var mesmoSite = document.referrer && document.referrer.indexOf(location.origin) === 0;
                if (window.history.length > 1 && mesmoSite) { window.history.back(); } else { window.location.href = '{{ url('/') }}'; }
            });
        })();
    </script>
</body>
</html>
