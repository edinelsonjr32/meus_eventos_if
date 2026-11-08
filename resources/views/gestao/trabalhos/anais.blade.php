<x-app-layout>
    <x-badge tipo="trabalho" valor="rascunho" class="d-none" />
    <div class="row justify-content-center">
        <div class="col-xl-10">

            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('gestao.trabalhos.index', $evento) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
                    <div>
                        <h3 class="fw-bold m-0">Anais de Trabalhos</h3>
                        <small class="text-secondary">{{ $evento->titulo }}</small>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @if($anais->exists && $anais->publicado())
                        <span class="sb" style="background:#16a34a;color:#fff;border-color:#16a34a;"><i class="fa-solid fa-eye"></i> Visível ao público</span>
                        <span class="sb" style="{{ $anais->dadosVisiveis() ? 'background:#dcfce7;color:#15803d;border-color:#86efac;' : 'background:#fef3c7;color:#b45309;border-color:#fcd34d;' }}">
                            <i class="fa-solid {{ $anais->dadosVisiveis() ? 'fa-book' : 'fa-eye-slash' }}"></i> Dados de publicação {{ $anais->dadosVisiveis() ? 'liberados' : 'ocultos' }}
                        </span>
                        <a href="{{ route('anais.index', $evento->slug) }}" target="_blank" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-arrow-up-right-from-square"></i> Ver página pública</a>
                    @else
                        <span class="sb" style="background:#f1f3f5;color:#495057;border-color:#ced4da;"><i class="fa-solid fa-eye-slash"></i> Oculto (rascunho)</span>
                    @endif
                </div>
            </div>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 small">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div>
            @endif

            <div class="row g-4">
                <div class="col-lg-7">
                    <form action="{{ route('gestao.trabalhos.anais.update', $evento) }}" method="POST" class="glass-card bg-white">
                        @csrf @method('PUT')
                        <h6 class="fw-bold text-success mb-1"><i class="fa-solid fa-book-open me-1"></i> Dados da edição</h6>
                        <p class="small text-muted mb-3">Título, edição, apresentação e comissão podem ser preenchidos agora. <strong>ISSN, ISBN, DOI, editora, local e ano</strong> só vão ao público quando você liberar os dados de publicação (normalmente após o evento).</p>
                        <div class="row g-3">
                            <div class="col-12"><label class="small fw-bold text-secondary">Título *</label>
                                <input type="text" name="titulo" class="form-control" value="{{ old('titulo', $anais->titulo) }}" required></div>
                            <div class="col-md-8"><label class="small fw-bold text-secondary">Edição</label>
                                <input type="text" name="edicao" class="form-control" value="{{ old('edicao', $anais->edicao) }}" placeholder="Ex.: 5ª Semana de Tecnologia"></div>
                            <div class="col-md-4"><label class="small fw-bold text-secondary">Ano *</label>
                                <input type="number" name="ano" class="form-control" value="{{ old('ano', $anais->ano) }}" required></div>
                            <div class="col-md-4"><label class="small fw-bold text-secondary">ISSN</label>
                                <input type="text" name="issn" class="form-control" value="{{ old('issn', $anais->issn) }}" placeholder="0000-0000"></div>
                            <div class="col-md-4"><label class="small fw-bold text-secondary">ISBN</label>
                                <input type="text" name="isbn" class="form-control" value="{{ old('isbn', $anais->isbn) }}"></div>
                            <div class="col-md-4"><label class="small fw-bold text-secondary">Prefixo DOI</label>
                                <input type="text" name="doi_prefixo" class="form-control" value="{{ old('doi_prefixo', $anais->doi_prefixo) }}" placeholder="10.12345"></div>
                            <div class="col-md-5"><label class="small fw-bold text-secondary">Local de publicação</label>
                                <input type="text" name="local_publicacao" class="form-control" value="{{ old('local_publicacao', $anais->local_publicacao) }}"></div>
                            <div class="col-md-7"><label class="small fw-bold text-secondary">Editora / instituição</label>
                                <input type="text" name="editora" class="form-control" value="{{ old('editora', $anais->editora) }}"></div>
                            <div class="col-12"><label class="small fw-bold text-secondary">Apresentação</label>
                                <textarea name="apresentacao" rows="4" class="form-control">{{ old('apresentacao', $anais->apresentacao) }}</textarea></div>
                            <div class="col-12"><label class="small fw-bold text-secondary">Comissão organizadora</label>
                                <textarea name="comissao" rows="4" class="form-control">{{ old('comissao', $anais->comissao) }}</textarea></div>
                        </div>
                        <div class="text-end mt-3"><button class="btn btn-primary-custom fw-bold"><i class="fa-regular fa-floppy-disk me-1"></i> Salvar</button></div>
                    </form>
                </div>

                <div class="col-lg-5">
                    <div class="glass-card bg-white mb-4">
                        <h6 class="fw-bold text-success mb-3">PDF compilado</h6>
                        @if(!$anais->exists)
                            <p class="small text-muted mb-0">Salve os dados da edição para habilitar a geração.</p>
                        @else
                            @if($anais->pdf_status === 'gerando')
                                <div class="alert alert-info small py-2"><i class="fa-solid fa-spinner fa-spin me-1"></i> Gerando... atualize a página em instantes.</div>
                            @elseif($anais->pdf_status === 'erro')
                                <div class="alert alert-danger small py-2">Falha na geração: {{ $anais->pdf_erro }}</div>
                            @elseif($anais->pdfPronto())
                                <p class="small mb-2">Gerado em {{ $anais->pdf_gerado_em->format('d/m/Y H:i') }} · {{ $anais->pdf_paginas }} páginas · {{ number_format($anais->pdf_tamanho / 1048576, 2, ',', '.') }} MB</p>
                                <a href="{{ route('gestao.trabalhos.anais.pdf', $evento) }}" target="_blank" class="btn btn-sm btn-outline-danger w-100 mb-2"><i class="fa-solid fa-file-pdf me-1"></i> Abrir PDF</a>
                            @endif
                            @if($anais->pdf_aviso)
                                <div class="alert alert-warning small py-2" style="white-space: pre-line;">{{ $anais->pdf_aviso }}</div>
                            @endif
                            <form action="{{ route('gestao.trabalhos.anais.gerar', $evento) }}" method="POST">
                                @csrf
                                <button class="btn btn-sm btn-primary-custom w-100 fw-bold" @disabled($anais->pdf_status === 'gerando' || $elegiveis->isEmpty())>
                                    {{ $anais->pdfPronto() ? 'Gerar novamente' : 'Gerar PDF dos Anais' }}
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="glass-card bg-white mb-4">
                        <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-bullhorn me-1"></i> Publicação e visibilidade</h6>

                        {{-- 1) página dos Anais --}}
                        <div class="border rounded-3 p-3 mb-3 {{ $anais->exists && $anais->publicado() ? 'border-success bg-success bg-opacity-10' : '' }}">
                            <div class="fw-bold small mb-1"><i class="fa-solid fa-eye me-1"></i> 1. Página dos Anais</div>
                            @if($anais->exists && $anais->publicado())
                                <p class="small mb-2">Visível desde {{ $anais->publicado_em?->format('d/m/Y H:i') }}. Lista os trabalhos aprovados, apresentados e autorizados.</p>
                                <form action="{{ route('gestao.trabalhos.anais.despublicar', $evento) }}" method="POST">
                                    @csrf <button class="btn btn-sm btn-outline-danger w-100" data-confirmar="Ocultar os Anais? A página pública sairá do ar."><i class="fa-solid fa-eye-slash"></i> Ocultar Anais</button>
                                </form>
                            @else
                                <p class="small text-muted mb-2">Oculta ao público. Pode ser tornada visível a qualquer momento, mesmo antes do fim do evento e sem os dados de publicação.</p>
                                <form action="{{ route('gestao.trabalhos.anais.publicar', $evento) }}" method="POST">
                                    @csrf <button class="btn btn-sm btn-success w-100" @disabled(!$anais->exists) data-confirmar="Tornar os Anais visíveis ao público agora?"><i class="fa-solid fa-eye"></i> Tornar visível</button>
                                </form>
                                @unless($anais->exists) <div class="form-text">Salve os dados da edição primeiro.</div> @endunless
                            @endif
                        </div>

                        {{-- 2) dados de publicação --}}
                        <div class="border rounded-3 p-3 {{ $anais->dadosVisiveis() ? 'border-success bg-success bg-opacity-10' : '' }}">
                            <div class="fw-bold small mb-1"><i class="fa-solid fa-book me-1"></i> 2. Dados de publicação</div>
                            <p class="small text-muted mb-2">ISSN, ISBN, DOI, editora, local, ano, citação ABNT e o PDF compilado dos Anais. Libere somente depois que o evento terminar e esses dados existirem.</p>
                            @if(!$anais->exists || !$anais->publicado())
                                <div class="small text-muted"><i class="fa-solid fa-lock me-1"></i> Disponível depois de tornar a página visível.</div>
                            @elseif($anais->dadosVisiveis())
                                <form action="{{ route('gestao.trabalhos.anais.dados-publicacao', $evento) }}" method="POST">
                                    @csrf <input type="hidden" name="acao" value="ocultar">
                                    <button class="btn btn-sm btn-outline-warning w-100"><i class="fa-solid fa-eye-slash"></i> Ocultar dados de publicação</button>
                                </form>
                            @else
                                @if(!($anais->isbn || $anais->issn || $anais->doi_prefixo))
                                    <div class="alert alert-warning small py-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> Nenhum ISSN, ISBN ou DOI informado ainda.</div>
                                @endif
                                <form action="{{ route('gestao.trabalhos.anais.dados-publicacao', $evento) }}" method="POST">
                                    @csrf <input type="hidden" name="acao" value="exibir">
                                    <button class="btn btn-sm btn-primary-custom w-100" data-confirmar="Liberar os dados de publicação na página pública?"><i class="fa-solid fa-book-open"></i> Liberar dados de publicação</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass-card bg-white p-0 overflow-hidden mt-2">
                <div class="p-3 border-bottom bg-light fw-bold">Trabalhos que entram nos Anais ({{ $elegiveis->count() }})</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead><tr><th class="ps-3">Protocolo</th><th>Título</th><th>Área</th><th>PDF dos Anais</th><th></th></tr></thead>
                        <tbody>
                        @forelse($elegiveis as $t)
                            <tr>
                                <td class="ps-3 fw-bold text-nowrap">{{ $t->codigo }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($t->titulo, 60) }}</td>
                                <td>{{ $t->area->nome ?? '—' }}</td>
                                <td>
                                    @if($t->arquivoAnais) <span class="badge bg-success">PDF final</span>
                                    @elseif($t->pdfPublico) <span class="badge bg-secondary">PDF da submissão</span>
                                    @else <span class="badge bg-danger">Sem PDF publicável</span> @endif
                                </td>
                                <td class="text-end pe-3">
                                    <form action="{{ route('gestao.trabalhos.arquivo-final', $t) }}" method="POST" enctype="multipart/form-data" class="d-inline-flex gap-1">
                                        @csrf
                                        <input type="file" name="arquivo" accept="application/pdf,.pdf" class="form-control form-control-sm" style="max-width: 190px;" required>
                                        <button class="btn btn-sm btn-outline-secondary" title="Enviar PDF final"><i class="fa-solid fa-upload"></i></button>
                                    </form>
                                    @if($t->arquivoAnais) <a href="{{ route('gestao.trabalhos.arquivo-final.baixar', $t) }}" target="_blank" class="btn btn-sm btn-light" title="Ver PDF final"><i class="fa-solid fa-eye"></i></a> @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Nenhum trabalho elegível ainda.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($semAutorizacao->isNotEmpty() || $aguardando > 0)
                <div class="alert alert-light border small mt-3">
                    @if($aguardando > 0) <div><i class="fa-solid fa-hourglass-half me-1"></i> {{ $aguardando }} trabalho(s) aprovado(s) ainda sem apresentação confirmada ficam de fora.</div> @endif
                    @if($semAutorizacao->isNotEmpty())
                        <div><i class="fa-solid fa-ban me-1"></i> Sem autorização de publicação dos autores (ficam de fora): {{ $semAutorizacao->pluck('codigo')->implode(', ') }}.</div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
