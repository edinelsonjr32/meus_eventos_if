{{-- Telas de autenticação: mesmo menu, tipografia e rodapé das páginas públicas, com painel de marca ao lado do formulário --}}
<x-publico-layout titulo="Acesso à plataforma" ativo="" :sem-moldura="true">
    <x-slot:estilos>
        <style>
            body { background: radial-gradient(900px 380px at 90% -5%, rgba(16,185,129,.10), transparent 70%), var(--bg); }
            .auth { min-height: 100vh; display: flex; align-items: center; padding: 2rem 0; }
            .auth > .container { width: 100%; }
            .auth-shell { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 6fr); background: #fff; border: 1px solid var(--line); border-radius: 28px; overflow: hidden; box-shadow: var(--sh-1); min-height: 620px; }
            .auth-lado { position: relative; color: #d1fae5; padding: 2.75rem 2.5rem; display: flex; flex-direction: column; justify-content: space-between; background: radial-gradient(700px 360px at 90% 0%, rgba(52,211,153,.28), transparent 60%), linear-gradient(160deg, #053b2d 0%, #065f46 60%, #047857 100%); overflow: hidden; }
            .auth-lado::before { content: ""; position: absolute; inset: 0; background-image: radial-gradient(rgba(255,255,255,.1) 1px, transparent 1px); background-size: 28px 28px; opacity: .5; }
            .auth-lado > * { position: relative; }
            .auth-lado h2 { color: #fff; font-weight: 800; font-size: 1.85rem; line-height: 1.2; margin: 1.25rem 0 .75rem; }
            .auth-lado h2 span { color: #6ee7b7; }
            .auth-lado .selo { background: rgba(255,255,255,.12); color: #d1fae5; border-color: rgba(255,255,255,.25); }
            .auth-itens { list-style: none; padding: 0; margin: 1.75rem 0 0; }
            .auth-itens li { display: flex; gap: .85rem; align-items: flex-start; margin-bottom: 1.1rem; font-size: .92rem; }
            .auth-itens i { width: 36px; height: 36px; border-radius: 11px; background: rgba(255,255,255,.12); color: #6ee7b7; display: grid; place-items: center; flex: 0 0 36px; font-size: .95rem; }
            .auth-itens strong { display: block; color: #fff; font-size: .95rem; }
            .auth-form { display: flex; align-items: center; justify-content: center; padding: 2.5rem 1.5rem; }
            .auth-card { width: 100%; max-width: 420px; }
            .auth-voltar { border-top: 1px solid var(--line); margin-top: 1.75rem; padding-top: 1.1rem; text-align: center; }
            .auth-voltar a { color: var(--muted); text-decoration: none; font-size: .85rem; font-weight: 600; } .auth-voltar a:hover { color: #047857; }
            .auth .input-group-text { border-color: #d5dce5; }
            @media (max-width: 991.98px) { .auth-shell { grid-template-columns: 1fr; min-height: 0; border-radius: 22px; } .auth-lado { display: none; } .auth-form { padding: 2rem 1.25rem; } .auth { padding: 1.25rem 0; } }
        </style>
    </x-slot:estilos>

    <section class="auth">
        <div class="container">
            <div class="auth-shell">
                <aside class="auth-lado" aria-hidden="true">
                    <div>
                        <span class="selo"><i class="fa-solid fa-building-columns"></i> IFPA · Campus Óbidos</span>
                        <h2>Sua participação em eventos, <span>do cadastro ao certificado</span></h2>
                        <ul class="auth-itens">
                            <li><i class="fa-solid fa-calendar-check"></i><span><strong>Inscrições e agenda</strong>Monte sua programação e acompanhe cada atividade.</span></li>
                            <li><i class="fa-solid fa-qrcode"></i><span><strong>Presença pelo celular</strong>Registre presença lendo o QR Code da atividade.</span></li>
                            <li><i class="fa-solid fa-file-lines"></i><span><strong>Trabalhos acadêmicos</strong>Envie, acompanhe a avaliação e veja nos Trabalhos Publicados.</span></li>
                            <li><i class="fa-solid fa-award"></i><span><strong>Certificados verificáveis</strong>Baixe em PDF, com código público de autenticidade.</span></li>
                        </ul>
                    </div>
                    <div class="small" style="color:#a7f3d0;"><i class="fa-solid fa-shield-halved me-1"></i> Seus dados são tratados conforme a LGPD.</div>
                </aside>

                <div class="auth-form">
                    <div class="auth-card">
                        <a href="{{ route('home') }}" class="pub-marca justify-content-center mb-4"><span class="pub-marca-icone"><i class="fa-solid fa-graduation-cap"></i></span><span>MeusEventos<em>IF</em></span></a>
                        {{ $slot }}

                        <div class="auth-voltar">
                            <a href="{{ route('home') }}"><i class="fa-solid fa-arrow-left me-1"></i> Voltar ao início</a>
                            <span class="mx-2 text-muted">·</span>
                            <a href="{{ route('privacidade') }}">Política de privacidade</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-publico-layout>
