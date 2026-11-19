@props(['trabalho', 'compacto' => false])
@php
    $trabalho->loadMissing('sala');
    $situacao = $trabalho->agendado() ? $trabalho->situacaoDaApresentacao() : null;
    $minutos = $situacao === 'futura' ? (int) now()->diffInMinutes($trabalho->apresentacao_inicio, false) : null;
@endphp
@if($situacao && $trabalho->sala?->ativo !== false)
    <div {{ $attributes->class(['rounded-3 border p-3', 'border-success bg-success-subtle' => $situacao === 'agora', 'bg-light' => $situacao !== 'agora']) }}>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="min-w-0">
                <div class="small fw-bold text-uppercase text-secondary mb-1"><i class="fa-solid fa-video me-1"></i> Apresentação oral online</div>
                <div class="fw-bold">{{ ucfirst($trabalho->apresentacao_inicio->translatedFormat('l, d/m')) }}, {{ $trabalho->apresentacao_inicio->format('H:i') }}@if($trabalho->apresentacao_fim) às {{ $trabalho->apresentacao_fim->format('H:i') }}@endif</div>
                <div class="small text-secondary">{{ $trabalho->sala->nome }}</div>
                @if($situacao === 'agora') <span class="badge bg-success mt-1">Ao vivo agora</span>
                @elseif($situacao === 'futura' && $minutos <= 60) <span class="badge bg-warning text-dark mt-1">Começa em {{ max($minutos, 1) }} min</span>
                @elseif($situacao === 'encerrada') <span class="badge bg-secondary mt-1">Encerrada</span> @endif
                @if(!$compacto && $trabalho->sala->instrucoes)<div class="small text-muted mt-2" style="white-space:pre-line;">{{ $trabalho->sala->instrucoes }}</div>@endif
            </div>
            @if($situacao !== 'encerrada')
                <a href="{{ route('apresentacao.sala', $trabalho) }}" target="_blank" rel="noopener" class="btn {{ $situacao === 'agora' ? 'btn-success' : 'btn-primary-custom' }} fw-bold"><i class="fa-solid fa-right-to-bracket me-1"></i> Entrar na sala</a>
            @endif
        </div>
    </div>
@endif
