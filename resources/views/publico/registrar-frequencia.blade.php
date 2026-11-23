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

        /* Modal de feedback (erros e alertas) */
        .fb-fundo { position: fixed; inset: 0; z-index: 3000; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(15, 23, 42, .6); backdrop-filter: blur(2px); }
        .fb-modal { width: 100%; max-width: 420px; max-height: 92vh; overflow-y: auto; background: #fff; border-radius: 20px; padding: 1.5rem 1.25rem 1.25rem; text-align: center; box-shadow: 0 30px 60px -20px rgba(15, 23, 42, .65); animation: fb-sobe .25s ease-out; }
        @keyframes fb-sobe { from { opacity: 0; transform: translateY(14px) scale(.98); } to { opacity: 1; transform: none; } }
        .fb-ico { width: 68px; height: 68px; border-radius: 50%; margin: 0 auto .9rem; display: flex; align-items: center; justify-content: center; font-size: 1.9rem; }
        .fb-ico.erro { background: #fee2e2; color: #dc2626; } .fb-ico.aviso { background: #fef3c7; color: #d97706; } .fb-ico.info { background: #dbeafe; color: #2563eb; } .fb-ico.ok { background: #dcfce7; color: #16a34a; }
        .fb-titulo { font-weight: 800; font-size: 1.2rem; color: #0f172a; margin-bottom: .35rem; }
        .fb-texto { color: #475569; font-size: .92rem; line-height: 1.45; }
        .fb-dist { margin: .9rem 0; padding: .8rem; border-radius: 14px; background: #fef2f2; border: 1px solid #fecaca; }
        .fb-dist b { display: block; font-size: 1.9rem; line-height: 1.1; color: #b91c1c; }
        .fb-dist span { font-size: .8rem; color: #7f1d1d; }
        .fb-dicas { text-align: left; margin: .9rem 0 0; padding: .75rem .9rem .75rem 1.9rem; background: #f8fafc; border-radius: 12px; font-size: .84rem; color: #334155; }
        .fb-dicas li + li { margin-top: .35rem; }
        .fb-lista { text-align: left; margin: .8rem 0 0; padding: .7rem .9rem .7rem 1.9rem; background: #fff7ed; border-radius: 12px; font-size: .85rem; color: #7c2d12; }
        .fb-acoes { display: flex; flex-direction: column; align-items: center; gap: .55rem; margin-top: 1.1rem; }
        .fb-btn, .fb-btn-sec { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; width: 100%; max-width: 300px; padding: .65rem 1.2rem; border-radius: 10px; font-weight: 700; font-size: .92rem; text-decoration: none !important; cursor: pointer; transition: transform .15s, box-shadow .15s, background .15s; }
        .fb-btn { border: 0; color: #fff !important; background: linear-gradient(135deg, #10b981, #047857); box-shadow: 0 8px 16px -8px rgba(4, 120, 87, .9); }
        .fb-btn:hover { transform: translateY(-1px); }
        .fb-btn-sec { color: #475569 !important; background: #fff; border: 2px solid #e2e8f0; }
        .fb-btn-sec:hover { background: #f8fafc; }
        .fb-btn:focus-visible, .fb-btn-sec:focus-visible { outline: 3px solid #a7f3d0; outline-offset: 2px; }
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

            <div class="text-center mb-4">
                <h5 class="fw-bold text-dark">{{ $atividade->titulo }}</h5>
                @if($atividade->frequenciaAberta() || ($frequenciaExistente ?? null))
                <p class="text-secondary small">{{ auth()->check() ? 'Confirme seus dados para registrar a presença.' : 'Entre ou crie sua conta para registrar a presença.' }}</p>
                @endif
            </div>

            @if(!$atividade->frequenciaAberta() && !($frequenciaExistente ?? null))
                <div class="text-center py-2">
                    <div class="mb-3"><i class="fa-solid fa-lock fs-1 text-secondary"></i></div>
                    <h6 class="fw-bold text-secondary">Frequência encerrada</h6>
                    <p class="small text-muted mb-0">O registro de presença desta atividade já foi encerrado{{ $atividade->estadoDaFrequencia() === 'encerrada_auto' ? ' (' . $atividade->frequencia_fecha_em->format('d/m/Y \à\s H:i') . ')' : '' }}. Se você participou, procure a organização do evento para que a presença seja lançada.</p>
                </div>
            @else
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
                    <a href="{{ route('frequencia.confirmada', $atividade->token_frequencia) }}" class="btn btn-sm btn-outline-success mt-3">Ver comprovante</a>
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

                @include('partials.vinculo-participante')

                <x-gps-button :exige-gps="$exigeGps" />

            </form>
            @endguest
            @endif

            {{-- ===== Modal de feedback: erros e alertas ===== --}}
            <div x-show="modal.aberto" x-cloak class="fb-fundo" @click.self="fecharModal()" @keydown.escape.window="fecharModal()" role="alertdialog" aria-modal="true" aria-labelledby="fb-titulo" aria-describedby="fb-texto"
                 x-effect="document.body.style.overflow = modal.aberto ? 'hidden' : ''; if (modal.aberto) $nextTick(() => $refs.fbPrimario && $refs.fbPrimario.focus())">
                <div class="fb-modal">
                    <div class="fb-ico" :class="modal.cor"><i class="fa-solid" :class="modal.icone"></i></div>
                    <div class="fb-titulo" id="fb-titulo" x-text="modal.titulo"></div>
                    <div class="fb-texto" id="fb-texto" x-text="modal.mensagem"></div>

                    {{-- Longe do local --}}
                    <template x-if="modal.tipo === 'distancia'">
                        <div>
                            <div class="fb-dist"><b x-text="distanciaTexto(modal.extra.distancia)"></b><span>de distância do local · limite permitido: <strong x-text="distanciaTexto(modal.extra.raio)"></strong></span></div>
                            <ol class="fb-dicas">
                                <li>Vá até o local da atividade<template x-if="modal.extra.local"><span>: <strong x-text="modal.extra.local"></strong></span></template>.</li>
                                <li>Já está no local? Ligue o <strong>GPS</strong> do aparelho e, se possível, saia para uma área aberta ou perto de uma janela.</li>
                                <li>Toque em <strong>Atualizar localização</strong> e registre sua presença de novo.</li>
                            </ol>
                        </div>
                    </template>

                    {{-- Problemas com a localização --}}
                    <template x-if="modal.tipo === 'gps'">
                        <ol class="fb-dicas">
                            <li x-show="modal.extra.estado === 'negado'">A localização está <strong>bloqueada</strong>: libere nas configurações do navegador (as instruções estão na tela) e volte.</li>
                            <li x-show="modal.extra.estado === 'inseguro'">Abra o link com <strong>https://</strong> (conexão segura) para o navegador permitir a localização.</li>
                            <li x-show="modal.extra.estado === 'sem_suporte'">Este navegador não consegue informar a localização. Abra o link no <strong>Chrome</strong> ou no <strong>Safari</strong>.</li>
                            <li x-show="['tempo','indisponivel'].includes(modal.extra.estado)">Ligue a <strong>Localização (GPS)</strong>, desative o modo avião e chegue perto de uma janela ou área aberta.</li>
                        </ol>
                    </template>

                    {{-- Dados do formulário --}}
                    <template x-if="modal.tipo === 'validacao' && modal.itens.length">
                        <ul class="fb-lista"><template x-for="it in modal.itens" :key="it"><li x-text="it"></li></template></ul>
                    </template>

                    <div class="fb-acoes">
                        <template x-if="modal.tipo === 'distancia'">
                            <button type="button" class="fb-btn" x-ref="fbPrimario" @click="fecharModal(); solicitar(false, true)"><i class="fa-solid fa-location-crosshairs"></i> Atualizar localização</button>
                        </template>
                        <template x-if="modal.tipo === 'gps' && !['inseguro','sem_suporte'].includes(modal.extra.estado)">
                            <button type="button" class="fb-btn" x-ref="fbPrimario" @click="fecharModal(); solicitar(false, true)"><i class="fa-solid fa-rotate-right"></i> Tentar de novo</button>
                        </template>
                        <template x-if="modal.tipo === 'rede'">
                            <button type="button" class="fb-btn" x-ref="fbPrimario" @click="fecharModal(); enviar()"><i class="fa-solid fa-rotate-right"></i> Tentar de novo</button>
                        </template>
                        <template x-if="modal.tipo === 'validacao'">
                            <button type="button" class="fb-btn" x-ref="fbPrimario" @click="fecharModal(); irParaCampo()"><i class="fa-solid fa-pen"></i> Corrigir dados</button>
                        </template>
                        <template x-if="modal.tipo === 'ja_registrada'">
                            <a class="fb-btn" x-ref="fbPrimario" href="{{ route('frequencia.confirmada', $atividade->token_frequencia) }}"><i class="fa-solid fa-receipt"></i> Ver comprovante</a>
                        </template>
                        <template x-if="['sessao', 'encerrada'].includes(modal.tipo)">
                            <button type="button" class="fb-btn" x-ref="fbPrimario" @click="window.location.reload()"><i class="fa-solid fa-rotate-right"></i> Recarregar a página</button>
                        </template>
                        <button type="button" class="fb-btn-sec" x-ref="fbFechar" @click="fecharModal()" x-text="['distancia','gps','rede','validacao'].includes(modal.tipo) ? 'Fechar' : 'Entendi'"></button>
                    </div>
                </div>
            </div>
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
                isLoading: false,
                modal: { aberto: false, tipo: '', titulo: '', mensagem: '', cor: 'erro', icone: 'fa-circle-exclamation', itens: [], extra: {} },
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
                solicitar(manterErro = false, avisar = false) {
                    if (!this.exigeGps) return;
                    if (!window.isSecureContext) { this.gpsEstado = 'inseguro'; if (avisar) this.avisoGps('inseguro'); return; }
                    if (!navigator.geolocation) { this.gpsEstado = 'sem_suporte'; if (avisar) this.avisoGps('sem_suporte'); return; }

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
                        if (avisar) this.avisoGps(this.gpsEstado);
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
                    if (!this.exigeGps) { this.enviar(); return; }
                    if (this.gpsEstado === 'ok' && this.lat && this.lng) { this.enviar(); return; }
                    this.solicitar(false, true);   // pedido pela pessoa: se falhar, explica em um modal
                },

                // ---- Modal de feedback ----
                abrirModal(tipo, dados = {}) {
                    const base = {
                        distancia: { cor: 'erro', icone: 'fa-location-dot', titulo: 'Você está longe do local' },
                        gps: { cor: 'aviso', icone: 'fa-satellite-dish', titulo: 'Não conseguimos sua localização' },
                        validacao: { cor: 'aviso', icone: 'fa-clipboard-list', titulo: 'Confira seus dados' },
                        ja_registrada: { cor: 'ok', icone: 'fa-circle-check', titulo: 'Presença já registrada' },
                        encerrada: { cor: 'aviso', icone: 'fa-lock', titulo: 'Frequência encerrada' },
                        sessao: { cor: 'info', icone: 'fa-user-clock', titulo: 'Sua sessão expirou' },
                        rede: { cor: 'aviso', icone: 'fa-wifi', titulo: 'Sem conexão' },
                        erro: { cor: 'erro', icone: 'fa-triangle-exclamation', titulo: 'Não foi possível registrar' },
                    }[tipo] || { cor: 'erro', icone: 'fa-triangle-exclamation', titulo: 'Não foi possível registrar' };
                    this.modal = { aberto: true, tipo, itens: [], extra: {}, mensagem: '', ...base, ...dados };
                },
                fecharModal() { this.modal.aberto = false; },
                avisoGps(estado) {
                    const msg = {
                        negado: 'O navegador bloqueou a localização. Sem ela não dá para confirmar que você está no evento.',
                        tempo: 'A busca pela sua localização demorou demais.',
                        indisponivel: 'O aparelho não conseguiu informar onde você está.',
                        inseguro: 'Esta página precisa de conexão segura para acessar a localização.',
                        sem_suporte: 'Este navegador não informa a localização.',
                    }[estado] || 'Não foi possível obter a localização.';
                    this.abrirModal('gps', { mensagem: msg, extra: { estado } });
                },
                distanciaTexto(m) { m = Number(m || 0); return m >= 1000 ? (m / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' km' : m.toLocaleString('pt-BR') + ' m'; },
                irParaCampo() {
                    const campo = document.querySelector('#formPresenca .is-invalid, #formPresenca [name=matricula]:required:invalid, #formPresenca select:required:invalid, #formPresenca input:required:invalid');
                    if (campo) { campo.scrollIntoView({ behavior: 'smooth', block: 'center' }); campo.focus({ preventScroll: true }); }
                },

                async enviar(retry = true) {
                    const form = document.getElementById('formPresenca');
                    this.isLoading = true;
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

                        this.isLoading = false;
                        const msg = data.message || 'Não foi possível registrar sua presença.';

                        if (res.status === 419) { this.abrirModal('sessao', { mensagem: 'Sua sessão expirou ou o navegador bloqueou os cookies. Recarregue a página (ou abra o link no Chrome/Safari) e tente de novo.' }); return; }
                        if (data.codigo === 'fora_do_raio') {
                            this.abrirModal('distancia', { mensagem: 'Para registrar presença você precisa estar no local do evento.', extra: { distancia: data.distancia, raio: data.raio, local: data.local } });
                            this.solicitar(true);   // renova a posição em segundo plano (pode estar velha/imprecisa)
                            return;
                        }
                        if (data.codigo === 'sem_localizacao' || (res.status === 422 && /localiza/i.test(msg) && !data.errors)) { this.avisoGps(this.gpsEstado === 'ok' ? 'indisponivel' : this.gpsEstado); return; }
                        if (data.codigo === 'ja_registrada') { this.abrirModal('ja_registrada', { mensagem: msg }); return; }
                        if (data.codigo === 'encerrada') { this.abrirModal('encerrada', { mensagem: msg }); return; }
                        if (data.errors) { this.abrirModal('validacao', { mensagem: 'Alguns campos precisam de atenção antes de registrar a presença:', itens: [...new Set(Object.values(data.errors).flat())] }); return; }
                        this.abrirModal('erro', { mensagem: msg });
                    } catch (e) {
                        this.isLoading = false;
                        this.abrirModal('rede', { mensagem: 'Verifique sua internet e tente novamente. Nada foi registrado ainda.' });
                    }
                },
            }
        }
    </script>
</body>
</html>
