@php
    $metaAvaliadores = $trabalho->evento->trabalhoConfig?->avaliadores_por_trabalho ?? 2;
    $ativas = $trabalho->avaliacoes->where('status', '!=', 'recusada');
@endphp

@if(session('erros_lote'))
    <div class="alert alert-warning small">
        <ul class="mb-0 ps-3">@foreach(session('erros_lote') as $erro) <li>{{ $erro }}</li> @endforeach</ul>
    </div>
@endif

{{-- Triagem --}}
<div class="glass-card bg-white mb-4">
    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-filter me-1"></i> Triagem</h6>

    @if($acoesTriagem->isEmpty())
        <p class="small text-muted m-0">Nenhuma ação de triagem disponível para o status atual ({{ $trabalho->statusRotulo() }}).</p>
    @else
        <form action="{{ route('gestao.trabalhos.triagem', $trabalho) }}" method="POST">
            @csrf
            @if($acoesTriagem->contains(true))
                <label class="small fw-bold text-secondary">Justificativa (obrigatória para devolver ou rejeitar)</label>
                <textarea name="justificativa" rows="3" class="form-control form-control-sm mb-2" maxlength="3000" placeholder="Explique ao autor o que precisa ser ajustado ou o motivo da rejeição">{{ old('justificativa') }}</textarea>
            @endif

            <div class="d-grid gap-2">
                @foreach($acoesTriagem as $para => $exigeJustificativa)
                    @php
                        $rotulos = [
                            'em_triagem' => ['Iniciar triagem', 'btn-primary-custom', 'fa-play'],
                            'em_avaliacao' => ['Encaminhar para avaliação', 'btn-success', 'fa-share'],
                            'devolvido_triagem' => ['Devolver ao autor para ajustes', 'btn-warning', 'fa-rotate-left'],
                            'rejeitado_triagem' => ['Rejeitar na triagem', 'btn-outline-danger', 'fa-ban'],
                        ][$para];
                    @endphp
                    <button type="submit" name="para" value="{{ $para }}" class="btn btn-sm {{ $rotulos[1] }} fw-bold"
                            @if($para === 'rejeitado_triagem') onclick="return confirm('Rejeitar este trabalho? O autor será avisado por e-mail.')" @endif>
                        <i class="fa-solid {{ $rotulos[2] }} me-1"></i> {{ $rotulos[0] }}
                    </button>
                @endforeach
            </div>
        </form>
    @endif
</div>

{{-- Avaliadores --}}
<div class="glass-card bg-white mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-success m-0"><i class="fa-solid fa-user-check me-1"></i> Avaliadores</h6>
        <span class="badge {{ $ativas->count() >= $metaAvaliadores ? 'bg-success' : 'bg-warning text-dark' }}">{{ $ativas->count() }}/{{ $metaAvaliadores }}</span>
    </div>

    @forelse($trabalho->avaliacoes as $av)
        <div class="d-flex justify-content-between align-items-start small mb-2 pb-2 border-bottom">
            <div>
                <div class="fw-bold">{{ $av->avaliador->name }}</div>
                <div class="text-muted">{{ $av->statusRotulo() }}@if($av->prazo) · prazo {{ $av->prazo->format('d/m/Y') }} @endif</div>
            </div>
            @if($av->status === 'pendente')
                <form action="{{ route('gestao.trabalhos.avaliadores.destroy', [$trabalho, $av]) }}" method="POST" onsubmit="return confirm('Remover este avaliador?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-light text-danger" title="Remover"><i class="fa-solid fa-xmark"></i></button>
                </form>
            @endif
        </div>
    @empty
        <p class="small text-muted">Nenhum avaliador atribuído.</p>
    @endforelse

    @if($podeAtribuir)
        <form action="{{ route('gestao.trabalhos.avaliadores.store', $trabalho) }}" method="POST" class="mt-3">
            @csrf
            <label class="small fw-bold text-secondary">Atribuir avaliadores</label>
            @forelse($avaliadores as $u)
                <div class="form-check small">
                    <input class="form-check-input" type="checkbox" name="avaliadores[]" value="{{ $u->id }}" id="av{{ $u->id }}" @disabled($u->conflito)>
                    <label class="form-check-label {{ $u->conflito ? 'text-muted' : '' }}" for="av{{ $u->id }}">
                        {{ $u->name }} <span class="text-muted">({{ $u->carga }} em aberto)</span>
                        @if($u->conflito) <span class="text-danger">— conflito: {{ $u->conflito }}</span> @endif
                    </label>
                </div>
            @empty
                <p class="small text-muted">Não há avaliadores disponíveis. Designe avaliadores em "Papéis" do evento.</p>
            @endforelse

            @if($avaliadores->isNotEmpty())
                <label class="small fw-bold text-secondary mt-2">Prazo (opcional)</label>
                <input type="date" name="prazo" class="form-control form-control-sm mb-2" min="{{ now()->addDay()->format('Y-m-d') }}">
                <button class="btn btn-sm btn-outline-success w-100 fw-bold"><i class="fa-solid fa-user-plus me-1"></i> Atribuir selecionados</button>
            @endif
        </form>
    @else
        <p class="small text-muted mt-2 mb-0">Atribuição disponível apenas em triagem ou avaliação.</p>
    @endif
</div>
