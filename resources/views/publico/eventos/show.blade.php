@php
    $cor = $evento->configuracoes['cor_fundo'] ?? '#059669';
    $cfg = $evento->trabalhoConfig;
    $chamadaAtiva = $cfg && $cfg->ativo;
    $chamadaAberta = $cfg?->estaAberta();
    $bannerUrl = $evento->bannerPublicoUrl();
    $logoUrl = $evento->logoPublicaUrl();
    $emAndamento = $evento->data_inicio->isPast() && $evento->data_fim->isFuture();
    $encerrado = $evento->data_fim->isPast();
    $mesmoDia = $evento->data_inicio->isSameDay($evento->data_fim);
    $periodo = $mesmoDia ? $evento->data_inicio->format('d/m/Y') : $evento->data_inicio->format('d/m') . ' a ' . $evento->data_fim->format('d/m/Y');
    $primeiroDia = $cronogramaPorDia->keys()->first();
    $fmtHoras = fn ($h) => rtrim(rtrim(number_format($h, 1, ',', ''), '0'), ',') . 'h';
@endphp
<x-publico-layout :titulo="$evento->titulo" :descricao="strip_tags($evento->descricao ?? 'Programação, inscrição e informações do evento.')" :marca="$cor" :mascara="true">
    <x-slot:estilos>
        <style>
            .ev-hero { position: relative; color: #fff; padding: 3.2rem 0 5.2rem; background: linear-gradient(135deg, var(--brand-deep), var(--brand-dark) 55%, var(--brand)); overflow: hidden; }
            .ev-hero.com-banner { background: linear-gradient(135deg, rgba(0,0,0,.62), rgba(0,0,0,.34)), var(--banner) center / cover no-repeat; }
            .ev-hero::before { content: ""; position: absolute; inset: 0; background-image: radial-gradient(rgba(255,255,255,.1) 1px, transparent 1px); background-size: 28px 28px; opacity: .5; }
            .ev-hero > .container { position: relative; }
            .ev-hero h1 { color: #fff; font-weight: 800; font-size: clamp(1.9rem, 4.4vw, 3.1rem); line-height: 1.12; }
            .ev-trilha { font-size: .83rem; color: rgba(255,255,255,.75); } .ev-trilha a { color: rgba(255,255,255,.85); text-decoration: none; } .ev-trilha a:hover { color: #fff; }
            .ev-selo { display: inline-flex; align-items: center; gap: .45rem; font-size: .76rem; font-weight: 700; padding: .38rem .85rem; border-radius: 999px; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.3); }
            .ev-selo i { font-size: .5rem; color: #6ee7b7; } .ev-selo.fim i { color: #fca5a5; }
            .ev-logo { max-height: 84px; max-width: 100%; object-fit: contain; background: rgba(255,255,255,.95); padding: 8px 16px; border-radius: 16px; }
            .ev-fatos { display: flex; flex-wrap: wrap; gap: .6rem; }
            .ev-fato { display: flex; align-items: center; gap: .6rem; background: rgba(0,0,0,.18); border: 1px solid rgba(255,255,255,.14); padding: .6rem 1rem; border-radius: 14px; font-size: .92rem; font-weight: 600; }
            .ev-fato i { opacity: .85; }
            .btn-ev { background: #fff; color: var(--brand-dark); padding: .8rem 1.5rem; box-shadow: 0 14px 28px -12px rgba(0,0,0,.5); }
            .btn-ev:hover { background: var(--brand-light); color: var(--brand-deep); transform: translateY(-1px); }
            .btn-ev-out { border: 2px solid rgba(255,255,255,.6); color: #fff; padding: .8rem 1.5rem; } .btn-ev-out:hover { background: rgba(255,255,255,.14); color: #fff; }

            .ev-subnav { position: sticky; top: 61px; z-index: 900; background: rgba(255,255,255,.94); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
            .ev-subnav .container { display: flex; gap: .25rem; overflow-x: auto; scrollbar-width: none; }
            .ev-subnav a { padding: .85rem 1rem; font-weight: 700; font-size: .88rem; color: var(--muted); text-decoration: none; white-space: nowrap; border-bottom: 3px solid transparent; }
            .ev-subnav a:hover { color: var(--brand-dark); border-bottom-color: var(--brand); }

            .ev-bloco { padding: 1.75rem; margin-bottom: 1.5rem; scroll-margin-top: 130px; }
            .ev-bloco h2 { font-size: 1.3rem; font-weight: 800; margin-bottom: 1.1rem; display: flex; align-items: center; gap: .6rem; }
            .ev-bloco h2 i { width: 36px; height: 36px; border-radius: 11px; background: var(--brand-light); color: var(--brand-dark); display: grid; place-items: center; font-size: .95rem; }
            .ev-desc { line-height: 1.8; font-size: 1.02rem; color: var(--ink-2); } .ev-desc img { max-width: 100%; height: auto; }
            .ev-num { text-align: center; padding: 1rem .5rem; background: var(--brand-light); border-radius: 14px; }
            .ev-num b { display: block; font-size: 1.5rem; font-weight: 800; color: var(--brand-deep); line-height: 1.1; } .ev-num span { font-size: .76rem; color: var(--muted); font-weight: 600; }

            .dias { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.4rem; }
            .dia-btn { border: 1px solid var(--line); background: #fff; border-radius: 14px; padding: .55rem 1rem; text-align: center; line-height: 1.15; cursor: pointer; transition: all .15s; min-width: 76px; }
            .dia-btn b { display: block; font-size: 1.15rem; color: var(--ink); } .dia-btn small { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 700; }
            .dia-btn.ativo { background: var(--brand); border-color: var(--brand); } .dia-btn.ativo b, .dia-btn.ativo small { color: #fff; }
            .atividade { display: flex; gap: 1.1rem; padding: 1.1rem 1.2rem; border: 1px solid var(--line); border-radius: 16px; margin-bottom: .8rem; background: #fff; transition: box-shadow .2s, border-color .2s; }
            .atividade:hover { border-color: var(--brand); box-shadow: var(--sh-1); }
            .atividade .hora { flex: 0 0 74px; text-align: center; border-right: 2px solid var(--brand-light); padding-right: 1rem; }
            .atividade .hora b { display: block; font-size: 1.2rem; color: var(--ink); line-height: 1.1; } .atividade .hora small { font-size: .74rem; color: var(--muted); font-weight: 600; }
            .atividade h3 { font-size: 1.05rem; font-weight: 700; margin: .35rem 0 .45rem; line-height: 1.3; }
            .atividade .meta { display: flex; flex-wrap: wrap; gap: .3rem 1rem; font-size: .82rem; color: var(--muted); } .atividade .meta i { color: var(--brand); margin-right: .3rem; }
            .vagas { font-size: .74rem; font-weight: 700; padding: .2rem .6rem; border-radius: 999px; background: #ecfdf5; color: #047857; } .vagas.esgotado { background: #fef2f2; color: #b91c1c; }

            .booking-card { position: sticky; top: 130px; overflow: hidden; }
            .booking-header { background: var(--brand-light); padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--line); }
            .info-linha { display: flex; gap: .8rem; padding: .7rem 0; border-bottom: 1px solid #eef1f5; font-size: .9rem; } .info-linha:last-child { border-bottom: 0; }
            .info-linha i { width: 22px; color: var(--brand); text-align: center; margin-top: .15rem; } .info-linha small { display: block; color: var(--muted); font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; }
            @media (max-width: 767.98px) { .atividade { flex-direction: column; gap: .6rem; } .atividade .hora { flex: none; text-align: left; border-right: 0; padding-right: 0; display: flex; align-items: baseline; gap: .5rem; } .booking-card { position: static; } .ev-subnav { top: 57px; } }
        </style>
    </x-slot:estilos>

    @if(session('error'))
        <div class="container mt-3"><div class="alert alert-warning shadow-sm mb-0">{{ session('error') }}</div></div>
    @endif

    {{-- ===== Cabeçalho do evento ===== --}}
    <header class="ev-hero {{ $bannerUrl ? 'com-banner' : '' }}" @if($bannerUrl) style="--banner: url('{{ $bannerUrl }}');" @endif>
        <div class="container">
            <div class="ev-trilha mb-3"><a href="{{ route('home') }}">Início</a> <i class="fa-solid fa-chevron-right mx-1" style="font-size:.6rem"></i> <a href="{{ route('home') }}#eventos">Eventos</a> <i class="fa-solid fa-chevron-right mx-1" style="font-size:.6rem"></i> <span>{{ \Illuminate\Support\Str::limit($evento->titulo, 40) }}</span></div>

            <div class="row">
                <div class="col-lg-9">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="ev-selo {{ $encerrado ? 'fim' : '' }}"><i class="fa-solid fa-circle"></i> {{ $encerrado ? 'Evento encerrado' : ($emAndamento ? 'Acontecendo agora' : 'Inscrições abertas') }}</span>
                        @if($chamadaAberta)<span class="ev-selo"><i class="fa-solid fa-file-arrow-up" style="font-size:.7rem"></i> Chamada de trabalhos aberta</span>@endif
                        @if($anaisPublicados)<span class="ev-selo"><i class="fa-solid fa-book" style="font-size:.7rem"></i> Anais publicados</span>@endif
                    </div>

                    @if($logoUrl)<img src="{{ $logoUrl }}" alt="Logo do evento {{ $evento->titulo }}" class="ev-logo mb-3 d-block">@endif
                    <h1 class="mb-4">{{ $evento->titulo }}</h1>

                    <div class="ev-fatos mb-4">
                        <div class="ev-fato"><i class="fa-regular fa-calendar"></i> {{ $periodo }}</div>
                        <div class="ev-fato"><i class="fa-solid fa-location-dot"></i> {{ $evento->local }}</div>
                        @if($atividades->isNotEmpty())<div class="ev-fato"><i class="fa-solid fa-list-check"></i> {{ $atividades->count() }} atividade(s)</div>@endif
                        @if($cargaTotal > 0)<div class="ev-fato"><i class="fa-regular fa-clock"></i> até {{ $fmtHoras($cargaTotal) }} de carga horária</div>@endif
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        @unless($encerrado)<a href="#inscricao" class="btn btn-ev">Quero me inscrever <i class="fa-solid fa-arrow-down ms-1"></i></a>@endunless
                        @if($chamadaAberta)
                            <a href="{{ route('trabalhos.create', $evento->slug) }}" class="btn btn-ev-out"><i class="fa-solid fa-file-arrow-up me-1"></i> Submeter Trabalho</a>
                        @endif
                        @if($anaisPublicados)
                            <a href="{{ route('anais.index', $evento->slug) }}" class="btn btn-ev-out"><i class="fa-solid fa-book me-1"></i> Anais</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </header>

    <nav class="ev-subnav" aria-label="Seções do evento">
        <div class="container">
            <a href="#sobre">Sobre</a>
            @if($atividades->isNotEmpty())<a href="#programacao">Programação</a>@endif
            @if($chamadaAtiva)<a href="#trabalhos">Trabalhos</a>@endif
            <a href="#local">Local</a>
            <a href="#inscricao">Inscrição</a>
        </div>
    </nav>

    <div class="container pt-4 pb-2">
        <div class="row gy-4 gx-lg-5">

            <div class="col-lg-7 col-xl-8">

                {{-- Sobre --}}
                <section class="cartao ev-bloco" id="sobre">
                    <h2><i class="fa-solid fa-circle-info"></i> Sobre o evento</h2>
                    <div class="ev-desc">{!! $evento->descricao ? nl2br($evento->descricao) : 'Participe deste evento acadêmico no IFPA Campus Óbidos: uma oportunidade de aprendizado, troca de experiências e certificação oficial.' !!}</div>

                    <div class="row g-3 mt-3">
                        <div class="col-6 col-md-3"><div class="ev-num"><b>{{ $atividades->count() }}</b><span>atividades</span></div></div>
                        <div class="col-6 col-md-3"><div class="ev-num"><b>{{ $cronogramaPorDia->count() }}</b><span>{{ $cronogramaPorDia->count() === 1 ? 'dia' : 'dias' }}</span></div></div>
                        <div class="col-6 col-md-3"><div class="ev-num"><b>{{ $cargaTotal > 0 ? $fmtHoras($cargaTotal) : '—' }}</b><span>carga horária</span></div></div>
                        <div class="col-6 col-md-3"><div class="ev-num"><b>{{ $totalInscritos }}</b><span>inscritos</span></div></div>
                    </div>
                </section>

                {{-- Programação --}}
                <section class="cartao ev-bloco" id="programacao" x-data="{ dia: '{{ $primeiroDia }}' }">
                    <h2><i class="fa-regular fa-calendar-days"></i> Programação oficial</h2>

                    @if($atividades->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="fa-regular fa-calendar-xmark fs-2 mb-3 opacity-50"></i>
                            <p class="mb-0">A programação será divulgada em breve.</p>
                        </div>
                    @else
                        @if($cronogramaPorDia->count() > 1)
                            <div class="dias" role="tablist">
                                @foreach($cronogramaPorDia as $data => $itens)
                                    @php $d = \Carbon\Carbon::parse($data); @endphp
                                    <button type="button" class="dia-btn" :class="{ 'ativo': dia === '{{ $data }}' }" @click="dia = '{{ $data }}'" role="tab">
                                        <small>{{ $d->translatedFormat('D') }}</small><b>{{ $d->format('d/m') }}</b>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @foreach($cronogramaPorDia as $data => $itens)
                            <div x-show="dia === '{{ $data }}'" x-cloak>
                                @if($cronogramaPorDia->count() > 1)
                                    <div class="small fw-bold text-muted text-uppercase mb-3" style="letter-spacing:.06em;">{{ \Carbon\Carbon::parse($data)->translatedFormat('l, d \\d\\e F') }}</div>
                                @endif
                                @foreach($itens as $ativ)
                                    @php
                                        $restantes = is_null($ativ->vagas) ? null : max(0, $ativ->vagas - $ativ->inscritos_count);
                                    @endphp
                                    <article class="atividade">
                                        <div class="hora">
                                            <b>{{ $ativ->data_inicio->format('H:i') }}</b>
                                            <small>{{ $ativ->data_fim ? 'até ' . $ativ->data_fim->format('H:i') : '' }}</small>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                <span class="selo">{{ ucfirst($ativ->tipo) }}</span>
                                                @if($restantes !== null)
                                                    <span class="vagas {{ $restantes === 0 ? 'esgotado' : '' }}">{{ $restantes === 0 ? 'Vagas esgotadas' : $restantes . ' vaga(s)' }}</span>
                                                @endif
                                            </div>
                                            <h3>{{ $ativ->titulo }}</h3>
                                            <div class="meta">
                                                @if($ativ->responsavel)<span><i class="fa-solid fa-user-tie"></i>{{ $ativ->responsavel }}</span>@endif
                                                @if($ativ->local)<span><i class="fa-solid fa-location-dot"></i>{{ $ativ->local }}</span>@endif
                                                @if($ativ->carga_horaria)<span><i class="fa-regular fa-clock"></i>{{ $fmtHoras($ativ->carga_horaria) }} de certificado</span>@endif
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endforeach
                    @endif
                </section>

                {{-- Chamada de trabalhos --}}
                @if($chamadaAtiva)
                    <section class="cartao ev-bloco" id="trabalhos">
                        <h2><i class="fa-solid fa-file-lines"></i> Chamada de trabalhos</h2>
                        @if($cfg->chamada_tema)<p class="fw-semibold mb-2" style="color: var(--brand-dark);">Tema: “{{ $cfg->chamada_tema }}”</p>@endif
                        <p class="text-muted">{{ $cfg->chamada_intro ? \Illuminate\Support\Str::limit(strip_tags($cfg->chamada_intro), 320) : 'Envie o seu trabalho acadêmico para avaliação da comissão. Os aprovados e apresentados são publicados nos Anais do evento.' }}</p>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6"><div class="info-linha"><i class="fa-regular fa-calendar-check"></i><div><small>Período de submissão</small>{{ $cfg->submissao_inicio ? $cfg->submissao_inicio->format('d/m/Y') : 'já aberto' }} @if($cfg->submissao_fim) a {{ $cfg->submissao_fim->format('d/m/Y') }} @endif</div></div></div>
                            <div class="col-sm-6"><div class="info-linha"><i class="fa-solid fa-users"></i><div><small>Autores por trabalho</small>até {{ $cfg->max_autores }}</div></div></div>
                        </div>

                        <div class="mb-3">
                            <div class="small fw-bold text-muted text-uppercase mb-2" style="letter-spacing:.05em;">Categorias aceitas</div>
                            <div class="d-flex flex-wrap gap-2">@foreach($cfg->tiposHabilitados() as $rotulo)<span class="selo">{{ $rotulo }}</span>@endforeach</div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            @if($chamadaAberta)
                                <a href="{{ route('trabalhos.create', $evento->slug) }}" class="btn btn-brand px-4"><i class="fa-solid fa-file-arrow-up me-1"></i> Enviar meu trabalho</a>
                            @elseif($cfg->submissao_inicio && $cfg->submissao_inicio->isFuture())
                                <span class="selo selo-azul"><i class="fa-regular fa-clock"></i> A chamada abre em {{ $cfg->submissao_inicio->format('d/m/Y') }}</span>
                            @else
                                <span class="selo selo-cinza"><i class="fa-regular fa-circle-xmark"></i> Chamada encerrada</span>
                            @endif
                            @if($cfg->template_url)<a href="{{ $cfg->template_url }}" target="_blank" rel="noopener" class="btn btn-outline-brand"><i class="fa-solid fa-download me-1"></i> Modelo (template)</a>@endif
                            @if($anaisPublicados)<a href="{{ route('anais.index', $evento->slug) }}" class="btn btn-outline-brand"><i class="fa-solid fa-book me-1"></i> Ver os Anais</a>@endif
                        </div>
                    </section>
                @endif

                {{-- Local --}}
                <section class="cartao ev-bloco" id="local">
                    <h2><i class="fa-solid fa-location-dot"></i> Local</h2>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <div class="fw-bold" style="font-size:1.05rem;">{{ $evento->local }}</div>
                            <div class="small text-muted">IFPA – Campus Óbidos · Óbidos, Pará</div>
                        </div>
                        @if($evento->latitude && $evento->longitude)
                            <a class="btn btn-outline-brand" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query={{ $evento->latitude }},{{ $evento->longitude }}"><i class="fa-solid fa-map-location-dot me-1"></i> Abrir no mapa</a>
                        @endif
                    </div>
                    @if($evento->latitude && $evento->longitude)
                        <div class="small text-muted mt-3"><i class="fa-solid fa-circle-info me-1"></i> A presença nas atividades pode exigir que você esteja no local do evento (conferência por GPS).</div>
                    @endif
                </section>
            </div>

            {{-- ===== Coluna lateral: inscrição ===== --}}
            <div class="col-lg-5 col-xl-4">
                <div class="cartao booking-card" id="inscricao">
                    <div class="booking-header">
                        <h4 class="fw-bold m-0" style="font-size:1.15rem;"><i class="fa-solid fa-ticket me-2" style="color: var(--brand);"></i> Inscrição</h4>
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

    <x-slot:scripts>
        <script>
            $(document).ready(function () { $('.cpf-mask').mask('000.000.000-00'); });
        </script>
    </x-slot:scripts>
</x-publico-layout>
