@props(['titulo' => null])
{{-- Usa o layout da área logada para quem está logado e a moldura pública para visitantes (formulários abertos com aviso de login). --}}
@auth
    <x-app-layout>{{ $slot }}</x-app-layout>
@else
    <x-publico-layout :titulo="$titulo" ativo="">
        <x-slot:estilos>
            <style>
                body { background: radial-gradient(900px 380px at 90% -5%, rgba(16,185,129,.10), transparent 70%), var(--bg, #f8fafc); }
                .visitante-pagina { padding: 104px 0 56px; }
                .glass-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.25rem; }
                .btn-primary-custom { background: var(--brand-dark, #047857); color: #fff; border: 0; border-radius: 10px; padding: .55rem 1rem; font-weight: 600; }
                .btn-primary-custom:hover { background: var(--brand-deep, #064e3b); color: #fff; }
                .texto-quebra { white-space: pre-line; overflow-wrap: anywhere; word-break: break-word; max-width: 100%; }
                .conteudo-rico p { margin-bottom: .5rem; } .conteudo-rico ul, .conteudo-rico ol { padding-left: 1.25rem; margin-bottom: .5rem; } .conteudo-rico blockquote { border-left: 3px solid #cbd5e1; padding-left: .75rem; color: #475569; margin: .5rem 0; } .conteudo-rico > :last-child { margin-bottom: 0; } .conteudo-rico { overflow-wrap: anywhere; }
                [x-cloak] { display: none !important; }
            </style>
        </x-slot:estilos>
        <main class="visitante-pagina"><div class="container">{{ $slot }}</div></main>
    </x-publico-layout>
@endauth
