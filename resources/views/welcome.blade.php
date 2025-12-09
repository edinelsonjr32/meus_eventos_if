<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'CertificaIF') }} - Portal de Eventos</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #064e3b;
            --accent: #10b981;
            --dark: #0f172a;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            overflow-x: hidden;
        }

        /* Navbar Transparente com Blur */
        .navbar-glass {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1rem 0;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--primary) 0%, #065f46 60%, #047857 100%);
            padding: 6rem 0 8rem 0;
            position: relative;
            color: white;
            clip-path: polygon(0 0, 100% 0, 100% 90%, 0 100%);
        }
        .hero-pattern {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-image: radial-gradient(rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.6;
        }

        /* Cards de Eventos */
        .event-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .event-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.15);
        }
        .card-header-img {
            height: 140px;
            background: linear-gradient(45deg, #e2e8f0, #f1f5f9);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .date-badge {
            position: absolute;
            top: 15px; right: 15px;
            background: white;
            border-radius: 12px;
            padding: 5px 12px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            font-weight: 800;
            line-height: 1.1;
            color: var(--primary);
        }
        .date-day { font-size: 1.2rem; display: block; }
        .date-month { font-size: 0.7rem; text-transform: uppercase; }

        /* Validação Section */
        .validation-section {
            margin-top: -4rem;
            position: relative;
            z-index: 10;
        }
        .validation-box {
            background: white;
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            text-align: center;
            border: 1px solid rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-glass fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-success d-flex align-items-center gap-2" href="#">
                <i class="fa-solid fa-graduation-cap fs-3"></i>
                <span class="text-dark">Certifica<span class="text-success">IF</span></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto gap-2">
                    <li class="nav-item">
                        <a class="nav-link fw-bold text-secondary" href="#eventos">Eventos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold text-secondary" href="#validar">Validar</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ url('/admin/dashboard') }}" class="btn btn-dark rounded-pill px-4 fw-bold">Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="btn btn-outline-success rounded-pill px-4 fw-bold">Acessar</a>
                            @endauth
                        @endif
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="hero-pattern"></div>
        <div class="container position-relative z-1 text-center">
            <span class="badge bg-white bg-opacity-10 border border-white border-opacity-25 px-3 py-2 rounded-pill mb-4 fw-normal">
                <i class="fa-solid fa-building-columns me-2"></i> IFPA Campus Óbidos
            </span>
            <h1 class="display-3 fw-bolder mb-4">Portal de Eventos Acadêmicos</h1>
            <p class="lead opacity-75 mb-5 mx-auto" style="max-width: 700px;">
                Acompanhe a agenda oficial, realize inscrições e emita seus certificados de forma simples e digital.
            </p>
            <div class="d-flex gap-3 justify-content-center">
                <a href="#eventos" class="btn btn-light rounded-pill px-5 py-3 fw-bold text-success shadow-lg">
                    Ver Programação
                </a>
                <a href="#validar" class="btn btn-outline-light rounded-pill px-5 py-3 fw-bold">
                    Validar Certificado
                </a>
            </div>
        </div>
    </header>

    <section class="container mb-5" id="eventos" style="margin-top: -4rem;">
        <div class="d-flex justify-content-between align-items-end mb-4 px-2">
            <h3 class="fw-bold m-0 text-white" style="text-shadow: 0 2px 4px rgba(0,0,0,0.1);">Próximos Eventos</h3>
        </div>

        @if($proximosEventos->isEmpty())
            <div class="bg-white rounded-4 p-5 text-center shadow-sm border">
                <div class="bg-light rounded-circle d-inline-flex p-4 mb-3">
                    <i class="fa-regular fa-calendar-xmark fs-1 text-secondary opacity-50"></i>
                </div>
                <h4 class="fw-bold text-dark">Nenhum evento agendado</h4>
                <p class="text-secondary">No momento não há inscrições abertas. Volte em breve!</p>
            </div>
        @else
            <div class="row g-4">
                @foreach($proximosEventos as $evento)
                <div class="col-md-6 col-lg-4">
                    <div class="event-card group">
                        <div class="card-header-img" style="background: {{ $evento->configuracoes['cor_fundo'] ?? 'linear-gradient(45deg, #064e3b, #10b981)' }}">
                            <i class="fa-solid fa-calendar-check text-white fs-1 opacity-25"></i>

                            <div class="date-badge">
                                <span class="date-day">{{ $evento->data_inicio->format('d') }}</span>
                                <span class="date-month">{{ $evento->data_inicio->format('M') }}</span>
                            </div>
                        </div>

                        <div class="p-4 d-flex flex-column flex-grow-1">
                            <div class="mb-2">
                                @if($evento->data_inicio->isFuture())
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill">Em Breve</span>
                                @else
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill">Acontecendo</span>
                                @endif
                            </div>

                            <h5 class="fw-bold text-dark mb-2 text-truncate" title="{{ $evento->titulo }}">
                                {{ $evento->titulo }}
                            </h5>

                            <p class="small text-secondary mb-4">
                                <i class="fa-solid fa-location-dot me-1 text-danger opacity-75"></i> {{ $evento->local }}
                            </p>

                            <div class="mt-auto">
                                <a href="{{ route('evento.publico.show', $evento->slug) }}" class="btn btn-outline-dark w-100 rounded-pill fw-bold hover-bg-success">
                                    Inscrever-se <i class="fa-solid fa-arrow-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="py-5 bg-light" id="validar">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="validation-box">
                        <div class="mb-4">
                            <i class="fa-solid fa-shield-halved fs-1 text-success mb-3"></i>
                            <h2 class="fw-bold">Autenticidade Documental</h2>
                            <p class="text-secondary mx-auto" style="max-width: 500px;">
                                Possui um certificado emitido pelo IFPA? Digite o código de validação (hash) encontrado no rodapé do documento para verificar sua legitimidade.
                            </p>
                        </div>

                        <form action="" method="GET" onsubmit="event.preventDefault(); window.location.href='/certificado/validar/'+document.getElementById('hashInput').value">
                            <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden border">
                                <span class="input-group-text bg-white border-0 ps-4 text-muted">
                                    <i class="fa-solid fa-barcode"></i>
                                </span>
                                <input type="text" id="hashInput" class="form-control border-0" placeholder="Cole o código do certificado aqui..." required>
                                <button class="btn btn-success px-5 fw-bold" type="submit">Verificar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-white py-5 border-top">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-md-6 text-center text-md-start">
                    <h5 class="fw-bold text-success mb-1">CertificaIF</h5>
                    <p class="small text-secondary mb-0">Sistema de Gestão de Eventos e Certificação</p>
                    <p class="small text-secondary mt-2">
                        &copy; {{ date('Y') }} Instituto Federal do Pará - Campus Óbidos.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="https://obidos.ifpa.edu.br/" target="_blank" class="text-decoration-none text-secondary small fw-bold me-3">Site do Campus</a>
                    <a href="{{ route('login') }}" class="text-decoration-none text-secondary small fw-bold">Acesso Restrito</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
