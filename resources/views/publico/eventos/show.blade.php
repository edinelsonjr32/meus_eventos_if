<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $evento->titulo }} - Inscrição Oficial</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <style>
        :root {
            --brand: {{ $evento->configuracoes['cor_fundo'] ?? '#059669' }};
            --brand-dark: color-mix(in srgb, var(--brand), black 20%);
            --brand-light: color-mix(in srgb, var(--brand), white 90%);
            --surface: #ffffff;
            --bg: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text-main); overflow-x: hidden; }

        /* HERO HEADER - Imersivo */
        .event-hero {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            padding: 6rem 0 8rem 0;
            color: white;
            position: relative;
            border-bottom-left-radius: 60px;
            border-bottom-right-radius: 60px;
            margin-bottom: 3rem;
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.15);
        }
        .hero-pattern {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-image: radial-gradient(rgba(255,255,255,0.15) 2px, transparent 2px);
            background-size: 32px 32px;
            opacity: 0.6;
        }

        /* CARD FLUTUANTE (Formulário) */
        .booking-card {
            background: var(--surface);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15);
            border: 1px solid rgba(0,0,0,0.04);
            position: sticky;
            top: 2rem;
            z-index: 50;
            overflow: hidden;
        }
        .booking-header {
            background: var(--brand-light);
            padding: 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        /* TIMELINE (Programação) */
        .timeline-section {
            position: relative;
            padding-left: 2rem;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 2.5rem;
            border-left: 2px dashed #cbd5e1;
            padding-left: 2.5rem;
        }
        .timeline-item:last-child { border-left: 2px dashed transparent; }

        .timeline-marker {
            position: absolute;
            left: -11px;
            top: 0;
            width: 20px;
            height: 20px;
            background: var(--brand);
            border: 4px solid white;
            border-radius: 50%;
            box-shadow: 0 0 0 3px var(--brand-light);
        }

        .activity-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .activity-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.05);
            border-color: var(--brand);
        }

        /* Form Controls */
        .form-control, .form-select {
            border-radius: 12px;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            background: white;
            border-color: var(--brand);
            box-shadow: 0 0 0 4px var(--brand-light);
        }

        /* Badge Custom */
        .badge-soft {
            background: var(--brand-light);
            color: var(--brand-dark);
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .top-nav {
            position: absolute;
            top: 0;
            width: 100%;
            padding: 1rem 0;
            z-index: 100;
        }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="container">
            <div class="d-flex justify-content-end gap-3">

                <a href="{{ route('home') }}" class="btn btn-sm btn-outline-light fw-bold px-3 shadow-sm">
                    <i class="fa-solid fa-home me-1"></i> Tela Inicial
                </a>

                @if(optional($evento->anais)->publicado())
                    <a href="{{ route('anais.index', $evento->slug) }}" class="btn btn-sm btn-outline-light fw-bold px-3 shadow-sm">
                        <i class="fa-solid fa-book me-1"></i> Anais
                    </a>
                @endif

                @if(optional($evento->trabalhoConfig)->estaAberta())
                    <a href="{{ route('trabalhos.create', $evento->slug) }}" class="btn btn-sm btn-warning fw-bold px-3 shadow-sm text-dark">
                        <i class="fa-solid fa-file-arrow-up me-1"></i> Submeter Trabalho
                    </a>
                @endif

                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-light fw-bold px-3 shadow-sm text-dark">
                        <i class="fa-solid fa-user-circle me-1"></i> Minha Área
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-sm btn-light fw-bold px-3 shadow-sm text-dark">
                        <i class="fa-solid fa-sign-in-alt me-1"></i> Entrar
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    @if(session('error'))
        <div class="container mt-3"><div class="alert alert-warning shadow-sm mb-0">{{ session('error') }}</div></div>
    @endif

    @php($bannerUrl = $evento->bannerUrl())
    @php($logoUrl = $evento->logoUrl())
    <header class="event-hero" @if($bannerUrl) style="background: linear-gradient(135deg, rgba(0,0,0,.55), rgba(0,0,0,.35)), url('{{ $bannerUrl }}') center / cover no-repeat;" @endif>
        <div class="hero-pattern"></div>
        <div class="container position-relative z-1">
            <div class="row">
                <div class="col-lg-8">
                    <div class="mb-4">
                        <span class="badge bg-white text-dark bg-opacity-25 backdrop-blur border border-white border-opacity-25 px-3 py-2 rounded-pill">
                            <i class="fa-solid fa-circle text-success small me-2"></i> Inscrições Abertas
                        </span>
                    </div>

                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="Logo do evento {{ $evento->titulo }}" class="mb-3 d-block" style="max-height: 90px; max-width: 100%; object-fit: contain; background: rgba(255,255,255,.92); padding: 8px 14px; border-radius: 14px;">
                    @endif
                    <h1 class="display-4 fw-bold mb-4" style="letter-spacing: -1px;">{{ $evento->titulo }}</h1>

                    <div class="d-flex flex-wrap gap-4 text-white opacity-90 mb-5">
                        <div class="d-flex align-items-center gap-2 bg-black bg-opacity-10 px-3 py-2 rounded-3">
                            <i class="fa-regular fa-calendar fs-5"></i>
                            <span class="fw-medium">{{ $evento->data_inicio->format('d/m/Y') }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 bg-black bg-opacity-10 px-3 py-2 rounded-3">
                            <i class="fa-solid fa-location-dot fs-5"></i>
                            <span class="fw-medium">{{ $evento->local }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <br>

    <div class="container" style="margin-top: -6rem;">
        <div class="row gy-5 gx-lg-5">

            <div class="col-lg-7">

                <div class="bg-white rounded-4 p-5 shadow-sm border border-light mb-5">
                    <h4 class="fw-bold text-dark mb-4 border-bottom pb-3">Sobre o Evento</h4>
                    <div class="text-muted" style="line-height: 1.8; font-size: 1.05rem;">
    {!! nl2br($evento->descricao ?? 'Participe deste evento acadêmico no IFPA Campus Óbidos. Uma oportunidade única de aprendizado, networking e certificação oficial.') !!}
</div>
                </div>

                <div class="bg-white rounded-4 p-5 shadow-sm border border-light">
                    <div class="d-flex justify-content-between align-items-center mb-5 border-bottom pb-3">
                        <h4 class="fw-bold text-dark m-0">Programação Oficial</h4>
                        <span class="badge bg-dark rounded-pill">{{ $evento->atividades->count() }} Atividades</span>
                    </div>

                    @if($evento->atividades->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="fa-regular fa-calendar-xmark fs-2 mb-3 opacity-50"></i>
                            <p>A programação será divulgada em breve.</p>
                        </div>
                    @else
                        <div class="timeline-section">
                            @foreach($evento->atividades as $ativ)
                            <div class="timeline-item">
                                <div class="timeline-marker"></div>

                                <div class="activity-card">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-dark fs-5">{{ $ativ->data_inicio->format('H:i') }}</span>
                                            <span class="text-muted small text-uppercase fw-bold">{{ $ativ->data_inicio->format('d/m') }}</span>
                                        </div>

                                        <div class="text-end">
                                            <span class="badge-soft mb-1 d-inline-block">{{ ucfirst($ativ->tipo) }}</span>
                                            <div class="text-muted small"><i class="fa-regular fa-clock me-1"></i> {{ $ativ->carga_horaria }}h</div>
                                        </div>
                                    </div>

                                    <h5 class="fw-bold text-dark mb-2">{{ $ativ->titulo }}</h5>

                                    <p class="text-muted small mb-0">Participe para garantir sua presença e certificação.</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>

            <div class="col-lg-5">
                <div class="booking-card">
                    <div class="booking-header">
                        <h4 class="fw-bold m-0"><i class="fa-solid fa-ticket me-2" style="color: var(--brand);"></i> Inscrição</h4>
                        <p class="text-muted small m-0 mt-1">{{ auth()->check() ? 'Confirme seus dados para garantir a vaga.' : 'Entre ou crie sua conta para garantir a vaga.' }}</p>
                    </div>

                    <div class="p-4">
                        @if ($errors->any())
                            <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3 small mb-4">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                                </ul>
                            </div>
                        @endif

                        @guest
                            <div id="inscricao" class="text-center py-2">
                                <div class="mb-3"><i class="fa-solid fa-user-lock fs-1" style="color: var(--brand);"></i></div>
                                <h5 class="fw-bold">Entre ou crie sua conta para se inscrever</h5>
                                <p class="text-muted small mb-4">A inscrição fica ligada à sua conta, o que garante a presença e o certificado no seu nome sem duplicidades.</p>
                                <a href="{{ route('evento.publico.acesso', [$evento->slug, 'entrar']) }}" class="btn w-100 py-3 fw-bold text-white shadow-lg mb-2" style="background-color: var(--brand); border-radius: 12px;">
                                    <i class="fa-solid fa-right-to-bracket me-1"></i> Já tenho conta — Entrar
                                </a>
                                <a href="{{ route('evento.publico.acesso', [$evento->slug, 'cadastrar']) }}" class="btn w-100 py-3 fw-bold shadow-sm" style="border: 2px solid var(--brand); color: var(--brand); border-radius: 12px;">
                                    <i class="fa-solid fa-user-plus me-1"></i> Criar minha conta
                                </a>
                                <p class="text-center mt-3 mb-0 text-muted small"><i class="fa-solid fa-shield-alt me-1"></i> Depois de entrar você volta aqui para concluir.</p>
                            </div>
                        @else
                        @if($inscrito)
                            <div id="inscricao" class="alert alert-success border-0 small mb-0"><i class="fa-solid fa-circle-check me-1"></i> Você já está inscrito(a) neste evento. Acompanhe em <a href="{{ route('inscrito.dashboard') }}" class="fw-bold">Minha Agenda</a>.</div>
                        @else
                        <form id="inscricao" action="{{ route('evento.publico.store', $evento->slug) }}" method="POST" x-data="{ tipo: '{{ old('tipo_vinculo', $participanteLogado?->tipo_vinculo ?? 'aluno') }}' }">
                            @csrf

                            <div class="alert alert-light border small py-2"><i class="fa-solid fa-user-check me-1 text-success"></i> Inscrevendo como <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}).</div>

                            <div class="mb-3">
                                <label class="fw-bold small text-secondary text-uppercase mb-1">CPF</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-regular fa-id-card"></i></span>
                                    @if($participanteLogado)
                                        <input type="text" class="form-control border-start-0 bg-light" value="{{ $participanteLogado->cpf }}" readonly>
                                    @else
                                        <input type="text" name="cpf" class="form-control border-start-0 cpf-mask" placeholder="000.000.000-00" value="{{ old('cpf') }}" required>
                                    @endif
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="fw-bold small text-secondary text-uppercase mb-1">Nome Completo</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-regular fa-user"></i></span>
                                    @if($participanteLogado)
                                        <input type="text" class="form-control border-start-0 bg-light" value="{{ $participanteLogado->nome_completo }}" readonly>
                                    @else
                                        <input type="text" name="nome_completo" class="form-control border-start-0" placeholder="Seu nome oficial" value="{{ old('nome_completo', auth()->user()->name) }}" required>
                                    @endif
                                </div>
                                @if($participanteLogado) <div class="form-text">CPF e nome vêm do seu cadastro. Para corrigir, use <a href="{{ route('profile.edit') }}">Minha Conta</a>.</div> @endif
                            </div>

                            <div class="mb-4">
                                <label class="fw-bold small text-secondary text-uppercase mb-1">Vínculo</label>
                                <select name="tipo_vinculo" x-model="tipo" class="form-select">
                                    <option value="aluno">Aluno (Discente)</option>
                                    <option value="servidor">Servidor (Técnico/Docente)</option>
                                    <option value="externo">Comunidade Externa</option>
                                </select>
                            </div>

                            <div x-show="['aluno', 'servidor'].includes(tipo)" x-transition class="bg-light p-3 rounded-3 mb-4 border border-dashed">
                                <div class="mb-3">
                                    <label class="fw-bold small text-dark mb-1" x-text="tipo === 'aluno' ? 'Matrícula Acadêmica' : 'Matrícula SIAPE'"></label>
                                    <input type="text" name="matricula" class="form-control bg-white" value="{{ old('matricula', $participanteLogado?->matricula) }}">
                                </div>

                                <div x-show="tipo === 'aluno'">
                                    <label class="fw-bold small text-dark mb-1">Turma / Curso</label>
                                    <select name="turma_id" class="form-select bg-white">
                                        <option value="">Selecione sua turma...</option>
                                        @foreach($turmas as $turma)
                                            <option value="{{ $turma->id }}" {{ (string) old('turma_id', $participanteLogado?->turma_id) === (string) $turma->id ? 'selected' : '' }}>
                                                {{ $turma->nome_completo }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="btn w-100 py-3 fw-bold text-white shadow-lg text-uppercase"
                                    style="background-color: var(--brand); border-radius: 12px; transition: transform 0.2s;">
                                Confirmar Inscrição
                            </button>

                            <p class="text-center mt-3 mb-0 text-muted small">
                                <i class="fa-solid fa-shield-alt me-1"></i> Seus dados estão seguros.
                            </p>
                        </form>
                        @endif
                        @endguest
                    </div>
                </div>
            </div>

        </div>
    </div>

    <footer class="text-center py-5 mt-5 border-top">
        <div class="container">
            <p class="text-muted small mb-0">&copy; {{ date('Y') }} IFPA Campus Óbidos - Sistema CertificaIF · <a href="{{ route('privacidade') }}" class="text-muted">Política de Privacidade</a></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function(){
            $('.cpf-mask').mask('000.000.000-00');
        });
    </script>
</body>
</html>
