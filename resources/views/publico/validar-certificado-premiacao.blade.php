<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Validação de Certificado de Premiação</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #fefce8; font-family: system-ui, sans-serif; }
        .card-validacao { max-width: 640px; margin: 40px auto; background: #fff; border-radius: 20px; padding: 2rem; box-shadow: 0 10px 30px -10px rgba(161,98,7,.25); }
        .data-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; font-weight: 700; display: block; }
        .data-row { padding: .75rem 0; border-bottom: 1px solid #f3f4f6; }
        .icone { font-size: 3.5rem; }
    </style>
    @include('partials.favicon')
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
                <div class="icone" style="color:#a16207;"><i class="fa-solid fa-trophy"></i></div>
                <h2 class="fw-bold mb-2" style="color:#a16207;">Certificado de premiação autêntico</h2>
                <p class="text-secondary mb-4">Documento emitido pelo sistema MeusEventosIF.</p>

                <div class="text-start">
                    <div class="data-row">
                        <span class="data-label">Premiado(a)</span>
                        <div class="fw-bold">{{ $certificado->autor->nome_completo }}</div>
                        <small class="text-muted">@if($certificado->autor->cpf) CPF: ***.{{ substr($certificado->autor->cpf, 4, 3) }}.***-** @endif</small>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Colocação</span>
                        <div class="fw-bold">{{ $certificado->premiacao->colocacaoRotulo() }} · {{ $certificado->premiacao->categoriaRotulo() }}</div>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Trabalho</span>
                        <div class="fw-bold">{{ $certificado->premiacao->trabalho->titulo }}</div>
                        <small class="text-muted">{{ $certificado->premiacao->trabalho->codigo }}</small>
                    </div>
                    <div class="data-row border-0">
                        <span class="data-label">Evento</span>
                        <div class="fw-bold">{{ $certificado->premiacao->trabalho->evento->titulo }}</div>
                    </div>
                </div>
            @endif
        </div>
        <div class="text-center text-secondary small pb-4">&copy; {{ date('Y') }} Instituto Federal do Pará - Campus Óbidos</div>
    </div>
</body>
</html>
