<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') — {{ $anais->titulo }}</title>
    @yield('meta')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0fdf4; font-family: system-ui, -apple-system, sans-serif; color: #1f2937; }
        .hero { background: linear-gradient(135deg, #064e3b, #047857); color: #fff; padding: 2.5rem 0; }
        .card-trabalho { background: #fff; border-radius: 16px; padding: 1.25rem 1.5rem; box-shadow: 0 6px 20px -8px rgba(6,78,59,.2); margin-bottom: 1rem; }
        .citacao { background: #f9fafb; border-left: 4px solid #047857; padding: .6rem .9rem; font-size: .85rem; border-radius: 6px; }
        .chip { background: #ecfdf5; color: #065f46; border-radius: 999px; padding: .15rem .6rem; font-size: .75rem; font-weight: 600; }
    </style>
</head>
<body>
    <header class="hero">
        <div class="container">
            <a href="{{ route('evento.publico.show', $evento->slug) }}" class="text-white-50 small text-decoration-none"><i class="fa-solid fa-arrow-left me-1"></i> {{ $evento->titulo }}</a>
            <h1 class="fw-bold mt-2 mb-1">{{ $anais->titulo }}</h1>
            <div class="opacity-75">
                @if($anais->dadosVisiveis())
                    {{ $anais->edicao }} @if($anais->edicao) · @endif {{ collect([$anais->local_publicacao, $anais->ano])->filter()->implode(', ') }}
                    @if($anais->issn) · ISSN {{ $anais->issn }} @endif @if($anais->isbn) · ISBN {{ $anais->isbn }} @endif
                @else
                    {{ $anais->edicao ?: $evento->titulo }}
                @endif
            </div>
        </div>
    </header>

    <main class="container py-4">
        @yield('conteudo')
    </main>

    <footer class="text-center text-secondary small pb-4">&copy; {{ date('Y') }} Instituto Federal do Pará - Campus Óbidos · <a href="{{ route('privacidade') }}" class="text-secondary">Política de Privacidade</a></footer>
</body>
</html>
