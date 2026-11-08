<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <h3 class="fw-bold mb-1">Apresentações a avaliar</h3>
            <p class="text-secondary mb-4">Trabalhos aprovados cuja apresentação (feira de trabalhos) foi atribuída a você. A avaliação do texto fica em <a href="{{ route('avaliador.avaliacoes.index') }}">Minhas Avaliações</a>.</p>

            <h6 class="fw-bold text-success mb-3">Em aberto ({{ $abertas->count() }})</h6>
            @forelse($abertas as $a)
                <div class="glass-card bg-white mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fw-bold">{{ $a->trabalho->titulo }}</div>
                        <small class="text-muted">{{ $a->trabalho->codigo }} · {{ $a->trabalho->evento->titulo }}</small>
                        <div class="small mt-1">
                            @if($a->trabalho->sessao) <span class="badge bg-info text-dark"><i class="fa-regular fa-clock me-1"></i>{{ $a->trabalho->sessao->titulo }} · {{ $a->trabalho->sessao->data_inicio->format('d/m H:i') }}</span> @endif
                            <span class="badge bg-secondary bg-opacity-10 text-dark">{{ $a->statusRotulo() }}</span>
                            @if($a->prazo) <span class="{{ $a->atrasada() ? 'text-danger fw-bold' : 'text-muted' }}">{{ $a->atrasada() ? 'Em atraso — prazo' : 'Prazo' }} {{ $a->prazo->format('d/m/Y') }}</span> @endif
                        </div>
                    </div>
                    <a href="{{ route('avaliador.apresentacoes.show', $a) }}" class="btn btn-primary-custom btn-sm fw-bold">{{ $a->status === 'em_andamento' ? 'Continuar' : 'Avaliar' }}</a>
                </div>
            @empty
                <div class="glass-card bg-white text-center text-muted py-4 mb-4">Nenhuma apresentação pendente.</div>
            @endforelse

            <h6 class="fw-bold text-success mt-4 mb-3">Concluídas ({{ $concluidas->count() }})</h6>
            @forelse($concluidas as $a)
                <div class="glass-card bg-white mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div><div class="fw-bold">{{ $a->trabalho->titulo }}</div><small class="text-muted">{{ $a->trabalho->codigo }} · nota {{ number_format($a->nota_final, 2, ',', '.') }} · {{ $a->concluida_em?->format('d/m/Y') }}</small></div>
                    <a href="{{ route('avaliador.apresentacoes.show', $a) }}" class="btn btn-light btn-sm">Ver</a>
                </div>
            @empty
                <div class="glass-card bg-white text-center text-muted py-4">Nenhuma avaliação concluída ainda.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
