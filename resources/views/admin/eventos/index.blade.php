<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
        <div>
            <h2 class="fw-bold text-dark m-0">Meus Eventos</h2>
            <div class="d-flex gap-3 text-secondary mt-2 small">
                <span><i class="fa-solid fa-layer-group me-1"></i> {{ $eventos->total() }} Total</span>
                <span><i class="fa-regular fa-calendar-check me-1"></i> {{ $eventos->where('data_inicio', '>=', now())->count() }} Ativos</span>
            </div>
        </div>
        <a href="{{ route('admin.eventos.create') }}" class="btn-primary-custom text-decoration-none shadow-sm">
            <i class="fa-solid fa-plus me-2"></i> Criar Novo Evento
        </a>
    </div>

    @if($eventos->isEmpty())
        <div class="glass-card text-center py-5 border-dashed">
            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-center mb-3" style="width: 80px; height: 80px;">
                <i class="fa-solid fa-calendar-plus fs-2 text-secondary opacity-50"></i>
            </div>
            <h4>Nenhum evento criado</h4>
            <p class="text-secondary mb-4">Comece a gerenciar seus eventos acadêmicos agora.</p>
            <a href="{{ route('admin.eventos.create') }}" class="btn btn-dark">Criar Primeiro Evento</a>
        </div>
    @else
        <div class="row g-4">
            @foreach($eventos as $evento)
                @php
                    $status = 'future';
                    $statusLabel = 'Em Breve';
                    $statusColor = 'bg-primary';

                    if(now()->between($evento->data_inicio, $evento->data_fim)) {
                        $status = 'live'; $statusLabel = 'Acontecendo Agora'; $statusColor = 'bg-success';
                    } elseif(now() > $evento->data_fim) {
                        $status = 'past'; $statusLabel = 'Finalizado'; $statusColor = 'bg-secondary';
                    }
                @endphp

            <div class="col-md-6 col-lg-4">
                <div class="glass-card h-100 p-0 overflow-hidden d-flex flex-column position-relative hover-lift transition">
                    <div style="height: 6px; background: {{ $evento->configuracoes['cor_fundo'] ?? '#10b981' }};"></div>

                    <div class="p-4 flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="badge {{ $statusColor }} bg-opacity-10 text-{{ str_replace('bg-', '', $statusColor) }} border border-{{ str_replace('bg-', '', $statusColor) }} border-opacity-25 rounded-pill px-3">
                                {{ $statusLabel }}
                            </span>

                            <div class="dropdown">
                                <button class="btn btn-link text-secondary p-0" data-bs-toggle="dropdown">
                                    <i class="fa-solid fa-ellipsis"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                                    <li><a class="dropdown-item" href="{{ route('evento.publico.show', $evento->slug) }}" target="_blank"><i class="fa-solid fa-eye me-2 text-muted"></i> Ver Página Pública</a></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.eventos.edit', $evento->id) }}"><i class="fa-solid fa-pen me-2 text-muted"></i> Editar</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route('admin.eventos.destroy', $evento->id) }}" method="POST">
                                            @csrf @method('DELETE')
                                            <button class="dropdown-item text-danger"><i class="fa-solid fa-trash me-2"></i> Excluir</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <h5 class="fw-bold text-dark mb-2 text-truncate" title="{{ $evento->titulo }}">
                            {{ $evento->titulo }}
                        </h5>
                        <p class="text-secondary small mb-4">
                            <i class="fa-solid fa-location-dot me-1"></i> {{ $evento->local }}
                        </p>

                        <div class="bg-light rounded-3 p-3 d-flex align-items-center gap-3 mb-4">
                            <div class="text-center lh-1 border-end pe-3">
                                <span class="d-block fw-bold fs-4 text-dark">{{ $evento->data_inicio->format('d') }}</span>
                                <span class="text-uppercase small text-secondary">{{ $evento->data_inicio->format('M') }}</span>
                            </div>
                            <div class="small text-secondary lh-sm">
                                Início às {{ $evento->data_inicio->format('H:i') }}<br>
                                {{ $evento->atividades->count() }} Atividades
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-light border-top text-center">
                        <a href="{{ route('admin.eventos.show', $evento->id) }}" class="btn btn-white w-100 fw-bold text-primary shadow-sm">
                            Gerenciar Painel
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-5">
            {{ $eventos->links() }}
        </div>
    @endif

    <style>
        .hover-lift:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important; }
        .transition { transition: all 0.3s ease; }
    </style>
</x-app-layout>
