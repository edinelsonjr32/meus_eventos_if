<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'MeusEventosIf') }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #064e3b 0%, #065f46 50%, #047857 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: url('https://www.transparenttextures.com/patterns/cubes.png'); /* Textura sutil opcional */
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 420px;
            padding: 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .form-control {
            border-radius: 10px;
            padding: 12px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }
        .form-control:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
        .btn-primary-custom {
            background-color: #059669;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            transition: all 0.2s;
            color: white;
        }
        .btn-primary-custom:hover {
            background-color: #047857;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-5">
            <div class="d-inline-flex align-items-center justify-center bg-success bg-opacity-10 rounded-circle p-3 mb-3">
                <i class="fa-solid fa-graduation-cap text-success fs-2"></i>
            </div>
            <h4 class="fw-bold text-dark m-0">MeusEventosIf</h4>
            <small class="text-secondary">Acesso Administrativo</small>
        </div>

        {{ $slot }}

        <div class="text-center mt-4 pt-4 border-top">
            <a href="/" class="text-decoration-none text-secondary small">
                <i class="fa-solid fa-arrow-left me-1"></i> Voltar ao Início
            </a>
        </div>
    </div>
</body>
</html>
