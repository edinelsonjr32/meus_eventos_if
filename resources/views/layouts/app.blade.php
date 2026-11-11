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

        /* Texto livre (pareceres, mensagens): respeita as quebras e nunca estoura o cartão */
        .texto-quebra { white-space: pre-line; overflow-wrap: anywhere; word-break: break-word; max-width: 100%; }
        .glass-card, .glass-card details { min-width: 0; }

        /* Utilitários Alpine */
        [x-cloak] { display: none !important; }

        /* ===== Tabelas no celular: cada linha vira um cartão (rótulo + valor), sem rolagem lateral escondida ===== */
        .aviso-rolagem { display: none; }
        @media (max-width: 767.98px) {
            .table-responsive > .table.tabela-cartoes { min-width: 0 !important; }
            .table-responsive:has(> .table.tabela-cartoes) { overflow-x: visible; box-shadow: none; margin-left: 0; margin-right: 0; }
            .tabela-cartoes, .tabela-cartoes tbody, .tabela-cartoes tfoot { display: block; width: 100%; }
            .tabela-cartoes thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
            .tabela-cartoes tr { display: block; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: .35rem .85rem; margin-bottom: .75rem; box-shadow: 0 2px 8px -4px rgba(0,0,0,.08); }
            .tabela-cartoes tr.table-active, .tabela-cartoes tr[class*="detalhe"] { background: #f8fafc; }
            .tabela-cartoes td { display: block; position: relative; padding: .5rem 0 .5rem 40% !important; border: 0 !important; border-bottom: 1px dashed #e5e7eb !important; text-align: right !important; white-space: normal !important; min-width: 0 !important; background: transparent !important; overflow-wrap: anywhere; min-height: 2.2rem; }
            .tabela-cartoes td:last-child { border-bottom: 0 !important; }
            .tabela-cartoes td::before { content: attr(data-label); position: absolute; left: 0; top: .6rem; width: 36%; text-align: left; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #64748b; }
            .tabela-cartoes td:not([data-label]), .tabela-cartoes td[data-label=""] { padding-left: 0 !important; }
            .tabela-cartoes td:not([data-label])::before, .tabela-cartoes td[data-label=""]::before { content: none; }
            .tabela-cartoes td .btn { white-space: normal; margin: .15rem 0 .15rem .25rem; }
            .tabela-cartoes td[colspan] { padding-left: 0 !important; text-align: left !important; }
            .tabela-cartoes td .btn { white-space: normal; }
            /* Tabelas que precisam continuar largas (ex.: ranking): rolagem com aviso visível */
            .table-responsive { position: relative; }
            .aviso-rolagem { display: flex; align-items: center; gap: .4rem; font-size: .75rem; font-weight: 600; color: #0f766e; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 999px; padding: .25rem .75rem; margin: 0 0 .5rem; width: fit-content; }
            .aviso-rolagem.oculto { display: none; }
        }

        /* Mobile Responsive */
        .sidebar-backdrop { display: none; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: min(86vw, var(--sidebar-width)); box-shadow: none; }
            .sidebar.active { transform: translateX(0); box-shadow: 8px 0 32px rgba(0,0,0,.35); }
            .sidebar.active ~ .sidebar-backdrop { display: block; position: fixed; inset: 0; background: rgba(15,23,42,.55); z-index: 999; }
            .main-content { margin-left: 0; padding: .85rem; min-width: 0; overflow-x: hidden; }
            .main-content h3, .main-content .h3 { font-size: 1.25rem; }
            .main-content h4 { font-size: 1.1rem; }
            .main-content img, .main-content video, .main-content iframe { max-width: 100%; height: auto; }
            .main-content .btn { white-space: normal; }
            .main-content .d-flex.justify-content-between.align-items-center:not(.flex-nowrap) { flex-wrap: wrap; gap: .5rem; }
            .main-content .row > [class*="col-"] { min-width: 0; }
            .main-content dl.row dt { margin-top: .5rem; }
            .main-content .nav-tabs, .main-content .nav-pills { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .main-content pre, .main-content code { white-space: pre-wrap; word-break: break-word; }
            .main-content .form-control, .main-content .form-select { font-size: 16px; } /* evita o zoom automático do iOS ao focar */
            .modal-dialog { margin: .5rem; }
            /* Selos de status: quebram de linha em vez de vazar do cartão */
            .main-content .sb { display: inline-block; white-space: normal; max-width: 100%; text-align: left; line-height: 1.3; border-radius: 12px; padding: .3rem .65rem; overflow-wrap: anywhere; }
            .main-content .sb i { margin-right: .35rem; }
            .main-content .sb .sb-extra { display: inline; }
            .tabela-cartoes td .sb { margin: 0 0 .25rem .25rem; }
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

            @php
                $ehAvaliador = \Illuminate\Support\Facades\DB::table('evento_user')->where('user_id', Auth::id())->where('role', 'avaliador')->exists();
                $ehAdmin = Auth::user()->role === 'admin';
                $ehRecepcao = !$ehAdmin && \Illuminate\Support\Facades\DB::table('evento_user')->where('user_id', Auth::id())->where('role', 'recepcao')->exists();
                $ehGestorTrab = !$ehAdmin && \App\Models\Evento::gestaoTrabalhosPor(Auth::user())->exists();
            @endphp

            {{-- ===== Área do participante ===== --}}
            <small class="text-uppercase text-secondary fw-bold mb-2 px-3" style="font-size: 0.7rem; letter-spacing: 1px;">Área do participante</small>

            <a href="{{ route('inscrito.dashboard') }}" class="nav-link {{ request()->routeIs('inscrito.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-check w-25 text-center"></i> Minha Agenda
            </a>

            <a href="{{ route('trabalhos.index') }}" class="nav-link {{ request()->routeIs('trabalhos.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-lines w-25 text-center"></i> Meus Trabalhos
            </a>

            {{-- ===== Área do avaliador ===== --}}
            @if($ehAvaliador)
                <div class="mt-4 pt-4 border-top border-secondary border-opacity-25">
                    <small class="text-uppercase text-info fw-bold mb-2 px-3 d-block" style="font-size: 0.7rem; letter-spacing: 1px;">
                        <i class="fa-solid fa-clipboard-check me-1"></i> Área do avaliador
                    </small>
                    <a href="{{ route('avaliador.avaliacoes.index') }}" class="nav-link {{ request()->routeIs('avaliador.avaliacoes.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-file-pen w-25 text-center"></i> Trab. Escrito
                    </a>
                    <a href="{{ route('avaliador.apresentacoes.index') }}" class="nav-link {{ request()->routeIs('avaliador.apresentacoes.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-person-chalkboard w-25 text-center"></i> Trab. Oral
                    </a>
                </div>
            @endif

            {{-- ===== Área de gestão ===== --}}
            @if($ehAdmin || $ehRecepcao || $ehGestorTrab)
                <div class="mt-4 pt-4 border-top border-secondary border-opacity-25">
                    <small class="text-uppercase text-warning fw-bold mb-2 px-3 d-block" style="font-size: 0.7rem; letter-spacing: 1px;">
                        <i class="fa-solid fa-lock me-1"></i> Área de gestão
                    </small>

                    @if($ehAdmin)
                        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                            <i class="fa-solid fa-gauge-high w-25 text-center"></i> Dashboard
                        </a>
                        <a href="{{ route('admin.eventos.index') }}" class="nav-link {{ request()->routeIs('admin.eventos*') ? 'active' : '' }}">
                            <i class="fa-solid fa-layer-group w-25 text-center"></i> Meus Eventos
                        </a>
                    @endif

                    @if($ehRecepcao)
                        <a href="{{ route('recepcao.index') }}" class="nav-link {{ request()->routeIs('recepcao.*') || request()->routeIs('admin.eventos.credenciamento.*') ? 'active' : '' }}">
                            <i class="fa-solid fa-id-badge w-25 text-center"></i> Recepção
                        </a>
                    @endif

                    @if($ehAdmin || $ehGestorTrab)
                        <a href="{{ route('gestao.trabalhos.eventos') }}" class="nav-link {{ request()->routeIs('gestao.trabalhos.*') ? 'active' : '' }}">
                            <i class="fa-solid fa-inbox w-25 text-center"></i> {{ $ehAdmin ? 'Trabalhos' : 'Gestão de Trabalhos' }}
                        </a>
                    @endif

                    @can('superadmin')
                        <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                            <i class="fa-solid fa-users w-25 text-center"></i> Usuários
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

    <div class="sidebar-backdrop" onclick="document.getElementById('sidebar').classList.remove('active')" aria-hidden="true"></div>

    <main class="main-content">
        <nav class="navbar navbar-light bg-white rounded-3 shadow-sm mb-4 d-md-none p-3 border">
            <div class="container-fluid px-0">
                <span class="navbar-brand fw-bold text-success mb-0 h1"><i class="fa-solid fa-leaf me-2"></i> MeusEventosIf</span>
                <button class="btn btn-light border" onclick="document.getElementById('sidebar').classList.toggle('active')" aria-label="Abrir menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </nav>

        {{ $slot }}
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tabelas: guarda o rótulo da coluna em cada célula (usado pelo modo "cartão" no celular).
        // Quem precisar manter a tabela larga usa a classe "table-no-stack": aparece um aviso de rolagem lateral.
        (function () {
            function preparar(raiz) {
                raiz.querySelectorAll('.main-content table.table').forEach(function (t) {
                    if (t.dataset.preparada === (t.rows.length + '')) return;
                    t.dataset.preparada = t.rows.length + '';
                    if (t.classList.contains('table-no-stack')) return;
                    var cab = t.tHead && t.tHead.rows[0] ? Array.prototype.map.call(t.tHead.rows[0].cells, function (c) { return { txt: (c.innerText || '').trim(), n: c.colSpan || 1 }; }) : [];
                    var rotulos = [];
                    cab.forEach(function (c) { for (var i = 0; i < c.n; i++) rotulos.push(c.txt); });
                    if (!rotulos.length) return;
                    t.classList.add('tabela-cartoes');
                    Array.prototype.forEach.call(t.tBodies, function (tb) {
                        Array.prototype.forEach.call(tb.rows, function (tr) {
                            var i = 0;
                            Array.prototype.forEach.call(tr.cells, function (td) {
                                if (td.colSpan > 1) { i += td.colSpan; return; }
                                if (!td.hasAttribute('data-label')) td.setAttribute('data-label', rotulos[i] || '');
                                i++;
                            });
                        });
                    });
                });
            }
            function avisos() {
                if (window.innerWidth >= 768) return;
                document.querySelectorAll('.main-content .table-responsive').forEach(function (w) {
                    if (w.querySelector('.table.tabela-cartoes') || w.previousElementSibling && w.previousElementSibling.classList.contains('aviso-rolagem')) return;
                    if (w.scrollWidth <= w.clientWidth + 4) return;
                    var a = document.createElement('div');
                    a.className = 'aviso-rolagem';
                    a.innerHTML = '<i class="fa-solid fa-arrows-left-right"></i> Deslize a tabela para o lado para ver mais colunas';
                    w.parentNode.insertBefore(a, w);
                    w.addEventListener('scroll', function () { a.classList.add('oculto'); }, { once: true, passive: true });
                });
            }
            function tudo() { preparar(document); avisos(); }
            document.addEventListener('DOMContentLoaded', function () { tudo(); setTimeout(tudo, 600); });
            var t; new MutationObserver(function () { clearTimeout(t); t = setTimeout(tudo, 150); }).observe(document.body, { childList: true, subtree: true });
        })();

        // No celular o menu fecha ao tocar em um item
        document.querySelectorAll('#sidebar a.nav-link').forEach(a => a.addEventListener('click', () => document.getElementById('sidebar').classList.remove('active')));
    </script>
    <x-feedback />
</body>
</html>
