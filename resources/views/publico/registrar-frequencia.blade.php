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
        .btn-success-custom:hover { background: #047857; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(5, 150, 105, 0.2); }
        .btn-success-custom:disabled { background: #9ca3af; transform: none; cursor: wait; opacity: 0.8; }
    </style>
</head>
<body class="p-3">

    <div class="mobile-card">
        <div class="header-bg">
            <h5 class="fw-bold mb-1">Registro de Presença</h5>
            <small class="opacity-75"><i class="fa-solid fa-location-dot me-1"></i> {{ $atividade->evento->local }}</small>
        </div>

        <div class="p-4" x-data="attendanceForm()">

            @if ($errors->any())
                <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3 small mb-4 shadow-sm">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                </div>
            @endif

            <div x-show="errorMessage" x-transition class="alert alert-warning border-0 bg-warning bg-opacity-10 text-dark rounded-3 small mb-4 shadow-sm" style="display: none;">
                <div class="d-flex gap-2">
                    <i class="fa-solid fa-triangle-exclamation mt-1"></i>
                    <div style="line-height: 1.2;">
                        <strong x-text="errorTitle" class="d-block mb-1"></strong>
                        <span x-text="errorMessage"></span>
                    </div>
                </div>
                <button type="button" @click="getLocation()" class="btn btn-sm btn-dark w-100 mt-2 fw-bold">Tentar Novamente</button>
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

                <x-gps-button />

            </form>
            @endguest
        </div>
    </div>

    <script>
        $(document).ready(function(){ $('.cpf-mask').mask('000.000.000-00'); });

        function attendanceForm() {
            return {
                tipoVinculo: '{{ old('tipo_vinculo', $participanteLogado?->tipo_vinculo ?? 'aluno') }}',
                lat: '', lng: '', isLoading: false, errorMessage: '', errorTitle: '',

                submitForm() {
                    if (this.lat && this.lng) { this.sendForm(); return; }
                    this.getLocation();
                },

                getLocation() {
                    this.isLoading = true; this.errorMessage = '';
                    if (!navigator.geolocation) { this.showError('Erro', 'Navegador incompatível.'); return; }

                    navigator.geolocation.getCurrentPosition(
                        (p) => {
                            this.lat = p.coords.latitude; this.lng = p.coords.longitude; this.isLoading = false;
                            this.sendForm();
                        },
                        (e) => {
                            this.isLoading = false;
                            let msg = "Erro desconhecido.";
                            if(e.code===1) msg = "Permissão de localização negada.";
                            else if(e.code===2) msg = "Sinal de GPS indisponível.";
                            else if(e.code===3) msg = "Tempo esgotado (Sinal fraco).";
                            this.showError("Não foi possível localizar", msg);
                        },
                        { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
                    );
                },
                async sendForm(retry = true) {
                    const form = document.getElementById('formPresenca');
                    this.isLoading = true; this.errorMessage = '';
                    try {
                        // Coordenadas enviadas direto (os inputs ocultos do x-model atualizam de forma assíncrona)
                        const body = new FormData(form);
                        body.set('user_lat', this.lat);
                        body.set('user_lng', this.lng);

                        const res = await fetch(form.action, {
                            method: 'POST',
                            body: body,
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });

                        // 419: sessão/token vencido -> renova o token e tenta de novo (1 vez)
                        if (res.status === 419 && retry) {
                            const r = await fetch('{{ route('frequencia.csrf', $atividade->token_frequencia) }}', {
                                credentials: 'same-origin', headers: { 'Accept': 'application/json' }
                            });
                            if (r.ok) {
                                form.querySelector('input[name=_token]').value = (await r.json()).token;
                                return this.sendForm(false);
                            }
                        }

                        if (res.status === 401) { window.location.reload(); return; }   // sessão acabou: recarrega para pedir login
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.redirect) { window.location.href = data.redirect; return; }

                        let msg = data.message || 'Erro ao registrar presença.';
                        if (res.status === 419) msg = 'Sua sessão expirou ou o navegador bloqueou cookies. Abra o link no Chrome/Safari e tente novamente.';
                        if (data.errors) msg = Object.values(data.errors).flat().join(' ');
                        this.lat = ''; this.lng = '';
                        this.showError('Não foi possível registrar', msg);
                    } catch (e) {
                        this.showError('Falha de conexão', 'Verifique sua internet e tente novamente.');
                    }
                },
                showError(t, m) { this.isLoading = false; this.errorTitle = t; this.errorMessage = m; }
            }
        }
    </script>
</body>
</html>
