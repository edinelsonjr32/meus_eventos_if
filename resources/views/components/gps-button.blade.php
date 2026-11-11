{{--
  Painel de localização + botão de confirmação da presença. Usa o estado do Alpine de attendanceForm() (registrar-frequencia).
  Estados: pedindo | ok | negado | indisponivel | tempo | inseguro | sem_suporte | webview
  Cada recarga da página pede a localização de novo; o envio só acontece quando a pessoa confirma.
--}}
@props(['exigeGps' => true])
<div class="mt-4">
    @if($exigeGps)
        {{-- Pedindo / obtida --}}
        <div x-show="gpsEstado === 'pedindo'" x-cloak class="gps-painel gps-info" role="status">
            <i class="fa-solid fa-satellite-dish fa-bounce"></i>
            <div><strong>Permita o acesso à localização</strong><span>Quando o navegador perguntar, toque em <b>Permitir</b>. Usamos a localização só para conferir que você está no local do evento; ela não é guardada.</span></div>
        </div>

        <div x-show="gpsEstado === 'ok'" x-cloak class="gps-painel gps-ok" role="status">
            <i class="fa-solid fa-circle-check"></i>
            <div>
                <strong>Localização obtida</strong>
                <span x-show="precisao">Precisão de cerca de <b x-text="precisao"></b> m.</span>
                <span x-show="precisao > 150">Sinal fraco: se a presença for recusada, vá para um local aberto e atualize.</span>
                <button type="button" class="gps-link" @click="solicitar()">Atualizar localização</button>
            </div>
        </div>

        {{-- Bloqueada pelo usuário: o navegador NÃO pergunta de novo, então explicamos como liberar --}}
        <div x-show="gpsEstado === 'negado'" x-cloak class="gps-painel gps-erro" role="alert">
            <i class="fa-solid fa-location-crosshairs"></i>
            <div>
                <strong>A localização está bloqueada</strong>
                <span>Para registrar presença precisamos conferir que você está no evento. O navegador não pergunta de novo depois de um bloqueio: libere nas configurações e volte aqui.</span>

                <div class="gps-passos">
                    <div x-show="plataforma === 'ios'">
                        <b>iPhone / iPad (Safari):</b>
                        <ol><li>Toque em <b>aA</b> na barra de endereço e em <b>Ajustes do Site</b>.</li><li>Em <b>Localização</b>, escolha <b>Permitir</b>.</li><li>Se não aparecer: <b>Ajustes › Privacidade e Segurança › Serviços de Localização › Safari</b> › "Ao usar o app".</li></ol>
                    </div>
                    <div x-show="plataforma === 'android'">
                        <b>Android (Chrome):</b>
                        <ol><li>Toque no <b>cadeado</b> (ou ⓘ) ao lado do endereço.</li><li>Abra <b>Permissões › Localização</b> e escolha <b>Permitir</b>.</li><li>Confira também se a <b>Localização</b> do aparelho está ligada no painel de notificações.</li></ol>
                    </div>
                    <div x-show="plataforma === 'desktop'">
                        <b>Computador:</b>
                        <ol><li>Clique no <b>cadeado</b> ao lado do endereço.</li><li>Em <b>Configurações do site</b> (ou Permissões), mude <b>Localização</b> para <b>Permitir</b>.</li><li>Recarregue a página.</li></ol>
                    </div>
                </div>

                <div class="d-grid gap-2 mt-3">
                    <button type="button" class="btn btn-dark fw-bold" @click="solicitar()"><i class="fa-solid fa-rotate-right me-1"></i> Já liberei, tentar de novo</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="location.reload()">Recarregar a página</button>
                </div>
            </div>
        </div>

        <div x-show="gpsEstado === 'indisponivel' || gpsEstado === 'tempo'" x-cloak class="gps-painel gps-aviso" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong x-text="gpsEstado === 'tempo' ? 'Demorou para achar sua localização' : 'Não foi possível obter a localização'"></strong>
                <span>Ligue a <b>Localização</b> (GPS) do aparelho, desative o modo avião e, se estiver em ambiente fechado, chegue perto de uma janela ou área aberta.</span>
                <button type="button" class="btn btn-dark btn-sm fw-bold w-100 mt-2" @click="solicitar()"><i class="fa-solid fa-rotate-right me-1"></i> Tentar de novo</button>
            </div>
        </div>

        <div x-show="gpsEstado === 'inseguro'" x-cloak class="gps-painel gps-erro" role="alert">
            <i class="fa-solid fa-lock-open"></i>
            <div><strong>Conexão não segura</strong><span>A localização só funciona em páginas com endereço <b>https://</b>. Abra o link do QR Code novamente pelo endereço seguro.</span></div>
        </div>

        <div x-show="gpsEstado === 'sem_suporte'" x-cloak class="gps-painel gps-erro" role="alert">
            <i class="fa-solid fa-ban"></i>
            <div><strong>Este navegador não oferece localização</strong><span>Abra o link no <b>Chrome</b> ou no <b>Safari</b> do seu aparelho.</span></div>
        </div>

        <div x-show="webview" x-cloak class="gps-painel gps-aviso" role="note">
            <i class="fa-solid fa-mobile-screen"></i>
            <div><strong>Você parece estar num navegador dentro de outro aplicativo</strong><span>Esses navegadores costumam bloquear a localização. Se der erro, toque no menu (⋮) e escolha <b>Abrir no Chrome/Safari</b>.</span></div>
        </div>
    @endif

    {{-- Botão principal: pede a localização, e só depois confirma --}}
    <button type="button" @click="confirmar()" :disabled="isLoading || gpsEstado === 'pedindo'"
            {{ $attributes->merge(['class' => 'btn btn-success-custom text-white w-100 py-3 fw-bold d-flex align-items-center justify-content-center']) }}>
        <span x-show="isLoading" x-cloak><i class="fa-solid fa-spinner fa-spin me-2"></i> <span x-text="gpsEstado === 'pedindo' ? 'Aguardando permissão…' : 'Enviando…'"></span></span>
        <span x-show="!isLoading">
            @if($exigeGps)
                <template x-if="gpsEstado === 'ok'"><span><i class="fa-solid fa-circle-check me-2"></i> Confirmar presença</span></template>
                <template x-if="gpsEstado !== 'ok'"><span><i class="fa-solid fa-location-dot me-2"></i> Permitir localização e confirmar</span></template>
            @else
                <span><i class="fa-solid fa-circle-check me-2"></i> Confirmar presença</span>
            @endif
        </span>
    </button>

    @if($exigeGps)
        <div class="text-center mt-2"><small class="text-muted" style="font-size: .75rem;"><i class="fa-solid fa-lock me-1"></i> Validação por localização. Seu GPS não é armazenado.</small></div>
    @endif

    <style>
        .gps-painel { display: flex; gap: .8rem; align-items: flex-start; border-radius: 14px; padding: .9rem 1rem; margin-bottom: 1rem; font-size: .85rem; line-height: 1.4; }
        .gps-painel > i { font-size: 1.25rem; margin-top: .1rem; flex: 0 0 auto; }
        .gps-painel strong { display: block; margin-bottom: .15rem; font-size: .92rem; }
        .gps-painel span { display: block; }
        .gps-info { background: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe; }
        .gps-ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .gps-aviso { background: #fffbeb; color: #78350f; border: 1px solid #fde68a; }
        .gps-erro { background: #fef2f2; color: #7f1d1d; border: 1px solid #fecaca; }
        .gps-passos { background: rgba(255,255,255,.7); border-radius: 10px; padding: .6rem .8rem; margin-top: .6rem; color: #1f2937; }
        .gps-passos ol { margin: .3rem 0 0; padding-left: 1.15rem; } .gps-passos li { margin-bottom: .2rem; }
        .gps-link { background: none; border: 0; padding: 0; margin-top: .3rem; color: #047857; font-weight: 700; text-decoration: underline; font-size: .82rem; }
    </style>
</div>
