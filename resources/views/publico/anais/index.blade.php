@extends('publico.anais._layout')

@section('titulo', 'Anais')

@section('conteudo')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-secondary">{{ $trabalhos->total() }} trabalho(s) publicado(s)</div>
        @if($anais->pdfPronto())
            <a href="{{ route('anais.pdf', $evento->slug) }}" class="btn btn-success fw-bold">
                <i class="fa-solid fa-file-pdf me-1"></i> Baixar os Anais completos (PDF)
            </a>
        @endif
    </div>

    <form method="GET" class="card-trabalho">
        <div class="row g-2">
            <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar por título, autor ou palavra-chave"></div>
            <div class="col-md-2">
                <select name="area" class="form-select">
                    <option value="">Todas as áreas</option>
                    @foreach($areas as $a) <option value="{{ $a->id }}" @selected((int) request('area') === $a->id)>{{ $a->nome }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="tipo" class="form-select">
                    <option value="">Todos os tipos</option>
                    @foreach(\App\Models\TrabalhoConfig::TIPOS as $v => $r) <option value="{{ $v }}" @selected(request('tipo') === $v)>{{ $r }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="modalidade" class="form-select">
                    <option value="">Modalidade</option>
                    @foreach(\App\Models\Trabalho::MODALIDADES as $v => $r) <option value="{{ $v }}" @selected(request('modalidade') === $v)>{{ $r }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <select name="ordem" class="form-select">
                    <option value="titulo" @selected(request('ordem') !== 'area')>A–Z</option>
                    <option value="area" @selected(request('ordem') === 'area')>Por área</option>
                </select>
                <button class="btn btn-dark"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
        </div>
    </form>

    @forelse($trabalhos as $t)
        <article class="card-trabalho">
            <div class="d-flex flex-wrap gap-2 mb-2">
                @if($t->area) <span class="chip">{{ $t->area->nome }}</span> @endif
                <span class="chip">{{ \App\Models\TrabalhoConfig::TIPOS[$t->tipo_trabalho] ?? 'Trabalho' }}</span>
                <span class="chip">{{ \App\Models\Trabalho::MODALIDADES[$t->modalidade_apresentacao] ?? '' }}</span>
            </div>
            <h5 class="fw-bold mb-1"><a href="{{ route('anais.show', [$evento->slug, $t->codigo]) }}" class="text-decoration-none text-dark">{{ $t->titulo }}</a></h5>
            <div class="text-secondary small mb-2">{{ $t->autores->whereIn('tipo_autor', ['autor_principal', 'coautor'])->pluck('nome_completo')->implode('; ') }}</div>
            <p class="small mb-2">{{ \Illuminate\Support\Str::limit($t->resumo, 280) }}</p>
            <div class="small text-secondary mb-2">{{ implode(' · ', $t->palavras_chave ?? []) }}</div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('anais.show', [$evento->slug, $t->codigo]) }}" class="btn btn-sm btn-outline-success">Ver detalhes</a>
                @if($pdfs->arquivoPublico($t))
                    <a href="{{ route('anais.trabalho.pdf', [$evento->slug, $t->codigo]) }}" class="btn btn-sm btn-success"><i class="fa-solid fa-file-pdf me-1"></i> PDF</a>
                @endif
            </div>
        </article>
    @empty
        <div class="card-trabalho text-center text-muted py-5">Nenhum trabalho encontrado.</div>
    @endforelse

    <div class="mt-3">{{ $trabalhos->links() }}</div>
@endsection
