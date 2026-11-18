<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Presença registrada · {{ $atividade->titulo }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0fdf4; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .cartao { background: #fff; border-radius: 24px; padding: 2.5rem 1.75rem; max-width: 440px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0,0,0,.1); animation: sobe .5s ease-out; }
        .selo { width: 80px; height: 80px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; color: #16a34a; font-size: 2.5rem; animation: cresce .5s cubic-bezier(.175,.885,.32,1.275) .3s both; }
        .atividade { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1rem 1.1rem; text-align: left; }
        .atividade dt { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; font-weight: 700; margin-top: .6rem; }
        .atividade dt:first-child { margin-top: 0; }
        .atividade dd { margin: 0; font-weight: 600; overflow-wrap: anywhere; }
        @keyframes sobe { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }
        @keyframes cresce { from { transform: scale(0); } to { transform: scale(1); } }
    </style>
    @include('partials.favicon')
</head>
<body>
    <main class="cartao text-center" role="status" aria-live="polite">
        <div class="selo"><i class="fa-solid fa-check"></i></div>
        <h1 class="h3 fw-bold mb-1">Presença registrada!</h1>
        <p class="text-secondary mb-4">{{ $participante->nome_completo }}, sua frequência nesta atividade foi confirmada.</p>

        <dl class="atividade mb-4">
            <dt>Atividade</dt><dd>{{ $atividade->titulo }}</dd>
            <dt>Evento</dt><dd>{{ $atividade->evento->titulo }}</dd>
            <dt>Data e horário previstos</dt><dd>{{ $atividade->data_inicio->format('d/m/Y \à\s H:i') }}@if($atividade->data_fim) – {{ $atividade->data_fim->format('H:i') }}@endif</dd>
            @if($atividade->local)<dt>Local</dt><dd>{{ $atividade->local }}</dd>@endif
            <dt>Presença registrada em</dt><dd>{{ $frequencia->data_registro?->format('d/m/Y \à\s H:i:s') }}</dd>
        </dl>

        @if($atividade->emiteCertificado())
            <p class="small text-muted mb-3"><i class="fa-solid fa-award me-1 text-warning"></i> O certificado desta atividade fica disponível em <strong>Minha Agenda</strong>.</p>
        @else
            <p class="small text-muted mb-3"><i class="fa-solid fa-circle-info me-1"></i> Esta atividade tem apenas controle de frequência, sem certificado.</p>
        @endif

        <div class="d-grid gap-2">
            <a href="{{ route('inscrito.dashboard') }}" class="btn btn-success fw-bold py-2">Ir para Minha Agenda</a>
            <a href="/" class="btn btn-outline-success fw-bold py-2">Voltar ao início</a>
        </div>
        <small class="text-muted d-block mt-4">IFPA Campus Óbidos</small>
    </main>
</body>
</html>
