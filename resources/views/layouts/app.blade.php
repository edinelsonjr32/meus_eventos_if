<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'MeusEventosIf') }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        :root {
            --primary-color: #064e3b; /* Verde IFPA Escuro */
            --accent-color: #10b981;  /* Verde Vibrante */
            --bg-body: #f3f4f6;
            --sidebar-width: 280px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            background-image: radial-gradient(#e5e7eb 1px, transparent 1px);
            background-size: 20px 20px;
            overflow-x: hidden;
        }

        /* Sidebar Moderna */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: white;
            z-index: 1000;
            padding: 2rem 1rem;
            box-shadow: 4px 0 24px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow-y: auto;
        }

        .nav-link {
            color: #94a3b8;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 8px;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: var(--accent-color);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            color: #fff;
        }

        /* Área de Conteúdo */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        /* Cards Estilo Glass (Padrão do Sistema) */
        .glass-card {
            background: white;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05);
            padding: 1.5rem;
            transition: transform 0.2s;
        }

        /* Botões Customizados */
        .btn-primary-custom {
            background: var(--primary-color);
            color: white;
            border-radius: 10px;
            padding: 10px 24px;
            border: none;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 6px rgba(6, 78, 59, 0.2);
            transition: all 0.2s;
        }
        .btn-primary-custom:hover {
            background: #065f46;
            transform: translateY(-1px);
            color: white;
        }

        /* Inputs Modernos */
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 12px 15px;
            background-color: #f8fafc;
        }
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
            border-color: var(--accent-color);
            background-color: white;
        }

        /* Utilitários Alpine */
        [x-cloak] { display: none !important; }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
        }
    </style>
</head>
<body>

    <nav class="sidebar" id="sidebar">
        <div class="d-flex align-items-center gap-3 mb-5 px-2">
            <div class="bg-success rounded-3 d-flex align-items-center justify-center shadow-lg" style="width: 40px; height: 40px;">
                <i class="fa-solid fa-graduation-cap text-white fs-5"></i>
            </div>
            <div>
                <h5 class="m-0 fw-bold tracking-tight text-white">MeusEventosIF</h5>
                <small class="text-secondary" style="font-size: 0.7rem;">Campus Óbidos</small>
            </div>
        </div>

        <div class="nav flex-column">

            <small class="text-uppercase text-secondary fw-bold mb-2 px-3" style="font-size: 0.7rem; letter-spacing: 1px;">Área do Participante</small>

            <a href="{{ route('inscrito.dashboard') }}" class="nav-link {{ request()->routeIs('inscrito.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-check w-25 text-center"></i> Minha Agenda
            </a>

            @if(Auth::user()->role === 'admin')
                <div class="mt-4 pt-4 border-top border-secondary border-opacity-25">
                    <small class="text-uppercase text-warning fw-bold mb-2 px-3" style="font-size: 0.7rem; letter-spacing: 1px;">
                        <i class="fa-solid fa-lock me-1"></i> Gestão
                    </small>

                    <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <i class="fa-solid fa-gauge-high w-25 text-center"></i> Dashboard
                    </a>

                    <a href="{{ route('admin.eventos.index') }}" class="nav-link {{ request()->routeIs('admin.eventos*') ? 'active' : '' }}">
                        <i class="fa-solid fa-layer-group w-25 text-center"></i> Meus Eventos
                    </a>

                    <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                        <i class="fa-solid fa-layer-group w-25 text-center"></i> Usuários
                    </a>
                </div>
            @endif

            <div class="mt-4 pt-4 border-top border-secondary border-opacity-25">
                <small class="text-uppercase text-secondary fw-bold mb-2 px-3" style="font-size: 0.7rem; letter-spacing: 1px;">Configurações</small>

                <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}">
                    <i class="fa-solid fa-user-gear w-25 text-center"></i> Minha Conta
                </a>

                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="nav-link w-100 text-start border-0 bg-transparent text-danger hover:bg-danger hover:bg-opacity-10 hover:text-danger">
                        <i class="fa-solid fa-power-off w-25 text-center"></i> Sair
                    </button>
                </form>
            </div>
        </div>

        <div class="mt-auto pt-4 pb-2 px-2 text-center text-secondary" style="font-size: 0.7rem;">
            <p class="m-0">Logado como <strong>{{ Auth::user()->name }}</strong></p>
            <p class="opacity-50 m-0">{{ ucfirst(Auth::user()->role) }}</p>
        </div>
    </nav>

    <main class="main-content">
        <nav class="navbar navbar-light bg-white rounded-3 shadow-sm mb-4 d-md-none p-3 border">
            <div class="container-fluid px-0">
                <span class="navbar-brand fw-bold text-success mb-0 h1"><i class="fa-solid fa-leaf me-2"></i> MeusEventosIf</span>
                <button class="btn btn-light border" onclick="document.getElementById('sidebar').classList.toggle('active')">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </nav>

        {{ $slot }}
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
