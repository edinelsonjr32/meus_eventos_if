<x-app-layout>
    <div class="row justify-content-center"><div class="col-xl-10">
        <h3 class="fw-bold mb-1"><i class="fa-solid fa-qrcode text-success me-1"></i> Frequência</h3>
        <p class="text-secondary mb-4">Exiba o QR Code da atividade para os participantes ou registre a presença de quem não conseguiu.</p>

        @forelse($eventos as $e)
            <h5 class="fw-bold mt-4 mb-3">{{ $e->titulo }} <small class="text-muted fw-normal">· {{ $e->data_inicio->format('d/m/Y') }}</small></h5>
            @forelse($e->atividades->groupBy(fn ($a) => $a->data_inicio->format('d/m/Y')) as $dia => $atividades)
                <div class="small fw-bold text-muted text-uppercase mb-2">{{ $dia }}</div>
                @foreach($atividades as $a)
                    <div class="glass-card bg-white mb-2 d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="min-w-0">
                            <div class="fw-bold texto-quebra">{{ $a->titulo }}</div>
                            <small class="text-muted">{{ $a->data_inicio->format('H:i') }}@if($a->data_fim) às {{ $a->data_fim->format('H:i') }}@endif @if($a->local) · {{ $a->local }}@endif · {{ $a->frequencias_count }} presença(s)</small>
                            @unless($a->frequenciaAberta())<span class="badge text-bg-secondary ms-1">Frequência encerrada</span>@endunless
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('gestao.frequencia.qr', $a) }}" class="btn btn-success fw-bold"><i class="fa-solid fa-qrcode me-1"></i> Exibir QR Code</a>
                            <a href="{{ route('gestao.frequencia.manual', $a) }}" class="btn btn-outline-primary fw-bold"><i class="fa-solid fa-user-check me-1"></i> Presença manual</a>
                        </div>
                    </div>
                @endforeach
            @empty
                <div class="glass-card bg-white text-muted small">Este evento ainda não tem atividades.</div>
            @endforelse
        @empty
            <div class="glass-card bg-white text-center text-muted py-5">
                <i class="fa-solid fa-qrcode fs-1 opacity-25 d-block mb-3"></i>
                Você ainda não foi designado(a) para a frequência de nenhum evento. Peça ao administrador o papel <strong>Gestor de Frequência</strong> em <em>Papéis</em> do evento.
            </div>
        @endforelse
    </div></div>
</x-app-layout>
