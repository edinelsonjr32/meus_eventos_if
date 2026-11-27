<x-app-layout>
    <style>
        .mini-avatar { width: 72px; height: 72px; flex: 0 0 72px; border-radius: 50%; object-fit: cover; display: grid; place-items: center; background: #ecfdf5; color: #065f46; font-weight: 800; font-size: 1.35rem; }
    </style>

    <div class="row justify-content-center"><div class="col-xl-10">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="fw-bold m-0"><i class="fa-solid fa-chalkboard-user me-2 text-primary-custom"></i> Ministrantes no site</h3>
            <a href="{{ route('admin.eventos.show', $evento->id) }}" class="btn btn-light fw-bold"><i class="fa-solid fa-arrow-left me-1"></i> Voltar ao evento</a>
        </div>

        @if(session('success'))<div class="alert alert-success small">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger small">{{ $errors->first() }}</div>@endif

        <div class="alert alert-light border small">
            Os nomes vêm do campo <strong>Responsável</strong> das atividades e já aparecem na página pública com as iniciais.
            Aqui você acrescenta foto e descrição ou oculta alguém. Publique a foto somente com a autorização da pessoa.
        </div>

        <details class="glass-card p-3 mb-3">
            <summary class="fw-bold"><i class="fa-solid fa-user-plus me-1"></i> Acrescentar pessoa que ainda não está na programação</summary>
            <form method="POST" action="{{ route('admin.ministrantes.salvar', $evento) }}" enctype="multipart/form-data" class="row g-2 mt-1">
                @csrf
                <input type="hidden" name="visivel" value="1">
                <div class="col-md-4"><label class="small fw-bold" for="novo-nome">Nome</label><input id="novo-nome" name="nome" class="form-control" required maxlength="160"></div>
                <div class="col-md-4"><label class="small fw-bold" for="novo-desc">Descrição</label><input id="novo-desc" name="descricao" class="form-control" maxlength="160" placeholder="Ex.: Palestrante, professora da UFOPA"></div>
                <div class="col-md-4"><label class="small fw-bold" for="novo-foto">Foto (até 2 MB)</label><input id="novo-foto" type="file" name="foto" class="form-control" accept=".jpg,.jpeg,.png,.webp"></div>
                <div class="col-12"><button class="btn btn-primary-custom fw-bold">Acrescentar</button></div>
            </form>
        </details>

        <div class="row g-3">
            @forelse($pessoas as $i => $p)
                <div class="col-lg-6">
                    <div class="glass-card p-3 h-100">
                        <div class="d-flex gap-3 align-items-center mb-2">
                            @if($p->foto)<img src="{{ $p->foto }}" alt="Foto de {{ $p->nome }}" class="mini-avatar">@else<span class="mini-avatar" aria-hidden="true">{{ $p->iniciais }}</span>@endif
                            <div style="min-width:0">
                                <div class="fw-bold texto-quebra">{{ $p->nome }}</div>
                                <div class="small text-muted">{{ $p->atividades ? implode(' · ', $p->atividades) : 'Sem atividade na programação' }}</div>
                                @if($p->oculto)<span class="badge bg-secondary">Oculto no site</span>@endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.ministrantes.salvar', $evento) }}" enctype="multipart/form-data" class="row g-2">
                            @csrf
                            <input type="hidden" name="chave" value="{{ $p->chave }}">
                            <div class="col-12"><label class="small fw-bold" for="nome-{{ $i }}">Nome exibido</label><input id="nome-{{ $i }}" name="nome" class="form-control form-control-sm" value="{{ $p->nome }}" required maxlength="160"></div>
                            <div class="col-9"><label class="small fw-bold" for="desc-{{ $i }}">Descrição</label><input id="desc-{{ $i }}" name="descricao" class="form-control form-control-sm" value="{{ $p->descricao }}" maxlength="160"></div>
                            <div class="col-3"><label class="small fw-bold" for="ordem-{{ $i }}">Ordem</label><input id="ordem-{{ $i }}" type="number" min="1" name="ordem" class="form-control form-control-sm" value="{{ $p->ordem }}"></div>
                            <div class="col-12"><label class="small fw-bold" for="foto-{{ $i }}">{{ $p->foto ? 'Trocar foto' : 'Foto' }} (até 2 MB)</label><input id="foto-{{ $i }}" type="file" name="foto" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp"></div>
                            <div class="col-12 d-flex flex-wrap gap-3">
                                <label class="form-check small mb-0"><input type="checkbox" class="form-check-input" name="visivel" value="1" @checked(!$p->oculto)> Mostrar no site</label>
                                @if($p->foto)<label class="form-check small mb-0"><input type="checkbox" class="form-check-input" name="remover_foto" value="1"> Remover foto</label>@endif
                            </div>
                            <div class="col-12"><button class="btn btn-sm btn-primary-custom fw-bold">Salvar</button></div>
                        </form>

                        @if($p->registro)
                            <form method="POST" action="{{ route('admin.ministrantes.destroy', [$evento, $p->registro]) }}" class="mt-2" onsubmit="return confirm('Remover a foto e os dados acrescentados?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-link text-danger p-0">Remover dados acrescentados</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="glass-card p-4 text-center text-muted">Nenhum ministrante ainda. Preencha o campo Responsável nas atividades ou acrescente alguém acima.</div></div>
            @endforelse
        </div>
    </div></div>
</x-app-layout>
