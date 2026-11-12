<x-app-layout>
    <div class="row justify-content-center"><div class="col-xl-10">
        <h3 class="fw-bold mb-1">Propostas de Curso/Oficina</h3>
        <p class="text-secondary mb-4">Proponha minicursos e oficinas para os eventos, acompanhe a análise e baixe o certificado de ministrante depois da atividade.</p>

        @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

        @unless($participante)
            <div class="alert alert-warning"><i class="fa-solid fa-id-card me-1"></i> Para enviar propostas, complete seu cadastro com o CPF em <a href="{{ route('profile.edit') }}" class="fw-bold">Minha Conta</a>.</div>
        @endunless

        <h5 class="fw-bold mb-3"><i class="fa-solid fa-bullhorn text-success me-2"></i> Chamadas abertas</h5>
        <div class="row g-3 mb-4">
            @forelse($chamadas as $c)
                <div class="col-md-6">
                    <div class="glass-card h-100 d-flex flex-column">
                        <div class="small text-secondary">{{ $c->evento->titulo }}</div>
                        <div class="fw-bold texto-quebra">{{ $c->titulo }}</div>
                        <div class="small text-muted mb-2"><i class="fa-regular fa-calendar me-1"></i> {{ $c->fim ? 'até ' . $c->fim->format('d/m/Y H:i') : 'sem prazo definido' }} · aceita {{ implode(' e ', $c->tiposAceitos()) }}</div>
                        @if($c->descricao)<p class="small texto-quebra mb-3">{{ \Illuminate\Support\Str::limit(\App\Support\HtmlSeguro::paraTexto($c->descricao), 220) }}</p>@endif
                        <a href="{{ route('propostas.create', $c) }}" class="btn btn-primary-custom btn-sm mt-auto"><i class="fa-solid fa-paper-plane"></i> Enviar proposta</a>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="glass-card text-muted small">Nenhuma chamada de minicursos/oficinas aberta no momento.</div></div>
            @endforelse
        </div>

        @php $aprovadas = $propostas->where('status', 'aprovada'); @endphp
        @if($aprovadas->isNotEmpty())
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-circle-check text-success me-2"></i> Minicursos e oficinas aprovados</h5>
            <div class="row g-3 mb-4">
                @foreach($aprovadas as $p)
                    @php $at = $p->atividade; @endphp
                    <div class="col-md-6">
                        <div class="glass-card h-100 border border-success">
                            <div class="d-flex justify-content-between gap-2"><div class="fw-bold texto-quebra">{{ $p->titulo }}</div><x-badge tipo="proposta" valor="aprovada" /></div>
                            <div class="small text-secondary mb-2">{{ $p->tipoRotulo() }} · {{ $p->evento->titulo }}</div>
                            @if($at)
                                <div class="small"><i class="fa-regular fa-calendar me-1 text-success"></i> {{ $at->data_inicio->format('d/m/Y H:i') }} a {{ $at->data_fim->format('H:i') }}</div>
                                <div class="small"><i class="fa-solid fa-location-dot me-1 text-success"></i> {{ $at->local }}@if($at->espaco) · {{ $at->espaco }}@endif</div>
                                <div class="small"><i class="fa-solid fa-users me-1 text-success"></i> {{ $at->vagas }} vagas · {{ $at->inscritos()->count() }} inscrito(s)</div>
                            @endif
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a href="{{ route('propostas.show', $p) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye"></i> Detalhes</a>
                                @if($p->atividadeConcluida())
                                    <a href="{{ route('propostas.certificado', $p->evento_id) }}" class="btn btn-sm btn-primary-custom"><i class="fa-solid fa-award"></i> Certificado de ministrante</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <h5 class="fw-bold mb-3"><i class="fa-solid fa-file-lines text-success me-2"></i> Todas as minhas propostas</h5>
        @if($propostas->isEmpty())
            <div class="glass-card text-center py-5 text-muted">Você ainda não enviou propostas.</div>
        @else
            <div class="glass-card p-0 overflow-hidden"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead><tr><th class="ps-4">Proposta</th><th>Evento</th><th>Situação</th><th class="text-end pe-4">Ações</th></tr></thead>
                <tbody>
                    @foreach($propostas as $p)
                        <tr>
                            <td class="ps-4"><div class="fw-bold texto-quebra">{{ $p->titulo }}</div><small class="text-muted">{{ $p->tipoRotulo() }} · enviada em {{ $p->created_at->format('d/m/Y') }}</small></td>
                            <td>{{ $p->evento->titulo }}</td>
                            <td><x-badge tipo="proposta" :valor="$p->status" />@if($p->parecer && in_array($p->status, ['ajustes', 'reprovada']))<div class="small text-muted mt-1 texto-quebra" style="max-width:260px">{{ \Illuminate\Support\Str::limit($p->parecer, 120) }}</div>@endif</td>
                            <td class="text-end pe-4">
                                <a href="{{ route('propostas.show', $p) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye"></i> Ver</a>
                                @if($p->editavel())<a href="{{ route('propostas.edit', $p) }}" class="btn btn-sm btn-outline-warning"><i class="fa-solid fa-pen"></i> Editar</a>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div></div>
        @endif
    </div></div>
</x-app-layout>
