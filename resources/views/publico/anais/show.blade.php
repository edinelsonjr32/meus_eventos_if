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
    <div class="pt-4 pb-2"><a href="{{ route('anais.index', $evento->slug) }}" class="text-decoration-none small fw-bold"><i class="fa-solid fa-arrow-left me-1"></i> Todos os trabalhos</a></div>

    <div class="row g-4">
        <div class="col-lg-8">
            <article class="cartao p-4 p-md-5 mb-4">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @if($trabalho->area) <span class="chip">{{ $trabalho->area->nome }}</span> @endif
                    <span class="chip">{{ \App\Models\TrabalhoConfig::TIPOS[$trabalho->tipo_trabalho] ?? 'Trabalho' }}</span>
                    <span class="chip cinza">{{ $trabalho->codigo }}</span>
                </div>
                <h2 class="fw-bold mb-4" style="line-height:1.3;">{{ $trabalho->titulo }}</h2>

                <div class="mb-4">
                    @foreach($trabalho->autores as $a)
                        <div class="d-flex gap-3 align-items-start py-2" style="border-bottom:1px solid #f1f5f9;">
                            <span class="flex-shrink-0 d-grid" style="width:38px;height:38px;border-radius:50%;background:#ecfdf5;color:#047857;place-items:center;font-weight:800;">{{ mb_strtoupper(mb_substr($a->nome_completo, 0, 1)) }}</span>
                            <div>
                                <strong>{{ $a->nome_completo }}</strong>
                                <div class="small text-muted">{{ $a->tipoRotulo() }}@if($a->instituicao) · {{ $a->instituicao }}@endif</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <h6 class="fw-bold text-uppercase small" style="letter-spacing:.06em;color:#059669;">Resumo</h6>
                <p class="an-resumo" style="white-space: pre-line;">{{ $trabalho->resumo }}</p>
                @if($trabalho->abstract)
                    <h6 class="fw-bold text-uppercase small mt-4" style="letter-spacing:.06em;color:#059669;">Abstract</h6>
                    <p class="an-resumo" style="white-space: pre-line;">{{ $trabalho->abstract }}</p>
                @endif
                @if(!empty($trabalho->palavras_chave))
                    <div class="mt-4"><strong class="small">Palavras-chave:</strong> {{ implode('; ', $trabalho->palavras_chave) }}</div>
                @endif
            </article>

            <div class="cartao p-4">
                <h6 class="fw-bold"><i class="fa-solid fa-award me-2 text-success"></i> Certificado do autor</h6>
                <p class="small text-muted">Se você é autor deste trabalho, informe o CPF e o e-mail usados na submissão para baixar o seu certificado.</p>

                @if(session('erro_certificado')) <div class="alert alert-warning small py-2">{{ session('erro_certificado') }}</div> @endif
                @if($errors->any()) <div class="alert alert-danger small py-2">{{ $errors->first() }}</div> @endif

                <form action="{{ route('anais.certificado', [$evento->slug, $trabalho->codigo]) }}" method="POST" class="row g-2">
                    @csrf
                    <div class="col-md-4"><input type="text" name="cpf" value="{{ old('cpf') }}" class="form-control" placeholder="CPF (000.000.000-00)" maxlength="14" required></div>
                    <div class="col-md-5"><input type="email" name="email" class="form-control" placeholder="E-mail da submissão" required></div>
                    <div class="col-md-3"><button class="btn btn-outline-brand w-100">Obter certificado</button></div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <aside class="an-lado d-flex flex-column gap-4">
                @if($temPdf)
                    <a href="{{ route('anais.trabalho.pdf', [$evento->slug, $trabalho->codigo]) }}" class="btn btn-brand py-3"><i class="fa-solid fa-file-pdf me-1"></i> Baixar PDF do trabalho</a>
                @endif

                <div class="cartao p-4">
                    <h6 class="fw-bold mb-2">Sobre este trabalho</h6>
                    <div class="an-dado"><span>Protocolo</span><span>{{ $trabalho->codigo }}</span></div>
                    <div class="an-dado"><span>Categoria</span><span>{{ \App\Models\TrabalhoConfig::TIPOS[$trabalho->tipo_trabalho] ?? '—' }}</span></div>
                    @if($trabalho->area)<div class="an-dado"><span>Área</span><span>{{ $trabalho->area->nome }}</span></div>@endif
                    <div class="an-dado"><span>Modalidade</span><span>{{ \App\Models\Trabalho::MODALIDADES[$trabalho->modalidade_apresentacao] ?? '—' }}</span></div>
                    <div class="an-dado"><span>Evento</span><span>{{ $evento->titulo }}</span></div>
                </div>

                @if($citacao)
                    <div class="cartao p-4">
                        <h6 class="fw-bold"><i class="fa-solid fa-quote-left me-2 text-success"></i> Como citar (ABNT)</h6>
                        <div class="citacao mb-3" id="citacao">{{ $citacao }}</div>
                        <button type="button" class="btn btn-sm btn-outline-brand"
                                onclick="navigator.clipboard.writeText(document.getElementById('citacao').innerText).then(() => { this.innerText = 'Copiado!'; })">
                            <i class="fa-regular fa-copy me-1"></i> Copiar citação
                        </button>
                    </div>
                @else
                    <div class="cartao p-4 small text-muted"><i class="fa-solid fa-quote-left me-1 text-success"></i> A referência para citação (ABNT) será disponibilizada junto com os dados de publicação, após o encerramento do evento.</div>
                @endif
            </aside>
        </div>
    </div>
@endsection
