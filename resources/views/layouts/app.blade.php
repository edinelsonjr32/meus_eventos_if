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


        /* Selo base (também usado por telas montadas no navegador, onde não dá para usar o componente de selo) */
        .sb { display: inline-flex; align-items: center; gap: .35rem; padding: .22rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; line-height: 1.2; border: 1px solid transparent; white-space: nowrap; vertical-align: middle; }
        .sb i { font-size: .72rem; }
        .sb-ok { background: #16a34a; color: #fff; border-color: #16a34a; } .sb-pend { background: #fef3c7; color: #b45309; border-color: #fcd34d; }
        .sb-aluno { background: #dbeafe; color: #1d4ed8; border-color: #93c5fd; } .sb-servidor { background: #f3e8ff; color: #7e22ce; border-color: #d8b4fe; } .sb-externo { background: #e2e8f0; color: #334155; border-color: #94a3b8; }

        /* ===== Botões: ícone + texto alinhados, cores por significado ===== */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; font-weight: 600; border-radius: 10px; transition: all .15s; }
        .btn-sm { padding: .32rem .7rem; font-size: .78rem; border-radius: 8px; }
        .btn > i { line-height: 1; }
        .btn:active { transform: translateY(1px); }
        .btn-outline-primary { color: #1d4ed8; border-color: #bfdbfe; background: #eff6ff; }
        .btn-outline-primary:hover { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
        .btn-outline-success { color: #15803d; border-color: #bbf7d0; background: #f0fdf4; }
        .btn-outline-success:hover { background: #16a34a; border-color: #16a34a; color: #fff; }
        .btn-outline-warning { color: #b45309; border-color: #fde68a; background: #fffbeb; }
        .btn-outline-warning:hover { background: #f59e0b; border-color: #f59e0b; color: #fff; }
        .btn-outline-danger { color: #b91c1c; border-color: #fecaca; background: #fef2f2; }
        .btn-outline-danger:hover { background: #dc2626; border-color: #dc2626; color: #fff; }
        .btn-outline-secondary { color: #475569; border-color: #e2e8f0; background: #f8fafc; }
        .btn-outline-secondary:hover { background: #475569; border-color: #475569; color: #fff; }
        .btn-light { background: #f1f5f9; border-color: #e2e8f0; color: #334155; }
        .btn-light:hover { background: #e2e8f0; color: #0f172a; }
        .btn-success { background: #16a34a; border-color: #16a34a; }
        .btn-success:hover { background: #15803d; border-color: #15803d; }
        .btn-dark { background: #1e293b; border-color: #1e293b; }
        .btn-dark:hover { background: #0f172a; border-color: #0f172a; }
        /* Cor automática pelo ícone, nos botões neutros (claros/contorno cinza) */
        .btn-light:has(> .fa-trash, > .fa-xmark, > .fa-ban, > .fa-circle-xmark, > .fa-trash-can), .btn-outline-secondary:has(> .fa-trash, > .fa-trash-can, > .fa-ban) { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
        .btn-light:has(> .fa-pen, > .fa-pencil, > .fa-pen-to-square, > .fa-gear, > .fa-user-gear), .btn-outline-secondary:has(> .fa-pen, > .fa-pen-to-square, > .fa-gear) { background: #fffbeb; border-color: #fde68a; color: #b45309; }
        .btn-light:has(> .fa-eye, > .fa-magnifying-glass, > .fa-folder-open, > .fa-circle-info), .btn-outline-secondary:has(> .fa-eye, > .fa-folder-open) { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
        .btn-light:has(> .fa-download, > .fa-file-pdf, > .fa-file-excel, > .fa-file-csv, > .fa-upload, > .fa-qrcode, > .fa-print), .btn-outline-secondary:has(> .fa-download, > .fa-upload) { background: #f0fdf4; border-color: #bbf7d0; color: #15803d; }
        .btn-light:has(> .fa-arrow-left, > .fa-arrow-right) { background: #fff; }

        /* Opções em formato de botão (radio): a escolhida fica cheia e com contorno, as demais claras */
        .btn-check:checked + .btn-outline-success { background: #16a34a; border-color: #16a34a; color: #fff; box-shadow: 0 0 0 .2rem rgba(22,163,74,.25); }
        .btn-check:checked + .btn-outline-primary { background: #1d4ed8; border-color: #1d4ed8; color: #fff; box-shadow: 0 0 0 .2rem rgba(29,78,216,.25); }
        .btn-check:checked + .btn-outline-warning { background: #d97706; border-color: #d97706; color: #fff; box-shadow: 0 0 0 .2rem rgba(217,119,6,.25); }
        .btn-check:checked + .btn-outline-secondary { background: #475569; border-color: #475569; color: #fff; box-shadow: 0 0 0 .2rem rgba(71,85,105,.25); }
        .btn-check:focus-visible + .btn { outline: 2px solid #0ea5e9; outline-offset: 2px; }

        /* Tons extras para distinguir ações lado a lado (barras de ferramentas) */
        .btn-tone-indigo { color: #4338ca; background: #eef2ff; border: 1px solid #c7d2fe; } .btn-tone-indigo:hover { background: #4338ca; color: #fff; border-color: #4338ca; }
        .btn-tone-purple { color: #7e22ce; background: #faf5ff; border: 1px solid #e9d5ff; } .btn-tone-purple:hover { background: #7e22ce; color: #fff; border-color: #7e22ce; }
        .btn-tone-teal   { color: #0f766e; background: #f0fdfa; border: 1px solid #99f6e4; } .btn-tone-teal:hover   { background: #0f766e; color: #fff; border-color: #0f766e; }
        .btn-tone-amber  { color: #b45309; background: #fffbeb; border: 1px solid #fde68a; } .btn-tone-amber:hover  { background: #d97706; color: #fff; border-color: #d97706; }
        .btn-tone-slate  { color: #334155; background: #f1f5f9; border: 1px solid #cbd5e1; } .btn-tone-slate:hover  { background: #334155; color: #fff; border-color: #334155; }
        /* Barras de botões nunca estouram a tela: quebram de linha */
        .d-flex:has(> .btn + .btn), .d-flex:has(> .btn + form), .d-flex:has(> form + .btn) { flex-wrap: wrap; }

        /* ===== Tabelas: rolagem lateral em vez de quebrar, cabeçalho legível, ações sem quebrar linha ===== */
        .table-responsive { border-radius: 14px; -webkit-overflow-scrolling: touch; scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
        .table-responsive > .table:has(th:nth-child(5)) { min-width: 760px; }
        .table-responsive > .table:has(th:nth-child(8)) { min-width: 1000px; }
        .table { --bs-table-hover-bg: #f0fdf4; }
        .table > thead th { white-space: nowrap; font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; }
        .table > tbody > tr > td { vertical-align: middle; }
        .table td:has(> .btn), .table td:has(> form), .table td:has(> .d-flex > .btn) { white-space: nowrap; }
        .table td > form { display: inline-block; margin: 0; }
        .table td > .btn + .btn, .table td > .btn + form, .table td > form + .btn, .table td > form + form { margin-left: .25rem; }
        .table td.text-end { min-width: 90px; }
        @media (max-width: 767.98px) {
            .glass-card { padding: 1rem; border-radius: 16px; }
            .table-responsive { margin-left: -.25rem; margin-right: -.25rem; box-shadow: inset -12px 0 10px -12px rgba(0,0,0,.18); }
            .btn-toolbar-stack, .d-flex.flex-wrap > .btn { flex: 1 1 auto; }
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

            <a href="{{ route('trabalhos.index') }}" class="nav-link {{ request()->routeIs('trabalhos.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-lines w-25 text-center"></i> Meus Trabalhos
            </a>

            @if(\Illuminate\Support\Facades\DB::table('evento_user')->where('user_id', Auth::id())->where('role', 'avaliador')->exists())
                <a href="{{ route('avaliador.avaliacoes.index') }}" class="nav-link {{ request()->routeIs('avaliador.avaliacoes.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-clipboard-check w-25 text-center"></i> Minhas Avaliações
                </a>
                <a href="{{ route('avaliador.apresentacoes.index') }}" class="nav-link {{ request()->routeIs('avaliador.apresentacoes.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-person-chalkboard w-25 text-center"></i> Apresentações
                </a>
            @endif

            @if(Auth::user()->role !== 'admin' && \Illuminate\Support\Facades\DB::table('evento_user')->where('user_id', Auth::id())->where('role', 'recepcao')->exists())
                <a href="{{ route('recepcao.index') }}" class="nav-link {{ request()->routeIs('recepcao.*') || request()->routeIs('admin.eventos.credenciamento.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-id-badge w-25 text-center"></i> Recepção
                </a>
            @endif

            @if(Auth::user()->role !== 'admin' && \App\Models\Evento::gestaoTrabalhosPor(Auth::user())->exists())
                <a href="{{ route('gestao.trabalhos.eventos') }}" class="nav-link {{ request()->routeIs('gestao.trabalhos.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-inbox w-25 text-center"></i> Gestão de Trabalhos
                </a>
            @endif

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

                    <a href="{{ route('gestao.trabalhos.eventos') }}" class="nav-link {{ request()->routeIs('gestao.trabalhos.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-inbox w-25 text-center"></i> Trabalhos
                    </a>

                    @can('superadmin')
                    <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                        <i class="fa-solid fa-layer-group w-25 text-center"></i> Usuários
                    </a>
                    @endcan
                </div>
            @endif

            <div class="mt-4 pt-4 border-top border-secondary border-opacity-25">
                <small class="text-uppercase text-secondary fw-bold mb-2 px-3" style="font-size: 0.7rem; letter-spacing: 1px;">Configurações</small>

                <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}">
                    <i class="fa-solid fa-user-gear w-25 text-center"></i> Minha Conta
                </a>

                <a href="{{ route('privacidade') }}" class="nav-link" target="_blank">
                    <i class="fa-solid fa-shield-halved w-25 text-center"></i> Privacidade
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
    <x-feedback />
</body>
</html>
