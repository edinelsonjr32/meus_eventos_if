<x-app-layout>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.eventos.show', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
            <div><h3 class="fw-bold m-0">Notícias e informações</h3><small class="text-secondary">{{ $evento->titulo }}</small></div>
        </div>
        <a href="{{ route('evento.publico.show', $evento->slug) }}#noticias" target="_blank" rel="noopener" class="btn btn-outline-success fw-bold"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ver no site</a>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div> @endif

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-plus me-1"></i> Nova notícia</h5>
            <form method="POST" action="{{ route('admin.noticias.store', $evento) }}">
                @csrf
                @include('admin.noticias._form', ['noticia' => null])
                <button class="btn btn-primary-custom fw-bold mt-3"><i class="fa-solid fa-paper-plane me-1"></i> Salvar notícia</button>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">Publicadas e rascunhos ({{ $noticias->total() }})</h5>
            @forelse($noticias as $n)
                @php [$rotulo, $classe] = ['no_ar' => ['No ar', 'text-bg-success'], 'agendada' => ['Agendada', 'text-bg-info'], 'rascunho' => ['Rascunho', 'text-bg-secondary']][$n->situacao()]; @endphp
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 py-3 border-bottom">
                    <div style="min-width:0">
                        <div class="fw-bold">@if($n->fixada)<i class="fa-solid fa-thumbtack me-1 text-success" title="Fixada"></i>@endif{{ $n->titulo }}</div>
                        <div class="small text-secondary"><span class="badge {{ $classe }} me-2">{{ $rotulo }}</span>{{ $n->publicado_em->format('d/m/Y H:i') }}@if($n->notificado_em)<span class="ms-2"><i class="fa-solid fa-envelope-circle-check me-1"></i>{{ (int) $n->notificados_total }} avisado(s)</span>@elseif($n->notificar_inscritos)<span class="ms-2"><i class="fa-regular fa-envelope me-1"></i>aviso pendente</span>@else<span class="ms-2 text-warning-emphasis"><i class="fa-solid fa-bell-slash me-1"></i>sem aviso por e-mail</span>@endif</div>
                        <div class="small text-muted mt-1">{{ \Illuminate\Support\Str::limit(\App\Support\HtmlSeguro::paraTexto($n->conteudo), 140) }}</div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.noticias.edit', [$evento, $n]) }}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-pen me-1"></i> Editar</a>
                        <form method="POST" action="{{ route('admin.noticias.destroy', [$evento, $n]) }}" onsubmit="return confirm('Excluir esta notícia? Esta ação não pode ser desfeita.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash me-1"></i> Excluir</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">Nenhuma notícia ainda. Use o formulário acima para publicar a primeira.</p>
            @endforelse
            <div class="mt-3">{{ $noticias->links() }}</div>
        </div>
    </div>
</x-app-layout>
