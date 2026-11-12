<x-app-layout>
    <div class="row justify-content-center"><div class="col-xl-9">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('propostas.minhas') }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
                <div class="min-w-0"><h3 class="fw-bold m-0 texto-quebra">{{ $proposta->titulo }}</h3><small class="text-secondary">{{ $proposta->tipoRotulo() }} · {{ $proposta->evento->titulo }}</small></div>
            </div>
            <x-badge tipo="proposta" :valor="$proposta->status" />
        </div>

        @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

        @if($proposta->parecer)
            <div class="alert alert-{{ in_array($proposta->status, ['aprovada']) ? 'success' : (in_array($proposta->status, ['ajustes']) ? 'warning' : 'secondary') }}"><strong>Mensagem da organização:</strong><div class="texto-quebra mt-1">{{ $proposta->parecer }}</div></div>
        @endif

        @if($proposta->status === 'aprovada' && $proposta->atividade)
            @php $at = $proposta->atividade; @endphp
            <div class="glass-card mb-4 border border-success">
                <h6 class="fw-bold text-success mb-2"><i class="fa-solid fa-calendar-check me-1"></i> Programação confirmada</h6>
                <div class="row g-2 small">
                    <div class="col-md-6"><i class="fa-regular fa-calendar me-1 text-success"></i> <strong>{{ $at->data_inicio->format('d/m/Y H:i') }}</strong> a {{ $at->data_fim->format('H:i') }}</div>
                    <div class="col-md-6"><i class="fa-solid fa-location-dot me-1 text-success"></i> {{ $at->local }}@if($at->espaco) · <strong>{{ $at->espaco }}</strong>@endif</div>
                    <div class="col-md-6"><i class="fa-solid fa-users me-1 text-success"></i> Capacidade: {{ $at->vagas }} · {{ $at->inscritos()->count() }} inscrito(s)</div>
                    <div class="col-md-6"><i class="fa-regular fa-clock me-1 text-success"></i> {{ $at->carga_horaria }} h de certificado</div>
                    @if($at->equipamentos)<div class="col-12"><i class="fa-solid fa-screwdriver-wrench me-1 text-success"></i> Equipamentos: {{ $at->equipamentos }}</div>@endif
                </div>
                <div class="mt-3">
                    @if($proposta->atividadeConcluida())
                        <a href="{{ route('propostas.certificado', $proposta->evento_id) }}" class="btn btn-primary-custom"><i class="fa-solid fa-award"></i> Baixar certificado de ministrante</a>
                    @else
                        <span class="small text-muted"><i class="fa-regular fa-clock me-1"></i> O certificado de ministrante fica disponível depois que a atividade terminar ({{ $at->data_fim->format('d/m/Y H:i') }}).</span>
                    @endif
                </div>
            </div>
        @endif

        <div class="glass-card mb-4">
            <h6 class="fw-bold text-success mb-3">Resumo</h6>
            <p class="texto-quebra">{{ $proposta->resumo }}</p>
            <dl class="row small mb-0">
                <dt class="col-sm-4 text-secondary">Carga horária prevista</dt><dd class="col-sm-8">{{ $proposta->carga_horaria_prevista }} h</dd>
                <dt class="col-sm-4 text-secondary">Vagas sugeridas</dt><dd class="col-sm-8">{{ $proposta->vagas_sugeridas }}</dd>
                <dt class="col-sm-4 text-secondary">Público-alvo</dt><dd class="col-sm-8">{{ $proposta->publico_alvo ?: '—' }}</dd>
                <dt class="col-sm-4 text-secondary">Laboratório/espaço</dt><dd class="col-sm-8">{{ $proposta->requer_laboratorio ? 'Sim' : 'Não' }}@if($proposta->espaco_solicitado) · {{ $proposta->espaco_solicitado }}@endif</dd>
                <dt class="col-sm-4 text-secondary">Equipamentos</dt><dd class="col-sm-8 texto-quebra">{{ $proposta->equipamentos_solicitados ?: '—' }}</dd>
                <dt class="col-sm-4 text-secondary">Pessoas de apoio</dt><dd class="col-sm-8">{{ $proposta->apoios->isEmpty() ? '—' : $proposta->apoios->pluck('nome')->implode(', ') }}</dd>
            </dl>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-5">
            @if($proposta->editavel())<a href="{{ route('propostas.edit', $proposta) }}" class="btn btn-outline-warning fw-bold"><i class="fa-solid fa-pen"></i> Editar proposta</a>@endif
            @if(!in_array($proposta->status, ['aprovada', 'retirada']))
                <form action="{{ route('propostas.retirar', $proposta) }}" method="POST" onsubmit="return confirm('Retirar esta proposta?')">@csrf<button class="btn btn-outline-danger fw-bold"><i class="fa-solid fa-ban"></i> Retirar proposta</button></form>
            @endif
        </div>
    </div></div>
</x-app-layout>
