<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <h3 class="fw-bold mb-1">Minhas Avaliações</h3>
            <p class="text-secondary mb-4">Trabalhos atribuídos a você para avaliação.</p>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

            <h6 class="fw-bold text-success mb-3">Em aberto ({{ $abertas->count() }})</h6>
            @forelse($abertas as $a)
                <div class="glass-card bg-white mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fw-bold">{{ $a->trabalho->titulo }} @if($a->desempate)<span class="sb" style="background:#fef3c7;color:#b45309;border-color:#fcd34d;"><i class="fa-solid fa-scale-balanced"></i> Desempate</span>@endif</div>
                        <small class="text-muted">{{ $a->trabalho->codigo }} · {{ $a->trabalho->evento->titulo }}</small>
                        <div class="small mt-1">
                            <x-badge tipo="avaliacao" :valor="$a->status" />
                            @if($a->prazo)
                                <span class="{{ $a->atrasada() ? 'text-danger fw-bold' : 'text-muted' }}">
                                    {{ $a->atrasada() ? 'Em atraso — prazo' : 'Prazo' }} {{ $a->prazo->format('d/m/Y') }}
                                </span>
                            @endif
                            @if($a->rodada > 1) <span class="badge bg-info text-dark">Rodada {{ $a->rodada }}</span> @endif
                        </div>
                    </div>
                    <a href="{{ route('avaliador.avaliacoes.show', $a) }}" class="btn btn-primary-custom btn-sm fw-bold">
                        {{ $a->status === 'em_andamento' ? 'Continuar avaliação' : 'Avaliar' }}
                    </a>
                </div>
            @empty
                <div class="glass-card bg-white text-center text-muted py-4 mb-4">Nenhuma avaliação pendente.</div>
            @endforelse

            @if($aguardando->isNotEmpty())
                <h6 class="fw-bold mt-4 mb-2" style="color:#1d4ed8;"><i class="fa-solid fa-hourglass-half me-1"></i> Aguardando liberação pela gestão ({{ $aguardando->count() }})</h6>
                <p class="small text-muted">Você já foi atribuído(a) a estes trabalhos, mas eles só ficam disponíveis para avaliação depois que a gestão os <strong>encaminha para avaliação</strong>.</p>
                @foreach($aguardando as $a)
                    <div class="glass-card bg-white mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div><div class="fw-bold">{{ $a->trabalho->codigo }} <span class="text-muted fw-normal">· {{ $a->trabalho->evento->titulo }}</span></div>
                            <x-badge tipo="pendencia" valor="encaminhar_avaliacao" rotulo="Aguardando a gestão encaminhar" /></div>
                    </div>
                @endforeach
            @endif

            <h6 class="fw-bold text-success mt-4 mb-3">Concluídas ({{ $concluidas->count() }})</h6>
            @forelse($concluidas as $a)
                <div class="glass-card bg-white mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fw-bold">{{ $a->trabalho->titulo }}</div>
                        <small class="text-muted">{{ $a->trabalho->codigo }} · nota {{ number_format($a->nota_final, 2, ',', '.') }} · {{ $a->recomendacaoRotulo() }} · {{ $a->concluida_em?->format('d/m/Y') }}</small>
                    </div>
                    <a href="{{ route('avaliador.avaliacoes.show', $a) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye"></i> Ver</a>
                </div>
            @empty
                <div class="glass-card bg-white text-center text-muted py-4">Nenhuma avaliação concluída ainda.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
