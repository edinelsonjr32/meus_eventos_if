<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presença Segura - IFPA</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0fdf4; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .mobile-card { background: white; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.08); width: 100%; max-width: 450px; overflow: hidden; }
        .header-bg { background: linear-gradient(135deg, #064e3b 0%, #059669 100%); padding: 2rem 1.5rem; color: white; text-align: center; }

        /* Estilo base do botão (usado pelo componente) */
        .btn-success-custom {
            background: #059669; border: none; font-size: 1.1rem;
            transition: all 0.2s; border-radius: 12px;
        }
        .btn-success-custom:hover { background: #047857; }
        .btn-success-custom:disabled { background: #9ca3af; transform: none; cursor: wait; opacity: 0.8; }
    </style>
    @include('partials.favicon')
</head>
<body class="p-3">

    <div class="mobile-card">
        <div class="header-bg">
            <h5 class="fw-bold mb-1">Registro de Presença</h5>
            <small class="opacity-75"><i class="fa-solid fa-location-dot me-1"></i> {{ $atividade->evento->local }}</small>
        </div>

        @php $exigeGps = filled($atividade->evento->latitude) && filled($atividade->evento->longitude); @endphp
        <div class="p-4" x-data="attendanceForm({ exigeGps: {{ $exigeGps ? 'true' : 'false' }} })">

            @if ($errors->any())
                <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3 small mb-4 shadow-sm">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                </div>
            @endif

            <div x-show="errorMessage" x-cloak class="alert alert-warning border-0 bg-warning bg-opacity-10 text-dark rounded-3 small mb-4" role="alert">
                <div class="d-flex gap-2">
                    <i class="fa-solid fa-triangle-exclamation mt-1"></i>
                    <div style="line-height: 1.3;">
                        <strong x-text="errorTitle" class="d-block mb-1"></strong>
                        <span x-text="errorMessage"></span>
                    </div>
                </div>
            </div>

            <div class="text-center mb-4">
                <h5 class="fw-bold text-dark">{{ $atividade->titulo }}</h5>
                <p class="text-secondary small">{{ auth()->check() ? 'Confirme seus dados para registrar a presença.' : 'Entre ou crie sua conta para registrar a presença.' }}</p>
            </div>

            @guest
                <div class="text-center">
                    <div class="mb-3"><i class="fa-solid fa-user-lock fs-1 text-success"></i></div>
                    <p class="small text-muted">A presença fica ligada à sua conta, garantindo o certificado no seu nome. Depois de entrar você volta aqui para registrar.</p>
                    <a href="{{ route('frequencia.acesso', [$atividade->token_frequencia, 'entrar']) }}" class="btn btn-success-custom w-100 mb-2 text-center text-decoration-none d-block"><i class="fa-solid fa-right-to-bracket me-1"></i> Já tenho conta — Entrar</a>
                    <a href="{{ route('frequencia.acesso', [$atividade->token_frequencia, 'cadastrar']) }}" class="btn btn-outline-success w-100 fw-bold py-2"><i class="fa-solid fa-user-plus me-1"></i> Criar minha conta</a>
                </div>
            @elseif($frequenciaExistente)
                <div class="text-center">
                    <div class="mb-3"><i class="fa-solid fa-circle-check fs-1 text-success"></i></div>
                    <h6 class="fw-bold text-success">Presença já registrada</h6>
                    <p class="small text-muted mb-0">Você já registrou presença nesta atividade em {{ $frequenciaExistente->data_registro?->format('d/m/Y \à\s H:i') }}. Não é necessário preencher novamente.</p>
                </div>
            @else
            <form id="formPresenca" action="{{ route('frequencia.store', $atividade->token_frequencia) }}" method="POST">
                @csrf
                <input type="hidden" name="user_lat" x-model="lat">
                <input type="hidden" name="user_lng" x-model="lng">

                <div class="alert alert-light border small py-2"><i class="fa-solid fa-user-check me-1 text-success"></i> Registrando como <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}).</div>

                @if($participanteLogado)
                    <div class="mb-3">
                        <label class="fw-bold small text-secondary">CPF</label>
                        <input type="text" class="form-control bg-light" value="{{ $participanteLogado->cpf }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold small text-secondary">Nome Completo</label>
                        <input type="text" class="form-control bg-light" value="{{ $participanteLogado->nome_completo }}" readonly>
                    </div>
                @else
                    <div class="mb-3">
                        <label class="fw-bold small text-secondary">CPF</label>
                        <input type="text" name="cpf" class="form-control cpf-mask" placeholder="000.000.000-00" value="{{ old('cpf') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold small text-secondary">Nome Completo</label>
                        <input type="text" name="nome_completo" class="form-control" value="{{ old('nome_completo', auth()->user()->name) }}" required>
                    </div>
                @endif

                <div class="mb-3">
                    <label class="fw-bold small text-secondary">Vínculo</label>
                    <select name="tipo_vinculo" x-model="tipoVinculo" class="form-select">
                        <option value="aluno">Aluno</option>
                        <option value="servidor">Servidor</option>
                        <option value="externo">Externo</option>
                    </select>
                </div>

                <div x-show="['aluno', 'servidor'].includes(tipoVinculo)" class="bg-light p-3 rounded-3 mb-4 border">
                    <div class="mb-2">
                        <label class="fw-bold small text-dark">Matrícula</label>
                        <input type="text" name="matricula" class="form-control bg-white" value="{{ old('matricula', $participanteLogado?->matricula) }}">
                    </div>

                    <div x-show="tipoVinculo === 'aluno'">
                        <label class="fw-bold small text-dark mt-2">Turma</label>
                        <select name="turma_id" class="form-select bg-white">
                            <option value="">Selecione...</option>
                            @foreach($turmas as $turma)
                                <option value="{{ $turma->id }}" @selected((string) old('turma_id', $participanteLogado?->turma_id) === (string) $turma->id)>{{ $turma->nome_completo }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <x-gps-button :exige-gps="$exigeGps" />

            </form>
            @endguest
        </div>
    </div>

    <script>
        $(document).ready(function(){ $('.cpf-mask').mask('000.000.000-00'); });

        function attendanceForm(cfg) {
            const ua = navigator.userAgent || '';
            return {
                exigeGps: !!(cfg && cfg.exigeGps),
                tipoVinculo: '{{ old('tipo_vinculo', $participanteLogado?->tipo_vinculo ?? 'aluno') }}',
                lat: '', lng: '', precisao: 0,
                gpsEstado: 'idle',          // idle | pedindo | ok | negado | indisponivel | tempo | inseguro | sem_suporte
                isLoading: false, errorMessage: '', errorTitle: '',
                plataforma: /iPhone|iPad|iPod/i.test(ua) ? 'ios' : (/Android/i.test(ua) ? 'android' : 'desktop'),
                webview: /FBAN|FBAV|Instagram|WhatsApp|Line\/|MicroMessenger|; wv\)|GSA\//i.test(ua),

                init() {
                    if (!this.exigeGps) return;

                    if (!window.isSecureContext) { this.gpsEstado = 'inseguro'; return; }
                    if (!navigator.geolocation) { this.gpsEstado = 'sem_suporte'; return; }

                    // Acompanha a permissão: se a pessoa liberar nas configurações, seguimos sozinhos
                    if (navigator.permissions && navigator.permissions.query) {
                        navigator.permissions.query({ name: 'geolocation' }).then((st) => {
                            st.onchange = () => {
                                if (st.state === 'granted' && this.gpsEstado !== 'ok') this.solicitar();
                                if (st.state === 'denied') this.gpsEstado = 'negado';
                            };
                            if (st.state === 'denied') this.gpsEstado = 'negado';
                        }).catch(() => {});
                    }

                    // Voltando das configurações do aparelho/navegador: tenta de novo
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible' && ['negado', 'indisponivel', 'tempo'].includes(this.gpsEstado)) this.solicitar();
                    });

                    // A cada carregamento da página, pede (ou confirma) a localização
                    this.solicitar();
                },

                // Pede a posição. Se o navegador já bloqueou, ele responde "negado" na hora: mostramos o passo a passo.
                solicitar(manterErro = false) {
                    if (!this.exigeGps) return;
                    if (!window.isSecureContext) { this.gpsEstado = 'inseguro'; return; }
                    if (!navigator.geolocation) { this.gpsEstado = 'sem_suporte'; return; }

                    if (!manterErro) this.errorMessage = '';
                    this.lat = ''; this.lng = ''; this.precisao = 0;
                    this.gpsEstado = 'pedindo';

                    const ok = (p) => {
                        this.lat = p.coords.latitude; this.lng = p.coords.longitude;
                        this.precisao = Math.round(p.coords.accuracy || 0);
                        this.gpsEstado = 'ok';
                    };
                    const falha = (e) => {
                        if (e.code === 1) this.gpsEstado = 'negado';
                        else if (e.code === 3) this.gpsEstado = 'tempo';
                        else this.gpsEstado = 'indisponivel';
                    };

                    // 1ª tentativa: alta precisão. Se demorar, 2ª com precisão normal (funciona melhor em ambiente fechado).
                    navigator.geolocation.getCurrentPosition(ok, (e) => {
                        if (e.code === 3 || e.code === 2) {
                            navigator.geolocation.getCurrentPosition(ok, falha, { enableHighAccuracy: false, timeout: 12000, maximumAge: 0 });
                        } else { falha(e); }
                    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
                },

                // Botão principal: sem localização, pede; com localização, confirma e envia
                confirmar() {
                    this.errorMessage = '';
                    if (!this.exigeGps) { this.enviar(); return; }
                    if (this.gpsEstado === 'ok' && this.lat && this.lng) { this.enviar(); return; }
                    this.solicitar();
                },

                async enviar(retry = true) {
                    const form = document.getElementById('formPresenca');
                    this.isLoading = true; this.errorMessage = '';
                    try {
                        // Coordenadas enviadas direto (os inputs ocultos do x-model atualizam de forma assíncrona)
                        const body = new FormData(form);
                        body.set('user_lat', this.lat);
                        body.set('user_lng', this.lng);

                        const res = await fetch(form.action, {
                            method: 'POST', body: body, credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });

                        // 419: sessão/token vencido -> renova o token e tenta de novo (1 vez)
                        if (res.status === 419 && retry) {
                            const r = await fetch('{{ route('frequencia.csrf', $atividade->token_frequencia) }}', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                            if (r.ok) {
                                form.querySelector('input[name=_token]').value = (await r.json()).token;
                                return this.enviar(false);
                            }
                        }

                        if (res.status === 401) { window.location.reload(); return; }   // sessão acabou: recarrega para pedir login
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.redirect) { window.location.href = data.redirect; return; }

                        let msg = data.message || 'Erro ao registrar presença.';
                        if (res.status === 419) msg = 'Sua sessão expirou ou o navegador bloqueou cookies. Abra o link no Chrome/Safari e tente novamente.';
                        if (data.errors) msg = Object.values(data.errors).flat().join(' ');
                        this.isLoading = false;
                        this.errorTitle = 'Não foi possível registrar';
                        this.errorMessage = msg;

                        // Fora do raio ou sem localização aceita: a posição pode estar velha/imprecisa; pede de novo
                        if (res.status === 403 || (res.status === 422 && /localiza/i.test(msg))) { this.solicitar(true); }
                    } catch (e) {
                        this.isLoading = false;
                        this.errorTitle = 'Falha de conexão';
                        this.errorMessage = 'Verifique sua internet e tente novamente.';
                    }
                },
            }
        }
    </script>
</body>
</html>
