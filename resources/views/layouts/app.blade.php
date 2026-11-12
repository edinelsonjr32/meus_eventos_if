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

        /* ===== Tabelas em cartão: cada linha vira um cartão (rótulo + valor). Entra no celular OU quando a tabela não cabe no espaço (script abaixo) ===== */
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
        .tabela-cartoes td .sb { display: inline-block; white-space: normal; max-width: 100%; text-align: left; line-height: 1.3; border-radius: 12px; margin: 0 0 .25rem .25rem; }
        .tabela-cartoes td .sb i { margin-right: .35rem; }
        .tabela-cartoes td .sb .sb-extra { display: inline; }
        /* Tabelas que precisam continuar largas (ex.: ranking): rolagem com aviso visível */
        .table-responsive { position: relative; }
        .aviso-rolagem { display: flex; align-items: center; gap: .4rem; font-size: .75rem; font-weight: 600; color: #0f766e; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 999px; padding: .25rem .75rem; margin: 0 0 .5rem; width: fit-content; }
        .aviso-rolagem.oculto { display: none; }

        /* Larguras intermediárias (tablet / janela estreita): colunas laterais ficam apertadas, então botões e formulários quebram de linha */
        .pagination { flex-wrap: wrap; row-gap: .25rem; }
        @media (max-width: 1199.98px) {
            .glass-card .btn.text-nowrap { white-space: normal !important; }
            .glass-card form.d-flex { flex-wrap: wrap; }
            .glass-card .d-flex.justify-content-between:not(.flex-nowrap) { flex-wrap: wrap; gap: .5rem; }
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
        }

        /* =====================================================================
           SISTEMA VISUAL — tokens e componentes (verde institucional do IFPA)
           ===================================================================== */
        :root {
            --brand-950: #022c22; --brand-900: #064e3b; --brand-800: #065f46; --brand-700: #047857; --brand-600: #059669;
            --brand-500: #10b981; --brand-400: #34d399; --brand-100: #d1fae5; --brand-50: #ecfdf5;
            --ink: #0f172a; --ink-2: #334155; --muted: #64748b; --line: #e4e9ef; --bg: #f3f6f4;
            --r-md: 14px; --r-lg: 20px;
            --sh-1: 0 1px 2px rgba(15,23,42,.04), 0 1px 3px rgba(15,23,42,.05);
            --sh-2: 0 4px 6px -2px rgba(15,23,42,.05), 0 12px 28px -10px rgba(15,23,42,.12);
            --sidebar-width: 272px;
            --bs-link-color-rgb: 4,120,87; --bs-link-hover-color-rgb: 6,78,59;
        }
        html { scroll-behavior: smooth; }
        body { background: radial-gradient(1100px 380px at 85% -8%, rgba(16,185,129,.09), transparent 70%), var(--bg); color: var(--ink); font-size: .94rem; -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; }
        ::selection { background: var(--brand-100); color: var(--brand-900); }

        /* --- Menu lateral --- */
        .sidebar { background: linear-gradient(185deg, #053b2d 0%, #064e3b 55%, #065f46 100%); padding: 1.15rem .9rem; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,.06); box-shadow: 6px 0 28px -12px rgba(2,44,34,.45); }
        .sidebar-marca { display: flex; align-items: center; gap: .75rem; padding: .3rem .5rem 1.05rem; margin-bottom: .5rem; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,.1); }
        .sidebar-marca-icone { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #34d399, #10b981); display: grid; place-items: center; color: #fff; font-size: 1.1rem; box-shadow: 0 8px 16px -6px rgba(16,185,129,.6); flex: 0 0 40px; }
        .sidebar-marca-texto strong { display: block; color: #fff; font-size: 1rem; line-height: 1.15; letter-spacing: -.01em; }
        .sidebar-marca-texto small { color: #a7f3d0; font-size: .7rem; }
        .sidebar-nav { flex: 1 1 auto; overflow-y: auto; flex-wrap: nowrap; margin: 0 -.35rem; padding: 0 .35rem; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.18) transparent; }
        .nav-secao { font-size: .66rem; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: #6ee7b7; opacity: .85; padding: .15rem .8rem; margin: 1rem 0 .35rem; display: flex; align-items: center; gap: .4rem; }
        .sidebar .nav-site { background: rgba(255,255,255,.07); margin: .15rem 0 .25rem; }
        .nav-grupo { margin-top: .5rem; padding-top: .1rem; border-top: 1px solid rgba(255,255,255,.09); }
        .sidebar .nav-link { color: #c9e9de; padding: .6rem .8rem; border-radius: 11px; margin-bottom: 2px; font-weight: 500; font-size: .9rem; gap: .7rem; position: relative; transform: none; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,.08); color: #fff; transform: none; }
        .sidebar .nav-link.active { background: rgba(16,185,129,.24); color: #fff; box-shadow: inset 0 0 0 1px rgba(110,231,183,.18); font-weight: 600; }
        .sidebar .nav-link.active::before { content: ""; position: absolute; left: -.35rem; top: 22%; bottom: 22%; width: 4px; border-radius: 0 4px 4px 0; background: #34d399; }
        .nav-ico { width: 20px; flex: 0 0 20px; text-align: center; font-size: .95rem; opacity: .92; }
        .sidebar .nav-sair { color: #fecaca; }
        .sidebar .nav-sair:hover { background: rgba(239,68,68,.18); color: #fff; }
        .sidebar-usuario { display: flex; align-items: center; gap: .7rem; padding: .85rem .5rem 0; margin-top: .6rem; border-top: 1px solid rgba(255,255,255,.1); }
        .sidebar-avatar { width: 36px; height: 36px; flex: 0 0 36px; border-radius: 50%; background: rgba(255,255,255,.14); color: #fff; font-weight: 700; display: grid; place-items: center; }
        .sidebar-usuario-texto { min-width: 0; }
        .sidebar-usuario-texto strong { color: #fff; font-size: .82rem; display: block; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sidebar-usuario-texto small { color: #a7f3d0; font-size: .7rem; }

        /* --- Conteúdo --- */
        .pagina { max-width: 1320px; margin: 0 auto; }
        .topbar-mobile { display: none; }
        .main-content h3, .main-content .h3 { font-weight: 700; letter-spacing: -.02em; font-size: 1.6rem; }
        .main-content h4 { font-weight: 700; letter-spacing: -.015em; }
        .main-content h5, .main-content h6 { letter-spacing: -.005em; }
        .main-content .text-secondary { color: var(--muted) !important; }
        .main-content small.text-secondary { font-size: .82rem; }
        .main-content hr { border-color: var(--line); opacity: 1; }

        /* --- Cartões --- */
        .glass-card { background: #fff; border: 1px solid var(--line); border-radius: var(--r-lg); box-shadow: var(--sh-1); padding: 1.5rem; }
        .glass-card:hover { transform: none; }
        .glass-card h6.fw-bold.text-success, .glass-card h6.text-success { font-size: 1rem; color: var(--brand-800) !important; letter-spacing: -.005em; }
        .glass-card .bg-light { background: #f6f8fa !important; }

        /* --- Botões --- */
        .btn { font-weight: 600; border-radius: 11px; padding: .52rem 1.05rem; font-size: .9rem; letter-spacing: -.003em; }
        .btn-sm { padding: .36rem .8rem; font-size: .8rem; border-radius: 9px; }
        .btn:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(16,185,129,.35); }
        .btn-primary-custom { background: linear-gradient(180deg, var(--brand-600), var(--brand-700)); border-radius: 11px; padding: .62rem 1.3rem; box-shadow: 0 1px 2px rgba(4,120,87,.35), 0 8px 18px -8px rgba(4,120,87,.6); font-size: .9rem; letter-spacing: 0; }
        .btn-primary-custom:hover { background: linear-gradient(180deg, #06a06f, var(--brand-800)); transform: translateY(-1px); box-shadow: 0 1px 2px rgba(4,120,87,.4), 0 12px 22px -8px rgba(4,120,87,.65); }
        .btn-primary-custom:active { transform: translateY(0); }
        .btn-success { background: var(--brand-600); border-color: var(--brand-600); }
        .btn-success:hover { background: var(--brand-700); border-color: var(--brand-700); }
        .btn-light { background: #fff; border-color: var(--line); }

        /* --- Formulários --- */
        .form-control, .form-select { background-color: #fff; border: 1px solid #d5dce5; border-radius: 11px; padding: .62rem .85rem; font-size: .92rem; color: var(--ink); }
        .form-control-sm, .form-select-sm { padding: .4rem .65rem; border-radius: 9px; font-size: .85rem; }
        .form-control::placeholder { color: #9aa7b6; }
        .form-control:focus, .form-select:focus { background-color: #fff; border-color: var(--brand-500); box-shadow: 0 0 0 4px rgba(16,185,129,.14); }
        .form-control:disabled, .form-select:disabled, .form-control[readonly] { background-color: #f3f6f8; }
        .form-check-input:checked { background-color: var(--brand-600); border-color: var(--brand-600); }
        .form-check-input:focus { border-color: var(--brand-500); box-shadow: 0 0 0 4px rgba(16,185,129,.15); }
        .main-content label.small.fw-bold, .main-content .form-label { color: var(--ink-2) !important; font-size: .76rem !important; letter-spacing: .01em; margin-bottom: .35rem; }
        .main-content .form-text { color: var(--muted); font-size: .78rem; }

        /* --- Tabelas --- */
        .table { --bs-table-bg: transparent; margin-bottom: 0; }
        .table > thead th { background: #f6f8fa; color: #5b6b7d; font-size: .67rem; letter-spacing: .07em; padding: .8rem 1rem; border-bottom: 1px solid var(--line); }
        .table > tbody > tr > td { padding: .85rem 1rem; border-color: #edf0f4; }
        .table-hover > tbody > tr:hover > * { background-color: #f4faf7; }
        .table-responsive { border: 1px solid var(--line); border-radius: var(--r-md); background: #fff; }
        .table-responsive:has(> .table.tabela-cartoes) { border: 0; background: transparent; }
        .glass-card .table-responsive { box-shadow: none; }

        /* --- Alertas, paginação, abas --- */
        .alert { border: 1px solid transparent; border-left-width: 4px; border-radius: 12px; padding: .8rem 1rem; font-size: .9rem; }
        .alert-success { background: #ecfdf5; border-color: #a7f3d0; border-left-color: #10b981; color: #065f46; }
        .alert-danger { background: #fef2f2; border-color: #fecaca; border-left-color: #ef4444; color: #7f1d1d; }
        .alert-warning { background: #fffbeb; border-color: #fde68a; border-left-color: #f59e0b; color: #78350f; }
        .alert-info { background: #eff6ff; border-color: #bfdbfe; border-left-color: #3b82f6; color: #1e3a8a; }
        .alert-secondary, .alert-light { background: #f6f8fa; border-color: var(--line); border-left-color: #94a3b8; color: var(--ink-2); }
        .pagination { gap: .15rem; }
        .pagination .page-link { border-radius: 9px !important; color: var(--ink-2); border: 1px solid var(--line); min-width: 36px; text-align: center; }
        .pagination .page-item.active .page-link { background: var(--brand-600); border-color: var(--brand-600); color: #fff; }
        .nav-tabs .nav-link, .nav-pills .nav-link { border-radius: 10px; }

        /* Linha de filtros: colunas que se ajustam ao espaço (nada de campos cortados) */
        .row.filtros-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(175px, 1fr)); gap: .6rem; margin: 0; }
        .row.filtros-grid > * { width: auto; max-width: none; flex: none; padding: 0; margin: 0; }
        .row.filtros-grid > :first-child { grid-column: span 2; }
        @media (max-width: 575.98px) { .row.filtros-grid > :first-child { grid-column: auto; } }
        .glass-card .d-flex.gap-2.mt-auto { flex-wrap: nowrap !important; }
        .glass-card .d-flex.gap-2.mt-auto > .btn:not(.flex-fill) { flex: 0 0 auto; }
        .main-content h2 { font-weight: 800; letter-spacing: -.025em; font-size: 1.75rem; }
        .item-agenda { border: 1px solid var(--line) !important; border-left: 4px solid var(--brand-500) !important; border-radius: 13px !important; background: #fbfdfc; transition: background .15s, box-shadow .15s; }
        .item-agenda:hover { background: #f3faf6; box-shadow: var(--sh-1); }
        .glass-card.border-primary, .glass-card.border-success { border-color: var(--brand-500) !important; box-shadow: 0 0 0 3px rgba(16,185,129,.12), var(--sh-1); }
        /* Botões: cor única, planos, sem sombra, degradê nem movimento (vale para todo o sistema) */
        .btn, .btn-primary-custom, .btn:hover, .btn:active, .btn-primary-custom:hover, .btn-primary-custom:active, .btn.shadow, .btn.shadow-sm, .btn.shadow-lg {
            box-shadow: none !important; transform: none !important; background-image: none !important;
        }
        .btn-primary-custom { background: var(--brand-600); }
        .btn-primary-custom:hover { background: var(--brand-700); }
        .btn:focus-visible { box-shadow: none !important; outline: 3px solid rgba(16,185,129,.4); outline-offset: 2px; }
        @media (min-width: 769px) {
            .main-content { margin-left: var(--sidebar-width); padding: 2rem 2.25rem 3rem; }
        }
        @media (max-width: 768px) {
            .topbar-mobile { position: sticky; top: 0; z-index: 900; display: flex; align-items: center; justify-content: space-between; margin: -.85rem -.85rem 1rem; padding: .7rem 1rem; background: linear-gradient(135deg, #053b2d, #065f46); color: #fff; box-shadow: 0 6px 16px -8px rgba(2,44,34,.6); }
            .topbar-marca { font-weight: 700; display: flex; align-items: center; gap: .55rem; letter-spacing: -.01em; }
            .topbar-marca i { color: #34d399; }
            .btn-menu { border: 0; background: rgba(255,255,255,.14); color: #fff; width: 40px; height: 40px; border-radius: 11px; display: grid; place-items: center; }
            .glass-card { padding: 1.1rem; border-radius: 16px; }
            .main-content { overflow-x: clip; } /* clip não cria área de rolagem: a barra superior fixa funciona */
        }
    </style>
    @include('partials.favicon')
</head>
<body>

    <nav class="sidebar" id="sidebar" aria-label="Menu principal">
        <a href="{{ route('dashboard') }}" class="sidebar-marca" title="Ir para o início">
            <span class="sidebar-marca-icone"><i class="fa-solid fa-graduation-cap"></i></span>
            <span class="sidebar-marca-texto">
                <strong>MeusEventosIF</strong>
                <small>IFPA · Campus Óbidos</small>
            </span>
        </a>

        <a href="{{ route('home') }}" class="nav-link nav-site" title="Abrir o site público (portal de eventos)">
            <i class="fa-solid fa-globe nav-ico"></i> Ir para o Site
            <i class="fa-solid fa-arrow-up-right-from-square ms-auto" style="font-size:.7rem;opacity:.7"></i>
        </a>

        <div class="nav flex-column sidebar-nav">

            @php
                $ehAvaliador = \Illuminate\Support\Facades\DB::table('evento_user')->where('user_id', Auth::id())->where('role', 'avaliador')->exists();
                $ehAdmin = Auth::user()->role === 'admin';
                $ehRecepcao = !$ehAdmin && \Illuminate\Support\Facades\DB::table('evento_user')->where('user_id', Auth::id())->where('role', 'recepcao')->exists();
                $ehGestorTrab = !$ehAdmin && \App\Models\Evento::gestaoTrabalhosPor(Auth::user())->exists();
            @endphp

            {{-- ===== Área do participante ===== --}}
            <div class="nav-secao">Área do participante</div>

            <a href="{{ route('inscrito.dashboard') }}" class="nav-link {{ request()->routeIs('inscrito.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-check nav-ico"></i> Minha Agenda
            </a>

            <a href="{{ route('trabalhos.index') }}" class="nav-link {{ request()->routeIs('trabalhos.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-lines nav-ico"></i> Meus Trabalhos
            </a>

            <a href="{{ route('propostas.minhas') }}" class="nav-link {{ request()->routeIs('propostas.*') ? 'active' : '' }}">
                <i class="fa-solid fa-chalkboard-user nav-ico"></i> Minhas Propostas
            </a>

            <a href="{{ route('voluntariado.meu') }}" class="nav-link {{ request()->routeIs('voluntariado.*') ? 'active' : '' }}">
                <i class="fa-solid fa-hand-holding-heart nav-ico"></i> Voluntariado
            </a>

            {{-- ===== Área do avaliador ===== --}}
            @if($ehAvaliador)
                <div class="nav-grupo">
                    <div class="nav-secao"><i class="fa-solid fa-clipboard-check"></i> Área do avaliador</div>
                    <a href="{{ route('avaliador.avaliacoes.index') }}" class="nav-link {{ request()->routeIs('avaliador.avaliacoes.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-file-pen nav-ico"></i> Trab. Escrito
                    </a>
                    <a href="{{ route('avaliador.apresentacoes.index') }}" class="nav-link {{ request()->routeIs('avaliador.apresentacoes.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-person-chalkboard nav-ico"></i> Trab. Oral
                    </a>
                </div>
            @endif

            {{-- ===== Área de gestão ===== --}}
            @if($ehAdmin || $ehRecepcao || $ehGestorTrab)
                <div class="nav-grupo">
                    <div class="nav-secao"><i class="fa-solid fa-lock"></i> Área de gestão</div>

                    @if($ehAdmin)
                        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                            <i class="fa-solid fa-gauge-high nav-ico"></i> Dashboard
                        </a>
                        <a href="{{ route('admin.eventos.index') }}" class="nav-link {{ request()->routeIs('admin.eventos*') ? 'active' : '' }}">
                            <i class="fa-solid fa-layer-group nav-ico"></i> Meus Eventos
                        </a>
                    @endif

                    @if($ehRecepcao)
                        <a href="{{ route('recepcao.index') }}" class="nav-link {{ request()->routeIs('recepcao.*') || request()->routeIs('admin.eventos.credenciamento.*') ? 'active' : '' }}">
                            <i class="fa-solid fa-id-badge nav-ico"></i> Recepção
                        </a>
                    @endif

                    @if($ehAdmin || $ehGestorTrab)
                        <a href="{{ route('gestao.trabalhos.eventos') }}" class="nav-link {{ request()->routeIs('gestao.trabalhos.*') ? 'active' : '' }}">
                            <i class="fa-solid fa-inbox nav-ico"></i> {{ $ehAdmin ? 'Trabalhos' : 'Gestão de Trabalhos' }}
                        </a>
                    @endif

                    @if($ehAdmin)
                        <a href="{{ route('admin.cursos.index') }}" class="nav-link {{ request()->routeIs('admin.cursos*', 'admin.turmas*') ? 'active' : '' }}">
                            <i class="fa-solid fa-school nav-ico"></i> Cursos e Turmas
                        </a>
                    @endif

                    @can('gerir-usuarios')
                        <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                            <i class="fa-solid fa-users nav-ico"></i> Usuários
                        </a>
                    @endcan
                </div>
            @endif

            <div class="nav-grupo">
                <div class="nav-secao">Conta</div>

                <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}">
                    <i class="fa-solid fa-user-gear nav-ico"></i> Minha Conta
                </a>

                <a href="{{ route('privacidade') }}" class="nav-link" target="_blank">
                    <i class="fa-solid fa-shield-halved nav-ico"></i> Privacidade
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-link nav-sair w-100 text-start border-0 bg-transparent">
                        <i class="fa-solid fa-power-off nav-ico"></i> Sair
                    </button>
                </form>
            </div>
        </div>

        <div class="sidebar-usuario">
            <span class="sidebar-avatar">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
            <span class="sidebar-usuario-texto">
                <strong title="{{ Auth::user()->name }}">{{ Auth::user()->name }}</strong>
                <small>{{ Auth::user()->role === 'admin' ? 'Administrador' : 'Participante' }}</small>
            </span>
        </div>
    </nav>

    <div class="sidebar-backdrop" onclick="document.getElementById('sidebar').classList.remove('active')" aria-hidden="true"></div>

    <main class="main-content">
        <header class="topbar-mobile d-md-none">
            <span class="topbar-marca"><i class="fa-solid fa-graduation-cap"></i> MeusEventosIF</span>
            <button class="btn-menu" onclick="document.getElementById('sidebar').classList.toggle('active')" aria-label="Abrir menu"><i class="fa-solid fa-bars"></i></button>
        </header>

        <div class="pagina">
            {{ $slot }}
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tabelas: guarda o rótulo da coluna em cada célula (usado pelo modo "cartão" no celular).
        // Quem precisar manter a tabela larga usa a classe "table-no-stack": aparece um aviso de rolagem lateral.
        (function () {
            var tabelas = function () { return document.querySelectorAll('.main-content table.table:not(.table-no-stack)'); };

            // 1) rótulo de cada célula = título da coluna
            function rotular(t) {
                var chave = t.rows.length + '';
                if (t.dataset.rotulada === chave) return;
                t.dataset.rotulada = chave;
                var cab = t.tHead && t.tHead.rows[0] ? Array.prototype.map.call(t.tHead.rows[0].cells, function (c) { return { txt: (c.innerText || '').trim(), n: c.colSpan || 1 }; }) : [];
                var rotulos = [];
                cab.forEach(function (c) { for (var i = 0; i < c.n; i++) rotulos.push(c.txt); });
                if (!rotulos.length) { t.dataset.semCabecalho = '1'; return; }
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
            }

            // 2) cartão no celular, ou em qualquer largura em que a tabela não caiba no espaço disponível
            function ajustar() {
                var celular = window.innerWidth < 768;
                tabelas().forEach(function (t) {
                    rotular(t);
                    if (t.dataset.semCabecalho) return;
                    var caixa = t.closest('.table-responsive') || t.parentElement;
                    var antes = t.classList.contains('tabela-cartoes');
                    t.classList.remove('tabela-cartoes');
                    var precisa = celular || t.scrollWidth > caixa.clientWidth + 2;
                    if (precisa) t.classList.add('tabela-cartoes');
                    else if (antes) { /* voltou ao normal */ }
                });
                avisos();
            }

            // 3) tabelas que ficam largas (sem cartão): avisa que dá para deslizar
            function avisos() {
                document.querySelectorAll('.main-content .table-responsive').forEach(function (w) {
                    var ja = w.previousElementSibling && w.previousElementSibling.classList.contains('aviso-rolagem');
                    var rola = !w.querySelector('.table.tabela-cartoes') && w.scrollWidth > w.clientWidth + 4;
                    if (rola && !ja) {
                        var a = document.createElement('div');
                        a.className = 'aviso-rolagem';
                        a.innerHTML = '<i class="fa-solid fa-arrows-left-right"></i> Deslize a tabela para o lado para ver mais colunas';
                        w.parentNode.insertBefore(a, w);
                        w.addEventListener('scroll', function () { a.classList.add('oculto'); }, { once: true, passive: true });
                    } else if (!rola && ja) {
                        w.previousElementSibling.remove();
                    }
                });
            }

            var tm;
            function agendar() { clearTimeout(tm); tm = setTimeout(ajustar, 120); }
            document.addEventListener('DOMContentLoaded', function () { ajustar(); setTimeout(ajustar, 600); });
            window.addEventListener('load', ajustar);
            window.addEventListener('resize', agendar);
            // conteúdo que chega depois (Alpine/AJAX): só reavalia se o número de linhas mudou
            new MutationObserver(function () {
                var mudou = false;
                tabelas().forEach(function (t) { if (t.dataset.rotulada !== t.rows.length + '') mudou = true; });
                if (mudou) agendar();
            }).observe(document.body, { childList: true, subtree: true });
        })();

        // No celular o menu fecha ao tocar em um item
        document.querySelectorAll('#sidebar a.nav-link').forEach(a => a.addEventListener('click', () => document.getElementById('sidebar').classList.remove('active')));
    </script>
    <x-feedback />
</body>
</html>
