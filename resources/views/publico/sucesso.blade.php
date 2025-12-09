<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sucesso - IFPA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f0fdf4;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .success-card {
            background: white;
            border-radius: 24px;
            padding: 3rem 2rem;
            text-align: center;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: slideUp 0.5s ease-out;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            background: #dcfce7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            color: #16a34a;
            font-size: 2.5rem;
            animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.3s both;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes scaleIn { from { transform: scale(0); } to { transform: scale(1); } }
    </style>
</head>
<body>

    <div class="success-card">
        <div class="icon-circle">
            <i class="fa-solid fa-check"></i>
        </div>

        <h2 class="fw-bold text-dark mb-2">Tudo Certo!</h2>
        <p class="text-secondary mb-4">
            {{ session('mensagem') ?? 'Sua presença foi confirmada com sucesso. O certificado será gerado automaticamente.' }}
        </p>

        <div class="d-grid gap-2">
            <a href="/" class="btn btn-outline-success fw-bold py-2 rounded-3">
                Voltar ao Início
            </a>
        </div>

        <div class="mt-4 pt-4 border-top">
            <small class="text-muted d-block">IFPA Campus Óbidos</small>
        </div>
    </div>

</body>
</html>
