<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Validação de Certificado de Trabalho</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0fdf4; font-family: system-ui, sans-serif; }
        .card-validacao { max-width: 640px; margin: 40px auto; background: #fff; border-radius: 20px; padding: 2rem; box-shadow: 0 10px 30px -10px rgba(6,78,59,.2); }
        .data-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; font-weight: 700; display: block; }
        .data-row { padding: .75rem 0; border-bottom: 1px solid #f3f4f6; }
        .icone { font-size: 3.5rem; }
    </style>
</head>
<body>
    <div class="container px-3">
        <div class="card-validacao text-center">
            @if(!$certificado)
                <div class="icone text-danger"><i class="fa-regular fa-circle-xmark"></i></div>
                <h2 class="fw-bold text-danger mb-2">Documento não encontrado</h2>
                <p class="text-secondary">O código <strong>{{ $hash }}</strong> não existe em nossa base de dados.</p>
            @elseif($certificado->revogado())
                <div class="icone text-warning"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <h2 class="fw-bold text-warning mb-2">Certificado revogado</h2>
                <p class="text-secondary">Este certificado foi revogado em {{ $certificado->revogado_em->format('d/m/Y') }} e não é mais válido.</p>
            @else
                <div class="icone text-success"><i class="fa-regular fa-circle-check"></i></div>
                <h2 class="fw-bold text-success mb-2">Certificado autêntico</h2>
                <p class="text-secondary mb-4">Documento emitido pelo sistema MeusEventosIF.</p>

                <div class="text-start">
                    <div class="data-row">
                        <span class="data-label">Autor</span>
                        <div class="fw-bold">{{ $certificado->autor->nome_completo }}</div>
                        <small class="text-muted">@if($certificado->autor->cpf) CPF: ***.{{ substr($certificado->autor->cpf, 4, 3) }}.***-** · @endif{{ $certificado->funcaoRotulo() }}</small>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Trabalho</span>
                        <div class="fw-bold">{{ $certificado->trabalho->titulo }}</div>
                        <small class="text-muted">{{ $certificado->trabalho->codigo }}</small>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Evento</span>
                        <div class="fw-bold">{{ $certificado->trabalho->evento->titulo }}</div>
                    </div>
                    <div class="data-row border-0">
                        <span class="data-label">Emitido em</span>
                        <div class="fw-bold">{{ $certificado->emitido_em->format('d/m/Y') }}</div>
                    </div>
                </div>

                <a href="{{ route('certificado.trabalho.download', $hash) }}" class="btn btn-outline-success rounded-pill px-4 fw-bold mt-3">
                    <i class="fa-solid fa-download me-2"></i> Baixar PDF
                </a>
            @endif
        </div>
        <div class="text-center text-secondary small pb-4">&copy; {{ date('Y') }} Instituto Federal do Pará - Campus Óbidos</div>
    </div>
</body>
</html>
