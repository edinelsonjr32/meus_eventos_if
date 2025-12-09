<x-app-layout>
    <div class="mb-5">
        <h2 class="fw-bold text-dark m-0">Visão Geral</h2>
        <p class="text-secondary small">Bem-vindo ao painel de controle do CertificaIF.</p>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="glass-card p-4 d-flex align-items-center gap-3 h-100">
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                    <i class="fa-solid fa-calendar-check fs-4"></i>
                </div>
                <div>
                    <h3 class="fw-bold m-0">{{ $eventosAtivos }}</h3>
                    <small class="text-secondary fw-bold text-uppercase" style="font-size: 0.7rem;">Eventos Ativos</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="glass-card p-4 d-flex align-items-center gap-3 h-100">
                <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                    <i class="fa-solid fa-users fs-4"></i>
                </div>
                <div>
                    <h3 class="fw-bold m-0">{{ $totalInscritos }}</h3>
                    <small class="text-secondary fw-bold text-uppercase" style="font-size: 0.7rem;">Total Inscritos</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="glass-card p-4 d-flex align-items-center gap-3 h-100">
                <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                    <i class="fa-solid fa-certificate fs-4"></i>
                </div>
                <div>
                    <h3 class="fw-bold m-0">{{ $totalCertificados }}</h3>
                    <small class="text-secondary fw-bold text-uppercase" style="font-size: 0.7rem;">Certificados</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="glass-card p-4 d-flex align-items-center gap-3 h-100">
                <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                    <i class="fa-solid fa-layer-group fs-4"></i>
                </div>
                <div>
                    <h3 class="fw-bold m-0">{{ $totalEventos }}</h3>
                    <small class="text-secondary fw-bold text-uppercase" style="font-size: 0.7rem;">Eventos Criados</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <div class="col-lg-8">

            <div class="glass-card p-0 overflow-hidden mb-4">
                <div class="p-4 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold m-0"><i class="fa-regular fa-clock me-2 text-primary"></i> Próximas Atividades</h5>
                    <a href="{{ route('admin.eventos.index') }}" class="text-decoration-none small fw-bold">Ver tudo</a>
                </div>

                @if($proximasAtividades->isEmpty())
                    <div class="p-5 text-center text-muted">
                        <i class="fa-solid fa-mug-hot fs-3 mb-2 opacity-50"></i>
                        <p class="m-0">Nenhuma atividade agendada para os próximos dias.</p>
                    </div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($proximasAtividades as $ativ)
                        <div class="list-group-item p-3 border-start border-4 border-primary m-3 mb-0 rounded bg-light">
                            <div class="d-flex align-items-center gap-3">
                                <div class="text-center px-2">
                                    <span class="d-block fw-bold text-dark h5 m-0">{{ $ativ->data_inicio->format('H:i') }}</span>
                                    <span class="text-secondary small text-uppercase">{{ $ativ->data_inicio->format('d M') }}</span>
                                </div>
                                <div class="vr"></div>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold text-dark m-0">{{ $ativ->titulo }}</h6>
                                    <small class="text-secondary">
                                        {{ $ativ->evento->titulo }} • {{ ucfirst($ativ->tipo) }}
                                    </small>
                                </div>
                                <a href="{{ route('admin.atividades.participantes', $ativ->id) }}" class="btn btn-sm btn-white border shadow-sm fw-bold text-primary">
                                    Gerenciar
                                </a>
                            </div>
                        </div>
                        @endforeach
                        <div class="p-2"></div>
                    </div>
                @endif
            </div>

            <div class="glass-card p-0 overflow-hidden">
                <div class="p-4 border-bottom bg-light">
                    <h5 class="fw-bold m-0"><i class="fa-solid fa-user-plus me-2 text-success"></i> Inscrições Recentes</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-white">
                            <tr>
                                <th class="ps-4 small text-secondary">Participante</th>
                                <th class="small text-secondary">Evento</th>
                                <th class="small text-secondary text-end pe-4">Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ultimosInscritos as $insc)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">{{ $insc->participante->nome_completo }}</div>
                                    <small class="text-muted">{{ $insc->participante->email }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border fw-normal">{{ Str::limit($insc->evento->titulo, 25) }}</span>
                                </td>
                                <td class="text-end pe-4 text-secondary small">
                                    {{ $insc->created_at->diffForHumans() }}
                                </td>
                            </tr>
                            @endforeach
                            @if($ultimosInscritos->isEmpty())
                                <tr><td colspan="3" class="text-center py-4 text-muted">Nenhuma inscrição recente.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div class="col-lg-4">

            <div class="glass-card p-4 mb-4 bg-primary text-white" style="background: linear-gradient(135deg, #064e3b 0%, #047857 100%);">
                <h5 class="fw-bold mb-3">Acesso Rápido</h5>
                <div class="d-grid gap-2">
                    <a href="{{ route('admin.eventos.create') }}" class="btn btn-light fw-bold text-success shadow-sm text-start">
                        <i class="fa-solid fa-plus me-2"></i> Criar Novo Evento
                    </a>
                </div>
            </div>

            <div class="glass-card p-4">
                <h5 class="fw-bold mb-4">Eventos Populares</h5>

                @if($topEventos->isEmpty())
                    <p class="text-muted small">Sem dados suficientes.</p>
                @else
                    @php $max = $topEventos->first()->inscricoes_count ?: 1; @endphp

                    @foreach($topEventos as $evento)
                        @php $percent = ($evento->inscricoes_count / $max) * 100; @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="fw-bold text-dark">{{ Str::limit($evento->titulo, 20) }}</span>
                                <span class="text-secondary">{{ $evento->inscricoes_count }} inscritos</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
