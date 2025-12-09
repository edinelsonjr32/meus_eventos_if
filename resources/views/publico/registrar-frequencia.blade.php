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
                <p class="text-secondary small">Preencha seus dados para confirmar.</p>
            </div>

            <form id="formPresenca" action="{{ route('frequencia.store', $atividade->token_frequencia) }}" method="POST">
                @csrf
                <input type="hidden" name="user_lat" x-model="lat">
                <input type="hidden" name="user_lng" x-model="lng">

                <div class="mb-3">
                    <label class="fw-bold small text-secondary">CPF</label>
                    <input type="text" name="cpf" class="form-control cpf-mask" placeholder="000.000.000-00" value="{{ old('cpf') }}" required>
                </div>

                <div class="mb-3">
                    <label class="fw-bold small text-secondary">Nome Completo</label>
                    <input type="text" name="nome_completo" class="form-control" value="{{ old('nome_completo') }}" required>
                </div>

                <div class="mb-3">
                    <label class="fw-bold small text-secondary">E-mail</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>

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
                        <input type="text" name="matricula" class="form-control bg-white" value="{{ old('matricula') }}">
                    </div>

                    <div x-show="tipoVinculo === 'aluno'">
                        <label class="fw-bold small text-dark mt-2">Turma</label>
                        <select name="turma_id" class="form-select bg-white">
                            <option value="">Selecione...</option>
                            @foreach($turmas as $turma)
                                <option value="{{ $turma->id }}">{{ $turma->nome_completo }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <x-gps-button />

            </form>
        </div>
    </div>

    <script>
        $(document).ready(function(){ $('.cpf-mask').mask('000.000.000-00'); });

        function attendanceForm() {
            return {
                tipoVinculo: '{{ old('tipo_vinculo', 'aluno') }}',
                lat: '', lng: '', isLoading: false, errorMessage: '', errorTitle: '',

                submitForm() {
                    if (this.lat && this.lng) { document.getElementById('formPresenca').submit(); return; }
                    this.getLocation();
                },

                getLocation() {
                    this.isLoading = true; this.errorMessage = '';
                    if (!navigator.geolocation) { this.showError('Erro', 'Navegador incompatível.'); return; }

                    navigator.geolocation.getCurrentPosition(
                        (p) => {
                            this.lat = p.coords.latitude; this.lng = p.coords.longitude; this.isLoading = false;
                            setTimeout(() => { document.getElementById('formPresenca').submit(); }, 500);
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
                showError(t, m) { this.isLoading = false; this.errorTitle = t; this.errorMessage = m; }
            }
        }
    </script>
</body>
</html>
