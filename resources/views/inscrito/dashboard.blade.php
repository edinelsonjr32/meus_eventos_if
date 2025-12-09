<x-app-layout>
    <style>
        /* Estilos mantidos da versão anterior + novos */
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #047857 100%);
            color: white;
            border-radius: 20px;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px -10px rgba(6, 78, 59, 0.4);
        }
        .profile-pattern {
            position: absolute; top: 0; right: 0; bottom: 0; left: 0;
            background-image: radial-gradient(rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            opacity: 0.3;
        }
        .stat-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 15px 20px;
            min-width: 120px;
            text-align: center;
        }

        .activity-timeline { position: relative; padding-left: 20px; }
        .activity-timeline::before {
            content: ''; position: absolute; left: 0; top: 15px; bottom: 15px;
            width: 2px; background: #e5e7eb; border-radius: 2px;
        }
        .activity-card { margin-bottom: 1rem; border: 1px solid #f3f4f6; transition: all 0.2s ease; }
        .activity-card:hover { transform: translateX(5px); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }

        /* Estados do Card */
        .activity-card.enrolled { background-color: #f0fdf4; border-color: #bbf7d0; }
        .activity-card.certified { background-color: #fffbeb; border-color: #fcd34d; } /* Amarelo Ouro */
        .activity-card.full { background-color: #f9fafb; border-color: #e5e7eb; opacity: 0.8; }

        /* Botão Hover Effect */
        .btn-status-enrolled:hover {
            background-color: #fee2e2 !important; border-color: #fecaca !important; color: #dc2626 !important;
        }
        .btn-status-enrolled:hover .text-status-normal { display: none; }
        .btn-status-enrolled:hover .text-status-hover { display: inline; }
        .text-status-hover { display: none; }
    </style>

    <div class="py-4">
        <div class="container-fluid px-4">

            @if(session('error'))
                <div class="alert alert-danger border-0 shadow-sm mb-4 fw-bold">
                    <i class="fa-solid fa-circle-exclamation me-2"></i> {{ session('error') }}
                </div>
            @endif

            <div class="profile-header mb-5">
                <div class="profile-pattern"></div>
                <div class="position-relative d-flex flex-column flex-md-row align-items-center justify-content-between gap-4">
                    <div class="d-flex align-items-center gap-4 text-center text-md-start">
                        <div class="bg-white text-success rounded-circle d-flex align-items-center justify-content-center shadow-lg" style="width: 80px; height: 80px; font-size: 2rem; font-weight: bold;">
                            {{ substr($participante->nome_completo, 0, 1) }}
                        </div>
                        <div>
                            <h2 class="fw-bold m-0 mb-1">{{ $participante->nome_completo }}</h2>
                            <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-md-start opacity-75 small">
                                <span><i class="fa-regular fa-id-card me-1"></i> {{ $participante->cpf }}</span>
                                <span><i class="fa-solid fa-graduation-cap me-1"></i> {{ ucfirst($participante->tipo_vinculo) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="stat-box">
                            <div class="fs-2 fw-bold">{{ $eventosInscritos->count() }}</div>
                            <div class="small text-uppercase tracking-wide opacity-75">Eventos</div>
                        </div>
                        <div class="stat-box">
                            <div class="fs-2 fw-bold">{{ $participante->frequencias->count() }}</div>
                            <div class="small text-uppercase tracking-wide opacity-75">Certificados</div>
                        </div>
                    </div>
                </div>
            </div>

            @if($eventosInscritos->isEmpty())
                <div class="glass-card text-center py-5">
                    <div class="bg-light rounded-circle d-inline-flex p-4 mb-3">
                        <i class="fa-regular fa-calendar-xmark fs-1 text-secondary opacity-25"></i>
                    </div>
                    <h4 class="fw-bold text-dark">Sua agenda está vazia</h4>
                    <p class="text-secondary mb-4">Inscreva-se em eventos para liberar atividades.</p>
                    <a href="{{ route('home') }}" class="btn btn-primary-custom shadow-sm px-4">Ver Eventos Disponíveis</a>
                </div>
            @else
                <h4 class="fw-bold text-dark mb-4 ps-2 border-start border-4 border-success">Meus Eventos</h4>

                <div class="row g-4">
                    @foreach($eventosInscritos as $evento)
                        <div class="col-12">
                            <div class="glass-card p-0 overflow-hidden">

                                <div class="p-4 bg-white border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                    <div>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 mb-2">Inscrição Confirmada</span>
                                        <h4 class="fw-bold text-dark m-0">{{ $evento->titulo }}</h4>
                                        <p class="text-secondary small m-0 mt-1">
                                            <i class="fa-regular fa-calendar me-1"></i> {{ $evento->data_inicio->format('d/m/Y') }}
                                        </p>
                                    </div>
                                    <a href="{{ route('evento.publico.show', $evento->slug) }}" class="btn btn-outline-dark btn-sm fw-bold">
                                        Página do Evento &rarr;
                                    </a>
                                </div>

                                <div class="p-4 bg-light bg-opacity-25">
                                    <div class="activity-timeline">
                                        @foreach($evento->atividades as $atividade)
                                            @php
                                                // 1. Verifica Inscrição
                                                $estaInscrito = $participante->atividadesInscritas->contains($atividade->id);

                                                // 2. Verifica Presença/Certificado (Se existe registro na tabela Frequencia)
                                                $frequencia = $participante->frequencias->where('atividade_id', $atividade->id)->first();
                                                $temCertificado = $frequencia ? true : false;

                                                // 3. Verifica Lotação
                                                $totalInscritos = $atividade->inscritos()->count();
                                                $vagasEsgotadas = (!is_null($atividade->vagas) && $totalInscritos >= $atividade->vagas);

                                                // Define classe CSS do card
                                                $cardClass = 'bg-white';
                                                if ($temCertificado) $cardClass = 'certified';
                                                elseif ($estaInscrito) $cardClass = 'enrolled';
                                                elseif ($vagasEsgotadas) $cardClass = 'full';
                                            @endphp

                                            <div class="activity-card glass-card p-3 d-flex flex-column flex-md-row align-items-md-center gap-3 {{ $cardClass }}">

                                                <div class="text-center px-3 border-end d-none d-md-block" style="min-width: 100px;">
                                                    <span class="d-block fw-bold fs-5 text-dark">{{ $atividade->data_inicio->format('H:i') }}</span>
                                                    <span class="text-secondary small">{{ $atividade->carga_horaria }}h</span>
                                                </div>
                                                <div class="d-md-none text-secondary small fw-bold">
                                                    <i class="fa-regular fa-clock me-1"></i> {{ $atividade->data_inicio->format('H:i') }} ({{ $atividade->carga_horaria }}h)
                                                </div>

                                                <div class="flex-grow-1">
                                                    <div class="d-flex gap-2 mb-1">
                                                        <span class="badge bg-white border text-secondary shadow-sm">{{ ucfirst($atividade->tipo) }}</span>
                                                        @if($vagasEsgotadas && !$estaInscrito && !$temCertificado)
                                                            <span class="badge bg-secondary text-white shadow-sm">Esgotado</span>
                                                        @elseif(!$temCertificado)
                                                            <span class="badge bg-light text-secondary border">
                                                                {{ $totalInscritos }}/{{ $atividade->vagas ?? '∞' }} Vagas
                                                            </span>
                                                        @endif
                                                    </div>

                                                    <h5 class="fw-bold m-0 {{ $estaInscrito ? 'text-success' : 'text-dark' }}">
                                                        {{ $atividade->titulo }}
                                                    </h5>

                                                    @if($temCertificado)
                                                        <small class="text-warning fw-bold d-block mt-1">
                                                            <i class="fa-solid fa-star me-1"></i> Concluído! Certificado disponível.
                                                        </small>
                                                    @elseif($estaInscrito)
                                                        <small class="text-success fw-bold d-block mt-1">
                                                            <i class="fa-solid fa-check-circle me-1"></i> Sua vaga está garantida.
                                                        </small>
                                                    @endif
                                                </div>

                                                <div class="ms-md-auto" style="min-width: 160px;">

                                                    @if($temCertificado)
                                                        <a href="{{ route('certificado.download', $frequencia->hash_validacao) }}" target="_blank" class="btn btn-warning w-100 fw-bold shadow-sm text-dark">
                                                            <i class="fa-solid fa-award me-2"></i> Certificado
                                                        </a>

                                                    @elseif($estaInscrito)
                                                        <form action="{{ route('inscrito.atividade.toggle', $atividade->id) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" class="btn btn-outline-success fw-bold w-100 btn-status-enrolled bg-white">
                                                                <span class="text-status-normal"><i class="fa-solid fa-check me-2"></i> Inscrito</span>
                                                                <span class="text-status-hover"><i class="fa-solid fa-xmark me-2"></i> Sair</span>
                                                            </button>
                                                        </form>

                                                    @elseif($vagasEsgotadas)
                                                        <button class="btn btn-light w-100 fw-bold text-muted border" disabled>
                                                            <i class="fa-solid fa-ban me-2"></i> Lotado
                                                        </button>

                                                    @else
                                                        <form action="{{ route('inscrito.atividade.toggle', $atividade->id) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" class="btn btn-dark fw-bold w-100 shadow-sm">
                                                                Participar
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
