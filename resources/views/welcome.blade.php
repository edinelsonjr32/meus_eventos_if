@php
    $destaque = $proximosEventos->first();
    $fmt = fn ($n) => number_format($n, 0, ',', '.');
@endphp
<x-publico-layout titulo="Portal de Eventos Acadêmicos" descricao="Eventos acadêmicos do IFPA Campus Óbidos: programação, inscrição, presença por QR Code, submissão de trabalhos, Anais e certificados verificáveis." ativo="">
    <x-slot:estilos>
        <style>
            .hero { position: relative; color: #fff; background: radial-gradient(900px 420px at 85% 10%, rgba(52,211,153,.28), transparent 60%), linear-gradient(135deg, #053b2d 0%, #065f46 55%, #047857 100%); padding: 4.5rem 0 5rem; border-radius: 0 0 44px 44px; overflow: hidden; }
            .hero::before { content: ""; position: absolute; inset: 0; background-image: radial-gradient(rgba(255,255,255,.1) 1px, transparent 1px); background-size: 28px 28px; opacity: .55; }
            .hero > .container { position: relative; }
            .hero h1 { color: #fff; font-weight: 800; font-size: clamp(2rem, 4.6vw, 3.4rem); line-height: 1.1; }
            .hero h1 span { color: #6ee7b7; }
            .hero-lead { color: #d1fae5; font-size: 1.1rem; max-width: 560px; }
            .btn-hero { background: #fff; color: #047857; padding: .9rem 1.6rem; box-shadow: 0 14px 30px -12px rgba(0,0,0,.45); }
            .btn-hero:hover { background: #ecfdf5; color: #065f46; transform: translateY(-1px); }
            .btn-hero-out { border: 2px solid rgba(255,255,255,.55); color: #fff; padding: .9rem 1.6rem; }
            .btn-hero-out:hover { background: rgba(255,255,255,.12); color: #fff; }
            .hero-selos { display: flex; flex-wrap: wrap; gap: .5rem 1.1rem; font-size: .85rem; color: #a7f3d0; }
            .hero-selos i { color: #34d399; margin-right: .35rem; }
            .hero-card { background: rgba(255,255,255,.97); color: var(--ink-2); border-radius: 22px; padding: 1.5rem; box-shadow: 0 30px 60px -25px rgba(0,0,0,.55); }
            .hero-card .data { width: 62px; height: 68px; border-radius: 16px; background: #ecfdf5; color: #047857; text-align: center; line-height: 1.05; padding-top: .55rem; font-weight: 800; flex: 0 0 62px; }
            .hero-card .data b { display: block; font-size: 1.6rem; }
            .hero-card .data small { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; }
            .hero-passos li { display: flex; gap: .8rem; align-items: flex-start; margin-bottom: .85rem; font-size: .92rem; }
            .hero-passos .n { width: 28px; height: 28px; border-radius: 50%; background: #ecfdf5; color: #047857; display: grid; place-items: center; font-weight: 800; font-size: .8rem; flex: 0 0 28px; }

            .numeros { padding: 2.25rem 0 0; }
            .numero { padding: 1.25rem 1.4rem; display: flex; align-items: center; gap: 1rem; height: 100%; }
            .numero-ico { width: 48px; height: 48px; border-radius: 14px; background: #ecfdf5; color: #059669; display: grid; place-items: center; font-size: 1.25rem; flex: 0 0 48px; }
            .numero b { display: block; font-size: 1.7rem; line-height: 1; color: var(--ink); font-weight: 800; letter-spacing: -.02em; }
            .numero span { font-size: .8rem; color: var(--muted); font-weight: 600; }

            .evento-card { overflow: hidden; display: flex; flex-direction: column; height: 100%; transition: transform .25s, box-shadow .25s; }
            .evento-card:hover { transform: translateY(-6px); box-shadow: var(--sh-2); }
            .evento-capa { height: 150px; position: relative; display: flex; align-items: center; justify-content: center; background-size: cover; background-position: center; }
            .evento-capa::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, transparent 40%, rgba(0,0,0,.25)); }
            .evento-capa img { position: relative; z-index: 1; max-height: 64px; max-width: 60%; object-fit: contain; background: rgba(255,255,255,.92); padding: 6px 12px; border-radius: 12px; }
            .evento-data { position: absolute; top: 14px; right: 14px; z-index: 2; background: #fff; border-radius: 12px; padding: .35rem .75rem; text-align: center; font-weight: 800; line-height: 1.1; color: #065f46; box-shadow: 0 6px 14px rgba(0,0,0,.15); }
            .evento-data b { display: block; font-size: 1.2rem; } .evento-data small { font-size: .66rem; text-transform: uppercase; letter-spacing: .05em; }
            .evento-meta { display: flex; flex-wrap: wrap; gap: .35rem 1rem; font-size: .83rem; color: var(--muted); }
            .evento-meta i { color: #059669; margin-right: .3rem; }

            .anais-card { padding: 1.4rem; height: 100%; transition: transform .25s, box-shadow .25s; text-decoration: none; display: block; color: inherit; }
            .anais-card:hover { transform: translateY(-4px); box-shadow: var(--sh-2); color: inherit; }
            .anais-ico { width: 46px; height: 46px; border-radius: 13px; background: linear-gradient(135deg, #047857, #10b981); color: #fff; display: grid; place-items: center; font-size: 1.15rem; margin-bottom: 1rem; }

            .passo { padding: 1.6rem; height: 100%; position: relative; }
            .passo .n { position: absolute; top: 1rem; right: 1.2rem; font-size: 2.6rem; font-weight: 800; color: #e6f4ee; line-height: 1; }
            .passo-ico { width: 52px; height: 52px; border-radius: 15px; background: #ecfdf5; color: #059669; display: grid; place-items: center; font-size: 1.3rem; margin-bottom: 1rem; }
            .recurso { display: flex; gap: .9rem; padding: 1.1rem 0; }
            .recurso i { width: 42px; height: 42px; border-radius: 12px; background: #ecfdf5; color: #059669; display: grid; place-items: center; flex: 0 0 42px; }
            .recurso h6 { margin: 0 0 .15rem; font-weight: 700; color: var(--ink); } .recurso p { margin: 0; font-size: .88rem; color: var(--muted); }

            .validar-box { background: linear-gradient(135deg, #053b2d, #065f46); color: #d1fae5; border-radius: 28px; padding: 2.6rem; position: relative; overflow: hidden; }
            .validar-box::after { content: ""; position: absolute; right: -60px; top: -60px; width: 220px; height: 220px; border-radius: 50%; background: rgba(52,211,153,.18); }
            .validar-box h2 { color: #fff; font-weight: 800; }
            .validar-box .input-group { background: #fff; border-radius: 14px; overflow: hidden; position: relative; z-index: 1; }
            .validar-box .form-control { border: 0; padding: 1rem; } .validar-box .input-group-text { background: #fff; border: 0; color: #94a3b8; }
            .accordion-item { border: 1px solid var(--line) !important; border-radius: 16px !important; overflow: hidden; margin-bottom: .75rem; background: #fff; }
            .accordion-button { font-weight: 700; color: var(--ink); background: #fff; box-shadow: none !important; padding: 1.1rem 1.25rem; }
            .accordion-button:not(.collapsed) { color: #047857; background: #f3faf6; }
            .cta-final { background: linear-gradient(135deg, #059669, #047857); border-radius: 28px; color: #fff; padding: 3rem 2rem; }
            .cta-final h2 { color: #fff; font-weight: 800; }
        </style>
    </x-slot:estilos>

    {{-- ===== Hero ===== --}}
    <section class="hero">
        <div class="container">
            <div class="row align-items-center gy-5 gx-lg-5">
                <div class="col-lg-7">
                    <span class="selo mb-4" style="background: rgba(255,255,255,.12); color: #d1fae5; border-color: rgba(255,255,255,.25);"><i class="fa-solid fa-building-columns"></i> IFPA · Campus Óbidos</span>
                    <h1 class="mb-3">Eventos acadêmicos do <span>cadastro ao certificado</span></h1>
                    <p class="hero-lead mb-4">Consulte a programação, inscreva-se, registre presença pelo celular, envie trabalhos para a chamada do evento e baixe certificados com código de autenticidade.</p>
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <a href="#eventos" class="btn btn-hero">Ver próximos eventos <i class="fa-solid fa-arrow-down ms-2"></i></a>
                        <a href="#validar" class="btn btn-hero-out">Validar certificado</a>
                    </div>
                    <div class="hero-selos">
                        <span><i class="fa-solid fa-qrcode"></i>Presença por QR Code</span>
                        <span><i class="fa-solid fa-file-lines"></i>Submissão de trabalhos</span>
                        <span><i class="fa-solid fa-book"></i>Anais públicos</span>
                        <span><i class="fa-solid fa-shield-halved"></i>Certificados verificáveis</span>
                    </div>
                </div>

                <div class="col-lg-5">
                    @if($destaque)
                        <div class="hero-card">
                            <div class="rotulo-secao mb-3"><i class="fa-solid fa-bolt me-1"></i> {{ $destaque->data_inicio->isFuture() ? 'Próximo evento' : 'Acontecendo agora' }}</div>
                            <div class="d-flex gap-3 mb-3">
                                <div class="data"><b>{{ $destaque->data_inicio->format('d') }}</b><small>{{ $destaque->data_inicio->translatedFormat('M') }}</small></div>
                                <div class="min-w-0">
                                    <h5 class="fw-bold mb-1" style="line-height:1.25;">{{ $destaque->titulo }}</h5>
                                    <div class="small text-muted"><i class="fa-solid fa-location-dot me-1 text-success"></i> {{ $destaque->local }}</div>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="selo selo-cinza"><i class="fa-regular fa-calendar"></i> {{ $destaque->atividades_count }} atividade(s)</span>
                                @if(optional($destaque->trabalhoConfig)->estaAberta())<span class="selo selo-ambar"><i class="fa-solid fa-file-arrow-up"></i> Submissão de trabalhos aberta</span>@endif
                            </div>
                            <a href="{{ route('evento.publico.show', $destaque->slug) }}" class="btn btn-brand w-100 py-2">Ver programação e inscrever-se <i class="fa-solid fa-arrow-right ms-1"></i></a>
                        </div>
                    @else
                        <div class="hero-card">
                            <div class="rotulo-secao mb-3"><i class="fa-solid fa-route me-1"></i> Como participar</div>
                            <ul class="hero-passos list-unstyled mb-0">
                                <li><span class="n">1</span><span><strong>Crie sua conta</strong><br><span class="text-muted">Com seu CPF, para reunir tudo em um só lugar.</span></span></li>
                                <li><span class="n">2</span><span><strong>Inscreva-se no evento</strong><br><span class="text-muted">E monte sua agenda de atividades.</span></span></li>
                                <li><span class="n">3</span><span><strong>Registre presença</strong><br><span class="text-muted">Lendo o QR Code da atividade.</span></span></li>
                                <li class="mb-0"><span class="n">4</span><span><strong>Baixe seu certificado</strong><br><span class="text-muted">Com código para qualquer pessoa verificar.</span></span></li>
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Números ===== --}}
    <section class="numeros">
        <div class="container">
            <div class="row g-3">
                @foreach([
                    ['fa-calendar-check', $numeros['eventos'], 'eventos na plataforma'],
                    ['fa-users', $numeros['participantes'], 'participantes inscritos'],
                    ['fa-list-check', $numeros['atividades'], 'atividades na programação'],
                    ['fa-award', $numeros['certificados'], 'certificados emitidos'],
                ] as [$ico, $valor, $rotulo])
                    <div class="col-6 col-lg-3">
                        <div class="cartao numero">
                            <span class="numero-ico"><i class="fa-solid {{ $ico }}"></i></span>
                            <div><b>{{ $fmt($valor) }}</b><span>{{ $rotulo }}</span></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== Próximos eventos ===== --}}
    <section class="secao" id="eventos">
        <div class="container">
            <div class="mb-4">
                <div class="rotulo-secao mb-1">Agenda</div>
                <h2 class="secao-titulo">Próximos eventos</h2>
                <p class="secao-sub mb-0">Escolha um evento para ver a programação completa, se inscrever e acompanhar a chamada de trabalhos.</p>
            </div>

            @if($proximosEventos->isEmpty())
                <div class="cartao p-4 p-md-5">
                    <div class="row align-items-center g-4">
                        <div class="col-md-7">
                            <span class="selo selo-cinza mb-3"><i class="fa-regular fa-calendar-xmark"></i> Nenhum evento agendado</span>
                            <h4 class="fw-bold">Ainda não há inscrições abertas</h4>
                            <p class="text-muted mb-4">Novos eventos do campus aparecem aqui assim que forem publicados. Enquanto isso, você pode consultar os Anais de edições anteriores, validar um certificado ou criar sua conta para já deixar tudo pronto.</p>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="#anais" class="btn btn-outline-brand">Ver Anais publicados</a>
                                @guest <a href="{{ route('register') }}" class="btn btn-brand px-4">Criar minha conta</a> @endguest
                            </div>
                        </div>
                        <div class="col-md-5 d-none d-md-block text-center"><i class="fa-regular fa-calendar-check" style="font-size: 6.5rem; color: #d1fae5;"></i></div>
                    </div>
                </div>
            @else
                <div class="row g-4">
                    @foreach($proximosEventos as $evento)
                        @php
                            $cor = $evento->configuracoes['cor_fundo'] ?? '#059669';
                            $mesmoDia = $evento->data_inicio->isSameDay($evento->data_fim);
                            $emAndamento = !$evento->data_inicio->isFuture();
                            $aberta = optional($evento->trabalhoConfig)->estaAberta();
                        @endphp
                        <div class="col-md-6 col-lg-4">
                            <article class="cartao evento-card">
                                <div class="evento-capa" style="background-color: {{ $cor }}; background-image: {{ $evento->bannerPublicoUrl() ? "url('" . $evento->bannerPublicoUrl() . "')" : 'linear-gradient(135deg, rgba(0,0,0,.05), rgba(0,0,0,.35))' }};">
                                    @if($evento->logoPublicaUrl())
                                        <img src="{{ $evento->logoPublicaUrl() }}" alt="Logo de {{ $evento->titulo }}">
                                    @elseif(!$evento->bannerPublicoUrl())
                                        <i class="fa-solid fa-calendar-check text-white opacity-50" style="font-size: 3rem; position: relative; z-index: 1;"></i>
                                    @endif
                                    <div class="evento-data"><b>{{ $evento->data_inicio->format('d') }}</b><small>{{ $evento->data_inicio->translatedFormat('M') }}</small></div>
                                </div>
                                <div class="p-4 d-flex flex-column flex-grow-1">
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <span class="selo {{ $emAndamento ? '' : 'selo-azul' }}"><i class="fa-solid fa-circle" style="font-size:.45rem"></i> {{ $emAndamento ? 'Acontecendo' : 'Em breve' }}</span>
                                        @if($aberta)<span class="selo selo-ambar"><i class="fa-solid fa-file-arrow-up"></i> Trabalhos</span>@endif
                                    </div>
                                    <h5 class="fw-bold mb-2" style="line-height:1.3;">{{ $evento->titulo }}</h5>
                                    <div class="evento-meta mb-4">
                                        <span><i class="fa-regular fa-calendar"></i>{{ $mesmoDia ? $evento->data_inicio->format('d/m/Y') : $evento->data_inicio->format('d/m') . ' a ' . $evento->data_fim->format('d/m/Y') }}</span>
                                        <span><i class="fa-solid fa-location-dot"></i>{{ $evento->local }}</span>
                                        <span><i class="fa-solid fa-list-check"></i>{{ $evento->atividades_count }} atividade(s)</span>
                                        <span><i class="fa-solid fa-user-check"></i>{{ $evento->inscricoes_count }} inscrito(s)</span>
                                    </div>
                                    <a href="{{ route('evento.publico.show', $evento->slug) }}" class="btn btn-brand w-100 mt-auto">Ver programação e inscrever-se <i class="fa-solid fa-arrow-right ms-1"></i></a>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ===== Anais ===== --}}
    <section class="secao pt-0" id="anais">
        <div class="container">
            <div class="mb-4">
                <div class="rotulo-secao mb-1">Produção acadêmica</div>
                <h2 class="secao-titulo">Anais dos eventos</h2>
                <p class="secao-sub mb-0">Trabalhos aprovados e apresentados, publicados com autorização dos autores, com busca e citação ABNT.</p>
            </div>

            @if($edicoesComAnais->isEmpty())
                <div class="cartao p-4 d-flex flex-wrap align-items-center gap-3">
                    <span class="anais-ico mb-0"><i class="fa-solid fa-book-open"></i></span>
                    <div class="flex-grow-1">
                        <strong class="d-block">Os Anais serão publicados após cada evento</strong>
                        <span class="text-muted small">Aqui você encontrará os trabalhos apresentados, com resumo, autores, PDF e referência para citação.</span>
                    </div>
                </div>
            @else
                <div class="row g-4">
                    @foreach($edicoesComAnais as $e)
                        <div class="col-md-6 col-lg-4">
                            <a href="{{ route('anais.index', $e->slug) }}" class="cartao anais-card">
                                <div class="anais-ico"><i class="fa-solid fa-book-open"></i></div>
                                <h5 class="fw-bold mb-1" style="line-height:1.3;">{{ $e->anais->titulo }}</h5>
                                <div class="small text-muted mb-3">{{ $e->titulo }} · {{ $e->data_inicio->format('Y') }}</div>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="selo"><i class="fa-solid fa-file-lines"></i> {{ $e->total_trabalhos }} trabalho(s)</span>
                                    @if($e->anais->dadosVisiveis() && $e->anais->pdfPronto())<span class="selo selo-azul"><i class="fa-solid fa-file-pdf"></i> PDF completo</span>@endif
                                </div>
                                <span class="fw-bold small" style="color:#047857;">Explorar os trabalhos <i class="fa-solid fa-arrow-right ms-1"></i></span>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ===== Como funciona ===== --}}
    <section class="secao" id="como-funciona" style="background:#fff; border-top:1px solid var(--line); border-bottom:1px solid var(--line);">
        <div class="container">
            <div class="text-center mx-auto mb-5" style="max-width:640px;">
                <div class="rotulo-secao mb-1">Passo a passo</div>
                <h2 class="secao-titulo">Como participar de um evento</h2>
                <p class="secao-sub mx-auto mb-0">Da inscrição ao certificado, tudo em um só lugar e vinculado ao seu CPF.</p>
            </div>
            <div class="row g-4">
                @foreach([
                    ['fa-user-plus', 'Crie sua conta', 'Cadastre-se com nome, CPF e e-mail. A conta reúne suas inscrições, trabalhos e certificados.'],
                    ['fa-ticket', 'Inscreva-se', 'Escolha o evento, confirme seu vínculo (aluno, servidor ou comunidade) e monte sua agenda.'],
                    ['fa-qrcode', 'Registre presença', 'Em cada atividade, leia o QR Code com o celular. A presença conta para o certificado.'],
                    ['fa-award', 'Receba o certificado', 'Baixe em PDF na sua área. Qualquer pessoa pode conferir a autenticidade pelo código.'],
                ] as $i => [$ico, $titulo, $texto])
                    <div class="col-sm-6 col-lg-3">
                        <div class="cartao passo">
                            <span class="n">{{ $i + 1 }}</span>
                            <div class="passo-ico"><i class="fa-solid {{ $ico }}"></i></div>
                            <h5 class="fw-bold mb-2">{{ $titulo }}</h5>
                            <p class="small text-muted mb-0">{{ $texto }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== Recursos ===== --}}
    <section class="secao">
        <div class="container">
            <div class="row gy-5 gx-lg-5 align-items-center">
                <div class="col-lg-5">
                    <div class="rotulo-secao mb-1">Plataforma</div>
                    <h2 class="secao-titulo">Tudo o que um evento acadêmico precisa</h2>
                    <p class="secao-sub mb-4">Para quem participa, para quem avalia e para quem organiza.</p>
                    <a href="{{ Route::has('register') ? route('register') : '#' }}" class="btn btn-brand px-4 py-2">Criar minha conta</a>
                </div>
                <div class="col-lg-7">
                    <div class="row">
                        @foreach([
                            ['fa-calendar-days', 'Programação e agenda', 'Atividades por dia, vagas e conflito de horário avisado na hora da inscrição.'],
                            ['fa-qrcode', 'Presença por QR Code', 'Registro pelo celular, com conferência de local quando o evento exige.'],
                            ['fa-file-pen', 'Submissão de trabalhos', 'Envio do PDF, acompanhamento do protocolo e avaliação por pares.'],
                            ['fa-book-open', 'Anais publicados', 'Trabalhos aprovados e apresentados, com citação ABNT e PDF compilado.'],
                            ['fa-award', 'Certificados verificáveis', 'Cada documento tem um código público de autenticidade.'],
                            ['fa-shield-halved', 'Privacidade (LGPD)', 'CPF e e-mail dos autores nunca são publicados nos Anais.'],
                        ] as [$ico, $titulo, $texto])
                            <div class="col-md-6"><div class="recurso"><i class="fa-solid {{ $ico }}"></i><div><h6>{{ $titulo }}</h6><p>{{ $texto }}</p></div></div></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Validar certificado ===== --}}
    <section class="secao pt-0" id="validar">
        <div class="container">
            <div class="validar-box">
                <div class="row align-items-center g-4 position-relative" style="z-index:1;">
                    <div class="col-lg-5">
                        <div class="rotulo-secao mb-2" style="color:#6ee7b7;">Autenticidade</div>
                        <h2 class="mb-2">Validar certificado</h2>
                        <p class="mb-0">Digite o código de validação que está no rodapé do documento (ou leia o QR Code) para confirmar que o certificado foi emitido pelo IFPA.</p>
                    </div>
                    <div class="col-lg-7">
                        <form onsubmit="event.preventDefault(); var v = document.getElementById('hashInput').value.trim(); if (v) window.location.href = '/certificado/validar/' + encodeURIComponent(v);">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text ps-4"><i class="fa-solid fa-barcode"></i></span>
                                <input type="text" id="hashInput" class="form-control" placeholder="Cole o código do certificado aqui…" required autocomplete="off">
                                <button class="btn btn-brand px-4" type="submit" style="border-radius:0;">Verificar</button>
                            </div>
                            <div class="small mt-2" style="color:#a7f3d0;"><i class="fa-solid fa-circle-info me-1"></i> Vale para certificados de participação, de trabalhos, de premiação e de equipe.</div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Perguntas frequentes ===== --}}
    <section class="secao pt-0">
        <div class="container">
            <div class="row gy-5 gx-lg-5">
                <div class="col-lg-4">
                    <div class="rotulo-secao mb-1">Dúvidas</div>
                    <h2 class="secao-titulo">Perguntas frequentes</h2>
                    <p class="secao-sub">Não achou o que procurava? Fale com a organização do evento em que você está inscrito.</p>
                </div>
                <div class="col-lg-8">
                    <div class="accordion" id="faq">
                        @foreach([
                            ['Preciso de conta para me inscrever?', 'Sim. A inscrição fica ligada à sua conta, o que garante que a presença e o certificado saiam no seu nome, sem duplicidade. Criar a conta leva um minuto.'],
                            ['Como registro presença nas atividades?', 'Abra a câmera do celular e leia o QR Code exibido na atividade. Você precisa estar logado e inscrito no evento. Alguns eventos conferem também a sua localização.'],
                            ['Onde baixo meu certificado?', 'Em "Minha Agenda", dentro da sua área. O certificado de cada atividade fica disponível depois que a presença é confirmada.'],
                            ['Como envio um trabalho acadêmico?', 'Quando o evento abre chamada de trabalhos, aparece o botão "Submeter trabalho" na página do evento. Você envia o PDF, cadastra os autores e acompanha o resultado em "Meus Trabalhos".'],
                            ['O que é publicado nos Anais?', 'Apenas trabalhos aprovados, apresentados e autorizados pelos autores: título, resumo, autores com instituição e o PDF. CPF e e-mail nunca são divulgados.'],
                            ['Como sei se um certificado é verdadeiro?', 'Use a área "Validar certificado" desta página com o código impresso no documento. Se for autêntico, aparecem os dados de quem o recebeu e do evento.'],
                        ] as $i => [$pergunta, $resposta])
                            <div class="accordion-item">
                                <h3 class="accordion-header"><button class="accordion-button {{ $i ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $i }}">{{ $pergunta }}</button></h3>
                                <div id="faq{{ $i }}" class="accordion-collapse collapse {{ $i ? '' : 'show' }}" data-bs-parent="#faq"><div class="accordion-body text-muted">{{ $resposta }}</div></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    @guest
    <section class="pb-2">
        <div class="container">
            <div class="cta-final text-center">
                <h2 class="mb-2">Pronto para participar?</h2>
                <p class="mb-4" style="color:#d1fae5;">Crie sua conta e acompanhe inscrições, presenças, trabalhos e certificados em um só lugar.</p>
                <a href="{{ route('register') }}" class="btn btn-hero me-2">Criar minha conta</a>
                <a href="{{ route('login') }}" class="btn btn-hero-out">Já tenho conta</a>
            </div>
        </div>
    </section>
    @endguest
</x-publico-layout>
