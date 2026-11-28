{{-- Presença de ministrantes e ajudantes: é o que libera o certificado de cada um. Precisa de $atividade e $membros. --}}
<div class="glass-card bg-white mt-4" id="presenca-equipe">
    <h6 class="fw-bold text-success mb-1"><i class="fa-solid fa-award me-1"></i> Presença e certificados da equipe</h6>
    <p class="small text-muted mb-3">Lance a presença de cada pessoa para liberar o certificado. Quem tem conta passa a vê-lo em <strong>Minha Agenda</strong>.</p>

    @unless($atividade->emiteCertificado())
        <div class="alert alert-warning small">Esta atividade está marcada como "não gera certificado".</div>
    @endunless
    @if($atividade->data_inicio->isFuture())
        <div class="alert alert-light border small">A presença pode ser lançada a partir de {{ $atividade->data_inicio->format('d/m/Y H:i') }}.</div>
    @endif

    @foreach($membros as $m)
        <div class="border rounded-3 p-3 mb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div style="min-width:0">
                <div class="fw-bold texto-quebra">{{ $m->nome }}</div>
                <div class="small text-muted">
                    {{ \App\Models\AtividadeEquipe::FUNCOES[$m->funcao] ?? 'Ajudante' }}
                    @if($m->registro) · presença lançada em {{ $m->registro->presenca_em->format('d/m/Y H:i') }}
                        @if($m->registro->certificadoEmitido()) · certificado emitido @else · certificado liberado @endif
                    @elseif($m->impedimento) · <span class="text-danger">{{ $m->impedimento }}</span>
                    @endif
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($m->registro)
                    @if($m->registro->certificadoEmitido())
                        <a href="{{ route('certificado.atividade.download', $m->registro->hash_validacao) }}" target="_blank" class="btn btn-sm btn-outline-success fw-bold"><i class="fa-solid fa-download"></i> Certificado</a>
                    @elseif($atividade->emiteCertificado())
                        <form action="{{ route('admin.atividades.equipe.certificado', [$atividade, $m->registro]) }}" method="POST">@csrf<button class="btn btn-sm btn-primary-custom fw-bold"><i class="fa-solid fa-award"></i> Emitir certificado</button></form>
                    @endif
                    <form action="{{ route('admin.atividades.equipe.destroy', [$atividade, $m->registro]) }}" method="POST" onsubmit="return confirm('Desfazer a presença? O certificado desta pessoa deixa de valer.')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Desfazer</button></form>
                @elseif(!$m->impedimento && !$atividade->data_inicio->isFuture())
                    <form action="{{ route('admin.atividades.equipe.presenca', $atividade) }}" method="POST">@csrf<input type="hidden" name="chave" value="{{ $m->chave }}"><button class="btn btn-sm btn-success fw-bold"><i class="fa-solid fa-user-check"></i> Lançar presença</button></form>
                @endif
            </div>
        </div>
    @endforeach
</div>
