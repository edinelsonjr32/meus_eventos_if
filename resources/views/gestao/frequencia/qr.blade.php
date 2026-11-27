<x-app-layout>
    <style>
        .qr-tela { background: #fff; border-radius: 20px; padding: 2rem 1rem; text-align: center; border: 1px solid #e2e8f0; }
        .qr-tela svg { width: 100%; max-width: min(62vh, 560px); height: auto; display: block; margin: 1rem auto; }
        .qr-tela:fullscreen { display: flex; flex-direction: column; justify-content: center; border-radius: 0; overflow: auto; }
        .qr-tela:fullscreen .nao-na-tela-cheia { display: none; }
    </style>

    <div class="row justify-content-center"><div class="col-xl-8">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <a href="{{ route('gestao.frequencia.index') }}" class="btn btn-light fw-bold"><i class="fa-solid fa-arrow-left me-1"></i> Voltar</a>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-dark fw-bold" onclick="document.getElementById('qrTela').requestFullscreen?.()"><i class="fa-solid fa-expand me-1"></i> Tela cheia</button>
                <a href="{{ route('gestao.frequencia.manual', $atividade) }}" class="btn btn-outline-primary fw-bold"><i class="fa-solid fa-user-check me-1"></i> Presença manual</a>
            </div>
        </div>

        @unless($atividade->frequenciaAberta())
            <div class="alert alert-warning"><i class="fa-solid fa-lock me-1"></i> A frequência desta atividade está <strong>encerrada</strong>: o QR Code não registra presença. Fale com a organização para reabrir.</div>
        @endunless

        <div class="qr-tela" id="qrTela">
            <h2 class="fw-bold mb-1 px-2">{{ $atividade->titulo }}</h2>
            <div class="text-muted">{{ $atividade->evento->titulo }} · {{ $atividade->data_inicio->format('d/m/Y H:i') }}</div>
            {!! $qr !!}
            <div class="fw-bold fs-5">Aponte a câmera do celular para registrar sua presença</div>
            <div class="small text-muted mt-2 nao-na-tela-cheia" style="overflow-wrap:anywhere">{{ $link }}</div>
            <div class="small text-muted mt-1 nao-na-tela-cheia">{{ $total }} presença(s) registrada(s) até agora (atualize a página para conferir)</div>
        </div>
    </div></div>
</x-app-layout>
