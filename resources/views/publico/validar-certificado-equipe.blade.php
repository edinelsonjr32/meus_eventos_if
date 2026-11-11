<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Validação de Certificado da Equipe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0fdf4; font-family: system-ui, sans-serif; }
        .card-validacao { max-width: 640px; margin: 40px auto; background: #fff; border-radius: 20px; padding: 2rem; box-shadow: 0 10px 30px -10px rgba(5,150,105,.25); }
        .data-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; font-weight: 700; display: block; }
        .data-row { padding: .75rem 0; border-bottom: 1px solid #f3f4f6; }
        .icone { font-size: 3.5rem; }
    </style>
    @include('partials.favicon')
</head>
<body>
    <div class="container px-3">
        <div class="card-validacao text-center">
            @if(!$membro || !$membro->certificadoEmitido())
                <div class="icone text-danger"><i class="fa-regular fa-circle-xmark"></i></div>
                <h2 class="fw-bold text-danger mb-2">Documento não encontrado</h2>
                <p class="text-secondary">O código <strong>{{ $hash }}</strong> não existe ou foi revogado.</p>
            @else
                <div class="icone text-success"><i class="fa-solid fa-circle-check"></i></div>
                <h2 class="fw-bold text-success mb-2">Certificado da equipe autêntico</h2>
                <p class="text-secondary mb-4">Documento emitido pelo sistema MeusEventosIF.</p>
                <div class="text-start">
                    <div class="data-row">
                        <span class="data-label">Participante</span>
                        <div class="fw-bold">{{ $membro->participante->nome_completo }}</div>
                        <small class="text-muted">@if($membro->participante->cpf) CPF: ***.{{ substr($membro->participante->cpf, 4, 3) }}.***-** @endif</small>
                    </div>
                    <div class="data-row"><span class="data-label">Função</span><div class="fw-bold">{{ $membro->funcao }}</div></div>
                    <div class="data-row"><span class="data-label">Carga horária</span><div class="fw-bold">{{ $membro->carga_horaria_trabalho }}h</div></div>
                    <div class="data-row"><span class="data-label">Evento</span><div class="fw-bold">{{ $membro->evento->titulo }}</div></div>
                    <div class="data-row border-0"><span class="data-label">Emitido em</span><div class="fw-bold">{{ $membro->certificado_emitido_em->format('d/m/Y') }}</div></div>
                </div>
                <a href="{{ route('certificado.equipe.download', $membro->hash_validacao) }}" class="btn btn-success fw-bold mt-3"><i class="fa-solid fa-download"></i> Baixar certificado</a>
            @endif
        </div>
        <div class="text-center text-secondary small pb-4">&copy; {{ date('Y') }} Instituto Federal do Pará - Campus Óbidos</div>
    </div>
</body>
</html>
