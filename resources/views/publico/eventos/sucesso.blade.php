<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Inscrição Confirmada!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0fdf4; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card-custom { background: white; border-radius: 20px; padding: 3rem; text-align: center; max-width: 500px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="card-custom">
        <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex p-4 mb-4 display-4">
            <i class="fa-solid fa-ticket"></i>
        </div>
        <h2 class="fw-bold text-dark mb-2">Inscrição Confirmada!</h2>
        <p class="text-secondary mb-4">
            Parabéns! Você está inscrito no evento <strong>{{ $evento->titulo }}</strong>.
            <br>Fique atento ao seu e-mail para mais novidades.
        </p>
        <div class="d-grid gap-2">
            <a href="/" class="btn btn-success fw-bold py-2">Voltar ao Início</a>
        </div>
    </div>
</body>
</html>
