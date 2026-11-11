@extends('publico.anais._layout')

@section('titulo', 'Trabalhos Publicados')

@section('conteudo')
    <form method="GET" class="cartao an-filtros">
        <div class="row g-2 align-items-center">
            <div class="col-lg-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control border-start-0 ps-0" placeholder="Buscar por título, autor ou palavra-chave">
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <select name="area" class="form-select">
                    <option value="">Todas as áreas</option>
                    @foreach($areas as $a) <option value="{{ $a->id }}" @selected((int) request('area') === $a->id)>{{ $a->nome }}</option> @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <select name="tipo" class="form-select">
                    <option value="">Todas as categorias</option>
                    @foreach(\App\Models\TrabalhoConfig::TIPOS as $v => $r) <option value="{{ $v }}" @selected(request('tipo') === $v)>{{ $r }}</option> @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <select name="modalidade" class="form-select">
                    <option value="">Modalidade</option>
                    @foreach(\App\Models\Trabalho::MODALIDADES as $v => $r) <option value="{{ $v }}" @selected(request('modalidade') === $v)>{{ $r }}</option> @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2 d-flex gap-2">
                <select name="ordem" class="form-select">
                    <option value="titulo" @selected(request('ordem') !== 'area')>A–Z</option>
                    <option value="area" @selected(request('ordem') === 'area')>Por área</option>
                </select>
                <button class="btn btn-brand px-3" title="Buscar"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
        </div>
    </form>

    @unless($anais->dadosVisiveis())
        <div class="an-aviso mt-4"><i class="fa-solid fa-circle-info me-1"></i>
            Os dados de publicação (ISBN, ISSN, DOI, editora) e o PDF completo dos Trabalhos Publicados serão divulgados após o encerramento do evento.
            @if($trabalhos->total() === 0) Os trabalhos aparecem aqui conforme forem apresentados. @endif
        </div>
    @endunless

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 my-4">
        <div class="fw-bold">{{ $trabalhos->total() }} trabalho(s) publicado(s)
            @if(request()->hasAny(['q', 'area', 'tipo', 'modalidade']))<a href="{{ route('anais.index', $evento->slug) }}" class="ms-2 small fw-semibold text-decoration-none"><i class="fa-solid fa-xmark me-1"></i>limpar filtros</a>@endif
        </div>
    </div>

    @forelse($trabalhos as $t)
        <article class="cartao an-card">
            <div class="d-flex flex-wrap gap-2 mb-2">
                @if($t->area) <span class="chip">{{ $t->area->nome }}</span> @endif
                <span class="chip">{{ \App\Models\TrabalhoConfig::TIPOS[$t->tipo_trabalho] ?? 'Trabalho' }}</span>
                @if(\App\Models\Trabalho::MODALIDADES[$t->modalidade_apresentacao] ?? null)<span class="chip cinza">{{ \App\Models\Trabalho::MODALIDADES[$t->modalidade_apresentacao] }}</span>@endif
            </div>
            <h2 class="mb-1"><a href="{{ route('anais.show', [$evento->slug, $t->codigo]) }}">{{ $t->titulo }}</a></h2>
            <div class="an-autores mb-2"><i class="fa-regular fa-user me-1"></i>{{ $t->autores->whereIn('tipo_autor', ['autor_principal', 'coautor'])->pluck('nome_completo')->implode('; ') }}</div>
            <p class="small mb-2 text-muted">{{ \Illuminate\Support\Str::limit($t->resumo, 260) }}</p>
            @if(!empty($t->palavras_chave))
                <div class="d-flex flex-wrap gap-1 mb-3">@foreach($t->palavras_chave as $p)<span class="an-tag">{{ $p }}</span>@endforeach</div>
            @endif

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('anais.show', [$evento->slug, $t->codigo]) }}" class="btn btn-sm btn-outline-brand">Ver detalhes</a>
                @if($pdfs->arquivoPublico($t))
                    <a href="{{ route('anais.trabalho.pdf', [$evento->slug, $t->codigo]) }}" class="btn btn-sm btn-brand"><i class="fa-solid fa-file-pdf me-1"></i> PDF</a>
                @endif
            </div>
        </article>
    @empty
        <div class="cartao text-center text-muted py-5"><i class="fa-regular fa-folder-open fs-1 d-block mb-3 opacity-50"></i>Nenhum trabalho encontrado.</div>
    @endforelse

    <div class="mt-3">{{ $trabalhos->links() }}</div>
@endsection
