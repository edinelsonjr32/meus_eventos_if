<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-9">
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-id-badge text-success me-1"></i> Recepção</h3>
            <p class="text-secondary mb-4">Eventos em que você faz o check-in e o credenciamento dos participantes.</p>

            @forelse($eventos as $e)
                <div class="glass-card bg-white mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="fw-bold fs-5">{{ $e->titulo }}</div>
                        <small class="text-muted">{{ $e->data_inicio->format('d/m/Y') }} @if($e->local) · {{ $e->local }} @endif</small>
                        <div class="mt-2">
                            <span class="sb" style="background:#dcfce7;color:#15803d;border-color:#86efac;"><i class="fa-solid fa-circle-check"></i> {{ $e->total_credenciados }} check-in</span>
                            <span class="sb" style="background:#fef3c7;color:#b45309;border-color:#fcd34d;"><i class="fa-solid fa-hourglass-half"></i> {{ $e->total_inscritos - $e->total_credenciados }} pendentes</span>
                            <span class="small text-muted ms-1">de {{ $e->total_inscritos }} inscritos</span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('admin.eventos.credenciamento.index', $e->id) }}" class="btn btn-success fw-bold"><i class="fa-solid fa-id-badge"></i> Recepção (check-in)</a>
                        <a href="{{ route('admin.eventos.credenciamento.lista', $e->id) }}" class="btn btn-outline-primary fw-bold"><i class="fa-solid fa-list-check"></i> Lista de inscritos</a>
                    </div>
                </div>
            @empty
                <div class="glass-card bg-white text-center text-muted py-5">
                    <i class="fa-solid fa-id-badge fs-1 opacity-25 d-block mb-3"></i>
                    Você ainda não foi designado(a) à recepção de nenhum evento. Peça ao administrador para atribuir o papel <strong>Recepção</strong> em <em>Papéis</em> do evento.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
