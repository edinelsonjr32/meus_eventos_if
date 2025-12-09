<x-app-layout>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 text-secondary mb-1">
                <i class="fa-solid fa-layer-group"></i>
                <small class="fw-bold text-uppercase">Painel de Controle</small>
            </div>
            <h2 class="fw-bold m-0 text-dark">{{ $evento->titulo }}</h2>
            <div class="d-flex gap-3 mt-2 text-secondary small">
                <span><i class="fa-regular fa-calendar me-1"></i> {{ $evento->data_inicio->format('d/m/Y') }}</span>
                <span><i class="fa-solid fa-location-dot me-1"></i> {{ $evento->local }}</span>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('admin.eventos.inscritos', $evento->id) }}" class="btn btn-primary-custom shadow-sm fw-bold">
                <i class="fa-solid fa-list-ul me-2"></i> Inscritos
            </a>

            <a href="{{ route('admin.eventos.equipe.index', $evento->id) }}" class="btn btn-white bg-white border shadow-sm text-secondary fw-bold">
                <i class="fa-solid fa-people-group me-2"></i> Equipe
            </a>

            <a href="{{ route('admin.eventos.credenciamento.index', $evento->id) }}" class="btn btn-dark shadow-sm fw-bold">
                <i class="fa-solid fa-id-badge me-2"></i> Recepção
            </a>

            <form action="{{ route('admin.relatorios.frequencia.exportar') }}" method="POST">
                @csrf
                <input type="hidden" name="evento_id" value="{{ $evento->id }}">
                <button type="submit" class="btn btn-dark shadow-sm fw-bold">
                    <i class="fa-solid fa-file-excel me-2"></i> Exportar Frequência
                </button>
            </form>

            <div class="dropdown">
                <button class="btn btn-white bg-white border shadow-sm text-secondary" data-bs-toggle="dropdown">
                    <i class="fa-solid fa-gear"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <li><a class="dropdown-item" href="{{ route('admin.eventos.edit', $evento->id) }}">Editar Evento</a></li>

                    <li><a class="dropdown-item" href="{{ route('evento.publico.show', $evento->slug) }}" target="_blank">Ver Página Pública</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <div class="col-lg-4">
            <div class="glass-card bg-white position-sticky" style="top: 20px; z-index: 10;">
                <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-success">
                    <i class="fa-regular fa-calendar-plus"></i> Nova Atividade
                </h5>

                @if ($errors->any())
                    <div class="alert alert-danger small border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-3">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('admin.eventos.atividades.store', $evento->id) }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">TÍTULO DA ATIVIDADE</label>
                        <input type="text" name="titulo" class="form-control" placeholder="Ex: Palestra Magna" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">TIPO</label>
                        <select name="tipo" class="form-select">
                            <option value="palestra">Palestra</option>
                            <option value="minicurso">Minicurso</option>
                            <option value="oficina">Oficina</option>
                            <option value="mesa_redonda">Mesa Redonda</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">HORAS</label>
                            <input type="number" name="carga_horaria" value="4" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">VAGAS</label>
                            <input type="number" name="vagas" value="50" class="form-control" placeholder="0 = ∞">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">DATA E HORA (INÍCIO)</label>
                        <input type="datetime-local" name="data_inicio" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-success w-100 fw-bold py-2 btn-primary-custom">
                        <i class="fa-solid fa-plus me-1"></i> Adicionar à Agenda
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="glass-card p-0 overflow-hidden">
                <div class="p-4 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold m-0">Programação Oficial</h5>
                    <span class="badge bg-secondary">{{ $evento->atividades->count() }} Atividades</span>
                </div>

                @if($evento->atividades->isEmpty())
                    <div class="p-5 text-center text-muted">
                        <div class="bg-light rounded-circle d-inline-flex p-3 mb-3">
                            <i class="fa-solid fa-mug-hot fs-3 opacity-50"></i>
                        </div>
                        <p class="mb-0">Nenhuma atividade cadastrada ainda.</p>
                        <small>Use o formulário ao lado para começar.</small>
                    </div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($evento->atividades as $atividade)
                            @php
                                // Cálculo de lotação e Link QR Code
                                $totalInscritos = $atividade->inscritos()->count();
                                $vagasTotais = $atividade->vagas;
                                $lotacao = $vagasTotais ? ($totalInscritos / $vagasTotais) * 100 : 0;

                                $corBarra = 'bg-success';
                                if($lotacao > 80) $corBarra = 'bg-warning';
                                if($lotacao >= 100) $corBarra = 'bg-danger';

                                // URL para registro de presença
                                $linkPresenca = route('frequencia.form', $atividade->token_frequencia);
                            @endphp

                        <div class="list-group-item p-4 hover-bg-light transition position-relative">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success border-opacity-25 text-uppercase" style="font-size: 0.65rem;">
                                            {{ ucfirst($atividade->tipo) }}
                                        </span>
                                        <span class="text-secondary small font-monospace">
                                            <i class="fa-regular fa-clock me-1"></i> {{ $atividade->carga_horaria }}h
                                        </span>
                                    </div>

                                    <h6 class="fw-bold mb-2 text-dark fs-5">{{ $atividade->titulo }}</h6>

                                    <div class="d-flex align-items-center gap-3 text-secondary small">
                                        <span>
                                            <i class="fa-regular fa-calendar-check me-1"></i>
                                            {{ $atividade->data_inicio->format('d/m \à\s H:i') }}
                                        </span>

                                        <div class="d-flex align-items-center gap-2" title="{{ $totalInscritos }} inscritos de {{ $vagasTotais ?? 'Ilimitado' }}">
                                            <i class="fa-solid fa-users me-1"></i>
                                            <div class="progress" style="height: 6px; width: 60px;">
                                                <div class="progress-bar {{ $corBarra }}" role="progressbar" style="width: {{ $vagasTotais ? $lotacao : 0 }}%"></div>
                                            </div>
                                            <span class="fw-bold {{ $lotacao >= 100 ? 'text-danger' : 'text-dark' }}">
                                                {{ $totalInscritos }}/{{ $vagasTotais ?? '∞' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2 align-items-center">

                                    <a href="{{ route('admin.atividades.participantes', $atividade->id) }}" class="btn btn-outline-primary btn-sm fw-bold d-flex align-items-center gap-2" title="Gerenciar Presença">
                                        <i class="fa-solid fa-clipboard-user"></i> <span class="d-none d-lg-inline">Lista</span>
                                    </a>

                                    <button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#modalQr-{{ $atividade->id }}" title="Exibir QR Code">
                                        <i class="fa-solid fa-qrcode"></i>
                                    </button>

                                    <form action="{{ route('admin.atividades.destroy', $atividade->id) }}" method="POST" onsubmit="return confirm('Tem certeza? Isso apagará o histórico de presença desta atividade.');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm border-0" title="Excluir Atividade">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="modalQr-{{ $atividade->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow-lg">
                                    <div class="modal-header border-0 pb-0">
                                        <h5 class="modal-title fw-bold">Registro de Presença</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body text-center p-4">
                                        <h6 class="text-secondary mb-3">{{ $atividade->titulo }}</h6>

                                        <div class="bg-white p-3 border rounded-3 d-inline-block mb-3 shadow-sm">
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data={{ urlencode($linkPresenca) }}"
                                                 alt="QR Code" class="img-fluid" width="250">
                                        </div>

                                        <p class="small text-muted mb-2">Aponte a câmera ou acesse o link:</p>

                                        <div class="input-group mb-3">
                                            <input type="text" class="form-control form-control-sm text-center bg-light" value="{{ $linkPresenca }}" readonly id="link-{{ $atividade->id }}">
                                            <button class="btn btn-outline-secondary btn-sm" onclick="copiarLink('link-{{ $atividade->id }}')">
                                                <i class="fa-regular fa-copy"></i>
                                            </button>
                                        </div>

                                        <div class="d-grid gap-2">
                                            <a href="{{ $linkPresenca }}" target="_blank" class="btn btn-success fw-bold">
                                                <i class="fa-solid fa-external-link-alt me-2"></i> Abrir Tela de Presença
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        function copiarLink(elementId) {
            var copyText = document.getElementById(elementId);
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value);
            alert("Link copiado: " + copyText.value);
        }
    </script>
</x-app-layout>
