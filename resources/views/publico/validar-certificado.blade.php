<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validação de Documento - IFPA</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
        .validation-card {
            max-width: 600px; margin: 3rem auto; background: white;
            border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.05); padding: 2.5rem;
            border-top: 5px solid #10b981;
        }
        .valid-icon { color: #10b981; font-size: 4rem; margin-bottom: 1rem; }
        .invalid-icon { color: #ef4444; font-size: 4rem; margin-bottom: 1rem; }
        .data-row { border-bottom: 1px solid #f1f5f9; padding: 1rem 0; }
        .data-label { color: #64748b; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; display: block; }
        .data-value { color: #1e293b; font-size: 1.1rem; font-weight: 500; }
    </style>
</head>
<body>
    <div class="container px-3">
        <div class="validation-card text-center">

            <div class="mb-4">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/6/6f/Logo_IF.svg/1200px-Logo_IF.svg.png" alt="IFPA" height="60" class="opacity-75">
            </div>

            @if(!$frequencia)
                <div class="invalid-icon"><i class="fa-regular fa-circle-xmark"></i></div>
                <h2 class="fw-bold text-danger mb-2">Documento Não Encontrado</h2>
                <p class="text-secondary">O código <strong>{{ $hash }}</strong> não existe em nossa base de dados.</p>
            @else
                <div class="valid-icon"><i class="fa-regular fa-circle-check"></i></div>
                <h2 class="fw-bold text-success mb-2">Certificado Autêntico</h2>
                <p class="text-secondary mb-5">Este documento foi emitido oficialmente pelo sistema MeusEventosIF.</p>

                <div class="text-start">
                    <div class="data-row">
                        <span class="data-label">Participante</span>
                        <div class="data-value">{{ $frequencia->participante->nome_completo }}</div>
                        <small class="text-muted">CPF: ***.{{ substr($frequencia->participante->cpf, 4, 3) }}.***-**</small>
                    </div>

                    <div class="data-row">
                        <span class="data-label">Evento</span>
                        <div class="data-value">{{ $frequencia->atividade->evento->titulo }}</div>
                    </div>

                    <div class="data-row">
                        <span class="data-label">Atividade</span>
                        <div class="data-value">{{ $frequencia->atividade->titulo }}</div>
                    </div>

                    <div class="row">
                        <div class="col-6 data-row border-0">
                            <span class="data-label">Data</span>
                            <div class="data-value">{{ $frequencia->atividade->data_inicio->format('d/m/Y') }}</div>
                        </div>
                        <div class="col-6 data-row border-0">
                            <span class="data-label">Carga Horária</span>
                            <div class="data-value">{{ $frequencia->atividade->carga_horaria }}h</div>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <a href="{{ route('certificado.download', $hash) }}" class="btn btn-outline-success rounded-pill px-4 fw-bold">
                        <i class="fa-solid fa-download me-2"></i> Baixar PDF Original
                    </a>
                </div>
            @endif
        </div>

        <div class="text-center text-secondary small">
            &copy; {{ date('Y') }} Instituto Federal do Pará - Campus Óbidos
        </div>
    </div>
</body>
</html>
