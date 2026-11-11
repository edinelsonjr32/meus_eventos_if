{{-- Moldura dos Anais públicos: mesmo menu e rodapé do portal, com cabeçalho da edição --}}
<x-publico-layout :titulo="trim($__env->yieldContent('titulo')) . ' — ' . $anais->titulo" :marca="$evento->configuracoes['cor_fundo'] ?? '#059669'" ativo="anais">
    <x-slot:meta>
        @yield('meta')
    </x-slot:meta>
    <x-slot:estilos>
        <style>
            .an-hero { position: relative; color: #fff; padding: 2.8rem 0 3rem; background: radial-gradient(800px 360px at 90% 0%, rgba(52,211,153,.25), transparent 60%), linear-gradient(135deg, #053b2d, #065f46 60%, #047857); overflow: hidden; }
            .an-hero::before { content: ""; position: absolute; inset: 0; background-image: radial-gradient(rgba(255,255,255,.1) 1px, transparent 1px); background-size: 28px 28px; opacity: .5; }
            .an-hero > .container { position: relative; }
            .an-hero h1 { color: #fff; font-weight: 800; font-size: clamp(1.7rem, 3.8vw, 2.6rem); line-height: 1.15; }
            .an-trilha { font-size: .83rem; color: rgba(255,255,255,.75); } .an-trilha a { color: rgba(255,255,255,.88); text-decoration: none; } .an-trilha a:hover { color: #fff; }
            .an-chip { display: inline-flex; align-items: center; gap: .45rem; font-size: .8rem; font-weight: 600; padding: .38rem .85rem; border-radius: 999px; background: rgba(255,255,255,.13); border: 1px solid rgba(255,255,255,.24); color: #e8fff5; }
            .an-stat b { display: block; font-size: 1.7rem; font-weight: 800; line-height: 1; } .an-stat span { font-size: .78rem; color: #a7f3d0; font-weight: 600; }
            .btn-an { background: #fff; color: #047857; padding: .75rem 1.4rem; box-shadow: 0 14px 28px -12px rgba(0,0,0,.5); } .btn-an:hover { background: #ecfdf5; color: #065f46; transform: translateY(-1px); }

            .an-filtros { padding: 1rem; margin-top: -1.6rem; position: relative; z-index: 5; box-shadow: var(--sh-2); }
            .an-card { padding: 1.4rem 1.6rem; margin-bottom: 1rem; border-left: 4px solid var(--brand); transition: box-shadow .2s, transform .2s; }
            .an-card:hover { box-shadow: var(--sh-2); transform: translateY(-2px); }
            .an-card h2 { font-size: 1.2rem; font-weight: 700; line-height: 1.35; } .an-card h2 a { color: var(--ink); text-decoration: none; } .an-card h2 a:hover { color: #047857; }
            .an-autores { font-size: .86rem; color: var(--muted); }
            .an-tag { font-size: .74rem; color: #047857; background: #f0fdf7; border: 1px solid #d1fae5; padding: .15rem .55rem; border-radius: 8px; font-weight: 600; }
            .chip { background: #ecfdf5; color: #065f46; border-radius: 999px; padding: .2rem .7rem; font-size: .74rem; font-weight: 700; border: 1px solid #a7f3d0; }
            .chip.cinza { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
            .an-aviso { background: #ecfdf5; border: 1px solid #a7f3d0; border-left: 4px solid #10b981; border-radius: 14px; padding: .9rem 1.1rem; color: #065f46; font-size: .9rem; }
            .an-lado { position: sticky; top: 84px; }
            .an-dado { display: flex; justify-content: space-between; gap: 1rem; padding: .6rem 0; border-bottom: 1px solid #eef1f5; font-size: .88rem; } .an-dado:last-child { border-bottom: 0; }
            .an-dado span:first-child { color: var(--muted); font-weight: 600; } .an-dado span:last-child { text-align: right; font-weight: 600; color: var(--ink); overflow-wrap: anywhere; }
            .citacao { background: #f8fafc; border-left: 4px solid #059669; padding: .8rem 1rem; font-size: .88rem; border-radius: 10px; line-height: 1.6; }
            .an-resumo { line-height: 1.85; font-size: 1.02rem; color: var(--ink-2); }
            @media (max-width: 991.98px) { .an-lado { position: static; } }
        </style>
    </x-slot:estilos>

    <header class="an-hero">
        <div class="container">
            <div class="an-trilha mb-3">
                <a href="{{ route('home') }}">Início</a> <i class="fa-solid fa-chevron-right mx-1" style="font-size:.6rem"></i>
                <a href="{{ route('evento.publico.show', $evento->slug) }}">{{ \Illuminate\Support\Str::limit($evento->titulo, 36) }}</a> <i class="fa-solid fa-chevron-right mx-1" style="font-size:.6rem"></i>
                <span>Anais</span>
            </div>
            <div class="row align-items-end g-4">
                <div class="col-lg-8">
                    <div class="mb-2"><span class="an-chip"><i class="fa-solid fa-book-open"></i> Anais do evento</span></div>
                    <h1 class="mb-2">{{ $anais->titulo }}</h1>
                    <div style="color:#d1fae5;">
                        @if($anais->dadosVisiveis())
                            {{ $anais->edicao }} @if($anais->edicao) · @endif {{ collect([$anais->local_publicacao, $anais->ano])->filter()->implode(', ') }}
                            @if($anais->editora) · {{ $anais->editora }} @endif
                        @else
                            {{ $anais->edicao ?: $evento->titulo }}
                        @endif
                    </div>
                    @if($anais->dadosVisiveis() && ($anais->issn || $anais->isbn || $anais->doi_prefixo))
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            @if($anais->issn)<span class="an-chip">ISSN {{ $anais->issn }}</span>@endif
                            @if($anais->isbn)<span class="an-chip">ISBN {{ $anais->isbn }}</span>@endif
                            @if($anais->doi_prefixo)<span class="an-chip">DOI {{ $anais->doi_prefixo }}</span>@endif
                        </div>
                    @endif
                </div>
                <div class="col-lg-4 d-flex flex-wrap align-items-end justify-content-lg-end gap-4">
                    @isset($totalTrabalhos)<div class="an-stat"><b>{{ $totalTrabalhos }}</b><span>trabalhos</span></div>@endisset
                    @isset($totalAreas)@if($totalAreas)<div class="an-stat"><b>{{ $totalAreas }}</b><span>áreas</span></div>@endif @endisset
                </div>
            </div>
            @if($anais->dadosVisiveis() && $anais->pdfPronto())
                <a href="{{ route('anais.pdf', $evento->slug) }}" class="btn btn-an mt-4"><i class="fa-solid fa-file-pdf me-1"></i> Baixar os Anais completos (PDF)</a>
            @endif
        </div>
    </header>

    <main class="container pb-2">
        @yield('conteudo')
    </main>
</x-publico-layout>
