@props(['titulo' => null, 'descricao' => null, 'marca' => '#059669', 'mascara' => false, 'ativo' => null, 'semMoldura' => false])
{{--
  Moldura das páginas públicas (home, página do evento, Anais): menu fixo, tokens de cor e rodapé completo.
  Uso: <x-publico-layout titulo="..." :marca="$cor"> ...conteúdo... </x-publico-layout>
  Slots opcionais: <x-slot:meta> (tags no <head>), <x-slot:scripts> (scripts no fim).
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ? $titulo . ' · ' : '' }}{{ config('app.name', 'Meus Eventos IF') }}</title>
    @if($descricao)<meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($descricao), 200) }}">@endif
    <meta name="theme-color" content="#064e3b">
    {{ $meta ?? '' }}

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @if($mascara)
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    @endif

    <style>
        :root {
            --brand: {{ $marca }};
            --brand-dark: color-mix(in srgb, var(--brand), black 24%);
            --brand-deep: color-mix(in srgb, var(--brand), black 52%);
            --brand-light: color-mix(in srgb, var(--brand), white 91%);
            --ink: #0f172a; --ink-2: #334155; --muted: #64748b; --line: #e4e9ef; --bg: #f5f8f6;
            --r-md: 14px; --r-lg: 22px;
            --sh-1: 0 1px 2px rgba(15,23,42,.04), 0 1px 3px rgba(15,23,42,.06);
            --sh-2: 0 6px 10px -4px rgba(15,23,42,.06), 0 18px 36px -14px rgba(15,23,42,.16);
            --bs-link-color-rgb: 4,120,87; --bs-link-hover-color-rgb: 6,78,59;
        }
        html { scroll-behavior: smooth; scroll-padding-top: 84px; }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: var(--bg); color: var(--ink-2); -webkit-font-smoothing: antialiased; overflow-x: hidden; }
        h1, h2, h3, h4, h5 { color: var(--ink); letter-spacing: -.02em; }
        .container { max-width: 1180px; }

        /* Menu */
        .pub-nav { position: sticky; top: 0; z-index: 1000; background: rgba(255,255,255,.92); backdrop-filter: blur(12px); border-bottom: 1px solid var(--line); }
        .pub-nav .navbar { padding: .7rem 0; }
        .pub-marca { display: flex; align-items: center; gap: .6rem; text-decoration: none; font-weight: 800; color: var(--ink); letter-spacing: -.02em; font-size: 1.1rem; }
        .pub-marca-icone { width: 36px; height: 36px; border-radius: 11px; background: linear-gradient(135deg, #34d399, #059669); color: #fff; display: grid; place-items: center; font-size: 1rem; box-shadow: 0 6px 12px -4px rgba(5,150,105,.55); }
        .pub-marca em { font-style: normal; color: #059669; }
        .pub-nav .nav-link { font-weight: 600; font-size: .92rem; color: var(--ink-2); padding: .5rem .9rem; border-radius: 10px; }
        .pub-nav .nav-link:hover, .pub-nav .nav-link.ativo { color: #047857; background: #ecfdf5; }
        /* Botões: cor única, planos, sem sombra, degradê nem movimento */
        .btn { font-weight: 700; border-radius: 12px; box-shadow: none !important; transform: none !important; background-image: none !important; transition: background-color .12s, color .12s, border-color .12s; }
        .btn:focus-visible { outline: 3px solid rgba(16,185,129,.4); outline-offset: 2px; }
        .btn-brand { background: #059669; color: #fff; border: 0; }
        .btn-brand:hover, .btn-brand:focus { background: #047857; color: #fff; }
        .btn-outline-brand { border: 2px solid #059669; color: #047857; background: transparent; }
        .btn-outline-brand:hover { background: #059669; color: #fff; }

        /* Cartões, selos */
        .cartao { background: #fff; border: 1px solid var(--line); border-radius: var(--r-lg); box-shadow: var(--sh-1); }
        .selo { display: inline-flex; align-items: center; gap: .4rem; font-size: .72rem; font-weight: 700; letter-spacing: .02em; padding: .28rem .7rem; border-radius: 999px; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .selo-azul { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .selo-ambar { background: #fffbeb; color: #b45309; border-color: #fde68a; }
        .selo-cinza { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
        .secao { padding: 4.25rem 0; }
        .secao-titulo { font-weight: 800; font-size: clamp(1.5rem, 3vw, 2rem); margin-bottom: .4rem; }
        .secao-sub { color: var(--muted); max-width: 640px; }
        .rotulo-secao { font-size: .75rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #059669; }

        /* Formulários */
        .form-control, .form-select { border-radius: 12px; padding: .7rem .95rem; border: 1px solid #d5dce5; background: #fff; font-size: .95rem; }
        .form-control:focus, .form-select:focus { border-color: #10b981; box-shadow: 0 0 0 4px rgba(16,185,129,.15); }

        /* Rodapé */
        .pub-rodape { background: linear-gradient(180deg, #053b2d, #022c22); color: #a7c4b8; margin-top: 4rem; }
        .pub-rodape h6 { color: #fff; font-weight: 700; font-size: .9rem; margin-bottom: 1rem; }
        .pub-rodape a { color: #a7c4b8; text-decoration: none; font-size: .9rem; display: block; padding: .22rem 0; }
        .pub-rodape a:hover { color: #fff; }
        .pub-rodape-fim { border-top: 1px solid rgba(255,255,255,.1); font-size: .82rem; }
        @media (max-width: 575.98px) { .secao { padding: 3rem 0; } }
    </style>
    {{ $estilos ?? '' }}
    @include('partials.favicon')
</head>
<body>
    @unless($semMoldura)
    <header class="pub-nav">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="pub-marca" href="{{ route('home') }}">
                    <span class="pub-marca-icone"><i class="fa-solid fa-graduation-cap"></i></span>
                    <span>MeusEventos<em>IF</em></span>
                </a>
                <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#pubMenu" aria-label="Abrir menu"><i class="fa-solid fa-bars"></i></button>
                <div class="collapse navbar-collapse" id="pubMenu">
                    <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1 pt-3 pt-lg-0">
                        <li class="nav-item"><a class="nav-link {{ $ativo === 'eventos' ? 'ativo' : '' }}" href="{{ route('home') }}#eventos">Eventos</a></li>
                        <li class="nav-item"><a class="nav-link {{ $ativo === 'anais' ? 'ativo' : '' }}" href="{{ route('home') }}#anais">Anais</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#como-funciona">Como funciona</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#validar">Validar certificado</a></li>
                        <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                            @auth
                                <a href="{{ route('dashboard') }}" class="btn btn-brand px-4"><i class="fa-solid fa-user-circle me-1"></i> Minha área</a>
                            @else
                                <a href="{{ route('login') }}" class="btn btn-outline-brand px-4">Entrar</a>
                            @endauth
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    @endunless

    {{ $slot }}

    @unless($semMoldura)
    <footer class="pub-rodape">
        <div class="container py-5">
            <div class="row g-4">
                <div class="col-lg-5">
                    <a class="pub-marca text-white mb-3" href="{{ route('home') }}">
                        <span class="pub-marca-icone"><i class="fa-solid fa-graduation-cap"></i></span>
                        <span style="color:#fff">MeusEventos<em style="color:#34d399">IF</em></span>
                    </a>
                    <p class="small mb-3" style="max-width: 400px;">Plataforma do Instituto Federal do Pará – Campus Óbidos para divulgar eventos, registrar presença, receber trabalhos acadêmicos e emitir certificados verificáveis.</p>
                    <div class="small"><i class="fa-solid fa-location-dot me-2 text-success"></i> Óbidos, Pará</div>
                </div>
                <div class="col-6 col-lg-3 offset-lg-1">
                    <h6>Navegue</h6>
                    <a href="{{ route('home') }}#eventos">Próximos eventos</a>
                    <a href="{{ route('home') }}#anais">Anais publicados</a>
                    <a href="{{ route('home') }}#como-funciona">Como funciona</a>
                    <a href="{{ route('home') }}#validar">Validar certificado</a>
                </div>
                <div class="col-6 col-lg-3">
                    <h6>Institucional</h6>
                    <a href="https://obidos.ifpa.edu.br/" target="_blank" rel="noopener">Site do Campus <i class="fa-solid fa-arrow-up-right-from-square ms-1 small"></i></a>
                    <a href="{{ route('privacidade') }}">Política de privacidade</a>
                    <a href="{{ route('login') }}">Acesso restrito</a>
                </div>
            </div>
        </div>
        <div class="pub-rodape-fim">
            <div class="container py-3 d-flex flex-wrap justify-content-between gap-2">
                <span>&copy; {{ date('Y') }} Instituto Federal do Pará – Campus Óbidos</span>
                <span>Seus dados são tratados conforme a LGPD.</span>
            </div>
        </div>
    </footer>
    @endunless

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    {{ $scripts ?? '' }}
</body>
</html>
