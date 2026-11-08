@extends('publico.anais._layout')

@section('titulo', $trabalho->titulo)

@section('meta')
    {{-- Metadados para indexação acadêmica (Google Scholar) --}}
    <meta name="citation_title" content="{{ $trabalho->titulo }}">
    @foreach($trabalho->autores->whereIn('tipo_autor', ['autor_principal', 'coautor']) as $a)
        <meta name="citation_author" content="{{ $a->nome_completo }}">
    @endforeach
    @if($anais->dadosVisiveis()) <meta name="citation_publication_date" content="{{ $anais->ano }}"> @endif
    <meta name="citation_conference_title" content="{{ $evento->titulo }}">
    <meta name="citation_abstract_html_url" content="{{ route('anais.show', [$evento->slug, $trabalho->codigo]) }}">
    @if($temPdf) <meta name="citation_pdf_url" content="{{ route('anais.trabalho.pdf', [$evento->slug, $trabalho->codigo]) }}"> @endif
    @if($trabalho->palavras_chave) <meta name="citation_keywords" content="{{ implode('; ', $trabalho->palavras_chave) }}"> @endif
    @if($anais->dadosVisiveis() && $anais->issn) <meta name="citation_issn" content="{{ $anais->issn }}"> @endif
    @if($anais->dadosVisiveis() && $anais->isbn) <meta name="citation_isbn" content="{{ $anais->isbn }}"> @endif
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($trabalho->resumo), 200) }}">
@endsection

@section('conteudo')
    <a href="{{ route('anais.index', $evento->slug) }}" class="text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i> Todos os trabalhos</a>

    <article class="card-trabalho mt-3">
        <div class="d-flex flex-wrap gap-2 mb-2">
            @if($trabalho->area) <span class="chip">{{ $trabalho->area->nome }}</span> @endif
            <span class="chip">{{ \App\Models\TrabalhoConfig::TIPOS[$trabalho->tipo_trabalho] ?? 'Trabalho' }}</span>
            <span class="chip">{{ $trabalho->codigo }}</span>
        </div>
        <h2 class="fw-bold">{{ $trabalho->titulo }}</h2>

        <ul class="list-unstyled small mb-3">
            @foreach($trabalho->autores as $a)
                <li>
                    <strong>{{ $a->nome_completo }}</strong>
                    <span class="text-secondary">— {{ $a->tipoRotulo() }}@if($a->instituicao), {{ $a->instituicao }}@endif</span>
                </li>
            @endforeach
        </ul>

        <h6 class="fw-bold">Resumo</h6>
        <p style="white-space: pre-line;">{{ $trabalho->resumo }}</p>
        @if($trabalho->abstract)
            <h6 class="fw-bold">Abstract</h6>
            <p style="white-space: pre-line;">{{ $trabalho->abstract }}</p>
        @endif
        <p class="small"><strong>Palavras-chave:</strong> {{ implode('; ', $trabalho->palavras_chave ?? []) }}</p>

        @if($temPdf)
            <a href="{{ route('anais.trabalho.pdf', [$evento->slug, $trabalho->codigo]) }}" class="btn btn-success fw-bold"><i class="fa-solid fa-file-pdf me-1"></i> Baixar PDF do trabalho</a>
        @endif
    </article>

    @if($citacao)
    <div class="card-trabalho">
        <h6 class="fw-bold"><i class="fa-solid fa-quote-left me-1 text-success"></i> Como citar (ABNT)</h6>
        <div class="citacao mb-2" id="citacao">{{ $citacao }}</div>
        <button type="button" class="btn btn-sm btn-outline-secondary"
                onclick="navigator.clipboard.writeText(document.getElementById('citacao').innerText).then(() => { this.innerText = 'Copiado!'; })">
            <i class="fa-regular fa-copy me-1"></i> Copiar citação
        </button>
    </div>
    @else
    <div class="card-trabalho text-secondary small"><i class="fa-solid fa-quote-left me-1 text-success"></i> A referência para citação (ABNT) será disponibilizada junto com os dados de publicação, após o encerramento do evento.</div>
    @endif

    <div class="card-trabalho">
        <h6 class="fw-bold"><i class="fa-solid fa-award me-1 text-success"></i> Certificado do autor</h6>
        <p class="small text-secondary">Se você é autor deste trabalho, informe o CPF e o e-mail usados na submissão para baixar o seu certificado.</p>

        @if(session('erro_certificado')) <div class="alert alert-warning small py-2">{{ session('erro_certificado') }}</div> @endif
        @if($errors->any()) <div class="alert alert-danger small py-2">{{ $errors->first() }}</div> @endif

        <form action="{{ route('anais.certificado', [$evento->slug, $trabalho->codigo]) }}" method="POST" class="row g-2">
            @csrf
            <div class="col-md-4"><input type="text" name="cpf" value="{{ old('cpf') }}" class="form-control" placeholder="CPF (000.000.000-00)" maxlength="14" required></div>
            <div class="col-md-5"><input type="email" name="email" class="form-control" placeholder="E-mail da submissão" required></div>
            <div class="col-md-3"><button class="btn btn-outline-success w-100 fw-bold">Obter certificado</button></div>
        </form>
    </div>
@endsection
