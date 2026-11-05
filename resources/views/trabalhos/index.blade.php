<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <h3 class="fw-bold mb-1">Meus Trabalhos</h3>
            <p class="text-secondary mb-4">Escolha um evento para submeter e acompanhe as suas submissões.</p>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

            {{-- Eventos com chamada de trabalhos --}}
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-bullhorn text-success me-2"></i> Submeter um trabalho</h5>

            @if($eventos->isEmpty())
                <div class="glass-card bg-white text-muted small mb-4">Nenhum evento com chamada de trabalhos no momento.</div>
            @else
                <div class="row g-3 mb-4">
                    @foreach($eventos as $evento)
                        @php
                            $cfg = $evento->trabalhoConfig;
                            $aberta = $cfg->estaAberta();
                            $limite = $cfg->max_trabalhos_por_usuario;
                            $meus = $meusPorEvento->get($evento->id, 0);
                            $atingiuLimite = $limite && $meus >= $limite;
                            $emBreve = !$aberta && $cfg->submissao_inicio && $cfg->submissao_inicio->isFuture();
                        @endphp
                        <div class="col-md-6 col-xl-4">
                            <div class="glass-card bg-white h-100 d-flex flex-column">
                                <div class="fw-bold">{{ $evento->titulo }}</div>
                                <small class="text-muted mb-2">
                                    <i class="fa-regular fa-calendar me-1"></i>{{ $evento->data_inicio->format('d/m/Y') }}
                                    · <i class="fa-solid fa-location-dot me-1"></i>{{ $evento->local }}
                                </small>

                                <div class="small mb-3">
                                    @if($aberta)
                                        <span class="badge bg-success">Chamada aberta</span>
                                        @if($cfg->submissao_fim) <span class="text-muted">até {{ $cfg->submissao_fim->format('d/m/Y H:i') }}</span> @endif
                                    @elseif($emBreve)
                                        <span class="badge bg-warning text-dark">Em breve</span>
                                        <span class="text-muted">abre em {{ $cfg->submissao_inicio->format('d/m/Y H:i') }}</span>
                                    @else
                                        <span class="badge bg-secondary">Encerrada</span>
                                    @endif
                                    @if($meus) <div class="text-muted mt-1">Você tem {{ $meus }} trabalho(s) neste evento{{ $limite ? " (limite: {$limite})" : '' }}.</div> @endif
                                </div>

                                <div class="mt-auto d-flex gap-2">
                                    @if($aberta && !$atingiuLimite)
                                        <a href="{{ route('trabalhos.create', $evento->slug) }}" class="btn btn-primary-custom btn-sm fw-bold flex-fill">
                                            <i class="fa-solid fa-file-arrow-up me-1"></i> Submeter trabalho
                                        </a>
                                    @elseif($aberta && $atingiuLimite)
                                        <button class="btn btn-secondary btn-sm fw-bold flex-fill" disabled>Limite de trabalhos atingido</button>
                                    @else
                                        <button class="btn btn-secondary btn-sm fw-bold flex-fill" disabled>Submissão indisponível</button>
                                    @endif
                                    <a href="{{ route('evento.publico.show', $evento->slug) }}" class="btn btn-light btn-sm" title="Página do evento"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <h5 class="fw-bold mb-3"><i class="fa-regular fa-file-lines text-success me-2"></i> Meus trabalhos submetidos</h5>

            @if($trabalhos->isEmpty())
                <div class="glass-card bg-white text-center py-5">
                    <i class="fa-regular fa-file-lines fs-1 text-secondary opacity-25 mb-3"></i>
                    <p class="text-muted m-0">Você ainda não submeteu trabalhos. Escolha um evento acima para começar.</p>
                </div>
            @else
                <div class="glass-card bg-white p-0 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr><th class="ps-4">Trabalho</th><th>Evento</th><th>Status</th><th class="text-end pe-4">Ações</th></tr>
                            </thead>
                            <tbody>
                                @foreach($trabalhos as $t)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold">{{ $t->titulo }}</div>
                                            <small class="text-muted">{{ $t->codigo ?? 'Sem protocolo (rascunho)' }} · {{ $t->autores->count() }} autor(es)</small>
                                        </td>
                                        <td>{{ $t->evento->titulo }}</td>
                                        <td><span class="badge bg-secondary bg-opacity-10 text-dark">{{ $t->statusRotulo() }}</span></td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('trabalhos.show', $t) }}" class="btn btn-sm btn-light">Ver</a>
                                            @can('update', $t)
                                                <a href="{{ route('trabalhos.edit', $t) }}" class="btn btn-sm btn-outline-success">Editar</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
