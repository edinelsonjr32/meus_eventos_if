<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <h3 class="fw-bold mb-1">Gestão de Trabalhos</h3>
            <p class="text-secondary mb-4">Eventos em que você gere a chamada e os trabalhos submetidos.</p>

            @forelse($eventos as $evento)
                <div class="glass-card bg-white mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fw-bold">{{ $evento->titulo }}</div>
                        <small class="text-muted">{{ $evento->data_inicio->format('d/m/Y') }} · {{ $evento->total_trabalhos }} trabalho(s) submetido(s)</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('gestao.trabalhos.index', $evento) }}" class="btn btn-primary-custom btn-sm fw-bold">Trabalhos</a>
                        <a href="{{ route('gestao.trabalhos.config', $evento) }}" class="btn btn-outline-secondary btn-sm fw-bold">Configurar chamada</a>
                    </div>
                </div>
            @empty
                <div class="glass-card bg-white text-center py-5 text-muted">Você ainda não foi designado como gestor de trabalhos em nenhum evento.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
