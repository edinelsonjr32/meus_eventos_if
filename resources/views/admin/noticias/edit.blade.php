<x-app-layout>
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('admin.noticias.index', $evento) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
        <div><h3 class="fw-bold m-0">Editar notícia</h3><small class="text-secondary">{{ $evento->titulo }}</small></div>
    </div>

    @if($errors->any()) <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div> @endif

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.noticias.update', [$evento, $noticia]) }}">
                @csrf @method('PUT')
                @include('admin.noticias._form')
                <button class="btn btn-primary-custom fw-bold mt-3"><i class="fa-solid fa-floppy-disk me-1"></i> Salvar alterações</button>
                <a href="{{ route('admin.noticias.index', $evento) }}" class="btn btn-light mt-3">Cancelar</a>
            </form>
        </div>
    </div>
</x-app-layout>
