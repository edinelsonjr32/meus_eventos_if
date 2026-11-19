<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-11">

            @php $filtrosExport = request()->only(['status', 'tipo', 'area', 'q']); @endphp
            <style>
                .gt-nav { display: flex; flex-wrap: wrap; gap: .25rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 1rem; }
                .gt-nav a { display: inline-flex; align-items: center; gap: .4rem; padding: .55rem .85rem; font-size: .85rem; font-weight: 600; color: #475569; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; }
                .gt-nav a:hover { color: #047857; border-bottom-color: #a7f3d0; }
                .gt-nav a i { color: #94a3b8; } .gt-nav a:hover i { color: #059669; }
            </style>
            <div class="d-flex align-items-start justify-content-between mb-3 flex-wrap gap-3">
                <div>
                    <h3 class="fw-bold m-0">Trabalhos submetidos</h3>
                    <small class="text-secondary">{{ $evento->titulo }} · <strong>{{ $trabalhos->total() }}</strong> trabalho(s){{ request()->hasAny(['q', 'status', 'tipo', 'area', 'pendencia']) ? ' com os filtros aplicados' : '' }}</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-download me-1"></i> Exportar</button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('gestao.trabalhos.exportar', array_merge(['evento' => $evento->id, 'formato' => 'xlsx'], $filtrosExport)) }}"><i class="fa-solid fa-file-excel text-success me-2"></i> Planilha Excel (.xlsx)</a></li>
                            <li><a class="dropdown-item" href="{{ route('gestao.trabalhos.exportar', array_merge(['evento' => $evento->id, 'formato' => 'csv'], $filtrosExport)) }}"><i class="fa-solid fa-file-csv text-secondary me-2"></i> Arquivo CSV</a></li>
                        </ul>
                    </div>
                    <a href="{{ route('gestao.trabalhos.config', $evento) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-sliders me-1"></i> {{ $config ? 'Editar chamada' : 'Criar chamada' }}</a>
                    @if($config)
                        <a href="{{ route('gestao.trabalhos.novo', $evento) }}" class="btn btn-success fw-bold" title="Cadastrar um trabalho recebido por outro meio"><i class="fa-solid fa-file-circle-plus me-1"></i> Cadastrar trabalho</a>
                    @endif
                </div>
            </div>

            <nav class="gt-nav" aria-label="Áreas da gestão de trabalhos">
                <a href="{{ route('gestao.trabalhos.apresentacoes.index', $evento) }}"><i class="fa-solid fa-person-chalkboard"></i> Apresentações</a>
                <a href="{{ route('gestao.trabalhos.agenda.index', $evento) }}"><i class="fa-solid fa-video"></i> Agenda das orais</a>
                <a href="{{ route('gestao.trabalhos.ranking', $evento) }}"><i class="fa-solid fa-ranking-star"></i> Notas e ranking</a>
                <a href="{{ route('gestao.trabalhos.premiacao.index', $evento) }}"><i class="fa-solid fa-trophy"></i> Premiação</a>
                <a href="{{ route('gestao.trabalhos.certificados.index', $evento) }}"><i class="fa-solid fa-award"></i> Certificados</a>
                <a href="{{ route('gestao.trabalhos.anais.edit', $evento) }}"><i class="fa-solid fa-book"></i> Trabalhos Publicados</a>
            </nav>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
            @if($errors->any())     <div class="alert alert-danger small">{{ $errors->first() }}</div> @endif
            @if(session('erros_lote'))
                <div class="alert alert-warning small"><strong>Ignorados:</strong><ul class="mb-0 ps-3">@foreach(session('erros_lote') as $erro) <li>{{ $erro }}</li> @endforeach</ul></div>
            @endif

            @php $sit = \App\Models\TrabalhoConfig::situacaoDe($config); @endphp
            {{-- Estados normais (aberta/encerrada) viram um selo discreto; só o que pede ação vira alerta --}}
            @if(in_array($sit['chave'], ['aberta', 'encerrada']))
                <div class="mb-3 small text-secondary d-flex flex-wrap align-items-center gap-2"><x-badge tipo="chamada" :valor="$sit['chave']" /> <span>{{ $sit['descricao'] }}</span></div>
            @else
            <div class="alert alert-{{ $sit['cor'] === 'dark' ? 'secondary' : $sit['cor'] }} d-flex flex-wrap justify-content-between align-items-center gap-2 small">
                <div><strong>{{ $sit['rotulo'] }}.</strong> {{ $sit['descricao'] }}</div>
                @if(!$config)
                    <a href="{{ route('gestao.trabalhos.config', $evento) }}" class="btn btn-sm btn-primary-custom fw-bold"><i class="fa-solid fa-plus me-1"></i> Criar chamada de trabalhos</a>
                @elseif($sit['chave'] === 'rascunho')
                    <a href="{{ route('gestao.trabalhos.config', $evento) }}" class="btn btn-sm btn-dark fw-bold">Revisar e publicar</a>
                @endif
            </div>
            @endif

            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach($contagem as $status => $total)
                    <a href="{{ request()->fullUrlWithQuery(['status' => $status]) }}" class="text-decoration-none" title="Filtrar por este status">
                        <x-badge tipo="trabalho" :valor="$status" :extra="$total" />
                    </a>
                @endforeach
            </div>

            <form method="GET" class="glass-card bg-white mb-3">
                <div class="row g-2 filtros-grid">
                    <div class="col-md-4"><input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Título, protocolo, autor ou CPF"></div>
                    <div class="col-md-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Todos os status</option>
                            @foreach(\App\Models\Trabalho::STATUS + ['aprovado_escrita' => 'Aprovado na parte escrita', 'aguardando_certificado' => 'Aguardando emissão de certificado', 'concluido' => 'Concluído (certificados emitidos)'] as $v => $r) <option value="{{ $v }}" @selected(request('status') === $v)>{{ $r }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="pendencia" class="form-select form-select-sm" title="Filtrar pelo que está faltando no trabalho">
                            <option value="">Qualquer pendência</option>
                            @foreach(\App\Services\PendenciasTrabalho::ROTULOS as $v => $r) <option value="{{ $v }}" @selected(request('pendencia') === $v)>{{ $r }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="tipo" class="form-select form-select-sm">
                            <option value="">Todas as categorias</option>
                            @foreach(\App\Models\TrabalhoConfig::TIPOS as $v => $r) <option value="{{ $v }}" @selected(request('tipo') === $v)>{{ $r }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="area" class="form-select form-select-sm">
                            <option value="">Todas as áreas</option>
                            @foreach($areas as $a) <option value="{{ $a->id }}" @selected((int) request('area') === $a->id)>{{ $a->nome }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button class="btn btn-sm btn-dark flex-fill"><i class="fa-solid fa-filter"></i> Filtrar</button>
                        <a href="{{ route('gestao.trabalhos.index', $evento) }}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-eraser"></i> Limpar</a>
                    </div>
                </div>
            </form>

            <x-lote-avaliadores :action="route('gestao.trabalhos.lote', $evento)" :avaliadores="$avaliadores" :restricoes="$restricoes" :acoes="[
                ['valor' => 'atribuir', 'rotulo' => 'Atribuir avaliadores', 'icone' => 'fa-user-plus', 'tom' => 'btn-outline-success'],
                ['valor' => 'triagem', 'rotulo' => 'Iniciar triagem', 'icone' => 'fa-filter', 'tom' => 'btn-outline-primary'],
                ['valor' => 'avaliacao', 'rotulo' => 'Encaminhar para avaliação', 'icone' => 'fa-share', 'tom' => 'btn-outline-warning'],
                ['valor' => 'certificados', 'rotulo' => 'Emitir certificados', 'icone' => 'fa-award', 'tom' => 'btn-tone-purple'],
            ]" />

            <style>
                .lt { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; container: lt / inline-size; }
                .lt-cab, .lt-item { display: grid; grid-template-columns: 34px minmax(0, 1fr) 118px 215px 62px 88px 124px; column-gap: .85rem; align-items: center; padding: .7rem 1rem; }
                .lt-cab { background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: .7rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #64748b; }
                .lt-item { border-bottom: 1px solid #eef2f6; font-size: .85rem; transition: background .12s; }
                .lt-item:last-child { border-bottom: 0; }
                .lt-item:hover { background: #f6fbf8; }
                /* colunas explícitas: a ordem do HTML serve ao cartão do celular */
                .lt-item > * { grid-row: 1; }
                .lt-sel { grid-column: 1; } .lt-main { grid-column: 2; } .lt-cat { grid-column: 3; } .lt-status { grid-column: 4; } .lt-aval { grid-column: 5; } .lt-data { grid-column: 6; } .lt-acoes { grid-column: 7; }
                .lt-proto-box { display: none; }
                .lt-proto { display: inline-block; white-space: nowrap; font-size: .7rem; font-weight: 700; letter-spacing: .03em; color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: .05rem .45rem; margin-bottom: .25rem; }
                .lt-titulo { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-weight: 700; color: #0f172a; line-height: 1.3; text-decoration: none; overflow-wrap: anywhere; }
                .lt-titulo:hover { color: #047857; }
                .lt-autores { display: block; color: #64748b; font-size: .78rem; margin-top: .15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .lt-cat { min-width: 0; line-height: 1.35; } .lt-cat small { display: block; color: #64748b; }
                .lt-aval { font-variant-numeric: tabular-nums; } .lt-aval .barra { height: 5px; border-radius: 99px; background: #e2e8f0; overflow: hidden; margin-top: .25rem; } .lt-aval .barra > i { display: block; height: 100%; background: #10b981; }
                .lt-data small { display: block; color: #64748b; }
                .lt-status { min-width: 0; } .lt-status .sb { max-width: 100%; white-space: normal; line-height: 1.25; overflow-wrap: anywhere; }
                .lt-acoes { display: flex; justify-content: flex-end; gap: .35rem; flex-wrap: wrap; }
                .lt-rot { display: none; }
                .lt-meta { display: contents; }
                /* Conteúdo estreito (celular, tablet ou tela com a barra lateral aberta): cada trabalho vira um cartão compacto */
                @container lt (max-width: 920px) {
                    .lt-cab { display: none; }
                    .lt-item { grid-template-columns: 28px minmax(0, 1fr) auto; row-gap: .5rem; padding: .85rem; }
                    .lt-item > * { grid-row: auto; }
                    .lt-sel { grid-column: 1; grid-row: 1; } .lt-proto-box { display: block; grid-column: 2; grid-row: 1; } .lt-status { grid-column: 1 / -1; grid-row: 2; text-align: left; display: flex; flex-wrap: wrap; gap: .3rem; } .lt-status > div { margin: 0 !important; }
                    .lt-main { grid-column: 1 / -1; grid-row: 3; } .lt-main .lt-proto { display: none; }
                    .lt-meta { display: flex; flex-wrap: wrap; gap: .35rem .5rem; grid-column: 1 / -1; grid-row: 4; }
                    .lt-meta > div { grid-column: auto; background: #f1f5f9; border-radius: 8px; padding: .2rem .55rem; font-size: .75rem; color: #334155; }
                    .lt-meta .lt-rot { display: inline; font-weight: 700; color: #64748b; margin-right: .25rem; }
                    .lt-meta .barra { display: none; } .lt-cat small, .lt-data small { display: inline; } .lt-cat small::before { content: ' · '; } .lt-data small::before { content: ' '; }
                    .lt-acoes { grid-column: 1 / -1; grid-row: 5; justify-content: flex-start; }
                    .lt-autores { white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
                }
            </style>

            <div class="lt">
                <div class="lt-cab" role="row">
                    <div><input type="checkbox" class="form-check-input sel-todos" title="Marcar todos desta página"></div>
                    <div>Trabalho</div><div>Categoria / área</div><div>Status</div><div>Aval.</div><div>Enviado</div><div></div>
                </div>
                @forelse($trabalhos as $t)
                    @php
                        $ativas = $t->avaliacoes->where('status', '!=', 'recusada')->count();
                        $minimo = max(1, (int) ($config?->avaliadores_por_trabalho ?? 2));
                        $aptoAval = $t->status === 'em_triagem' && $ativas >= $minimo;
                        $situacaoFinal = $t->statusFinal();
                        $autoresTxt = $t->autores->pluck('nome_completo')->take(3)->implode(', ') . ($t->autores->count() > 3 ? ' +' . ($t->autores->count() - 3) : '');
                    @endphp
                    <div class="lt-item">
                        <div class="lt-sel"><input type="checkbox" form="lote" name="trabalhos[]" value="{{ $t->id }}" class="form-check-input sel-trabalho" data-codigo="{{ $t->codigo }}" data-atribuir="{{ in_array($t->status, \App\Services\TrabalhoWorkflow::STATUS_ATRIBUICAO, true) ? 1 : 0 }}" data-triagem="{{ in_array($t->status, [\App\Models\Trabalho::SUBMETIDO, 'resubmetido'], true) ? 1 : 0 }}" data-certificados="{{ $situacaoFinal === 'aguardando_certificado' ? 1 : 0 }}" data-motivo-certificados="{{ $situacaoFinal === 'aguardando_certificado' ? '' : ($situacaoFinal === 'concluido' ? 'certificados já emitidos' : 'situação: ' . strtolower($t->statusFinalRotulo())) }}" data-avaliacao="{{ $aptoAval ? 1 : 0 }}" data-motivo-avaliacao="{{ $aptoAval ? '' : ($t->status !== 'em_triagem' ? 'não está em triagem' : 'faltam ' . ($minimo - $ativas) . ' avaliador(es)') }}"></div>
                        <div class="lt-proto-box"><span class="lt-proto">{{ $t->codigo }}</span></div>
                        <div class="lt-main">
                            <span class="lt-proto">{{ $t->codigo }}</span>
                            <a href="{{ route('gestao.trabalhos.show', $t) }}" class="lt-titulo" title="{{ $t->titulo }}">{{ $t->titulo }}</a>
                            <span class="lt-autores" title="{{ $t->autores->pluck('nome_completo')->implode(', ') }}">{{ $autoresTxt ?: '—' }}</span>
                        </div>
                        <div class="lt-meta">
                            <div class="lt-cat"><span class="lt-rot">Categoria</span>{{ \App\Models\TrabalhoConfig::TIPOS[$t->tipo_trabalho] ?? '—' }}<small>{{ $t->area->nome ?? 'sem área' }}</small></div>
                            <div class="lt-status-m d-none"></div>
                            <div class="lt-aval"><span class="lt-rot">Aval.</span>{{ $ativas }}/{{ $minimo }}<div class="barra"><i style="width: {{ min(100, round($ativas / $minimo * 100)) }}%"></i></div></div>
                            <div class="lt-data"><span class="lt-rot">Enviado</span>{{ $t->submetido_em?->format('d/m/Y') ?? '—' }}<small>{{ $t->submetido_em?->format('H:i') }}</small></div>
                        </div>
                        <div class="lt-status">
                            <x-badge tipo="trabalho" :valor="$t->statusFinal()" :extra="$t->extraSituacao()" />
                            @foreach(($pendencias[$t->id] ?? []) as $chave)
                                <div class="mt-1"><x-badge tipo="pendencia" :valor="$chave" /></div>
                            @endforeach
                        </div>
                        <div class="lt-acoes">
                            @if($t->status === 'resubmetido')
                                <form action="{{ route('gestao.trabalhos.decisao', $t) }}" method="POST" class="d-inline">
                                    @csrf <input type="hidden" name="decisao" value="aprovado">
                                    <button class="btn btn-sm btn-success" title="Aprovar o trabalho reenviado" data-confirmar="Aprovar {{ $t->codigo }} (reenviado após correção)? Os autores serão avisados por e-mail."><i class="fa-solid fa-circle-check"></i> Aprovar</button>
                                </form>
                            @endif
                            @if($t->arquivoAtual) <a href="{{ route('trabalhos.arquivo', $t) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Abrir PDF" aria-label="Abrir PDF"><i class="fa-solid fa-file-pdf text-danger"></i></a> @endif
                            <a href="{{ route('gestao.trabalhos.show', $t) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-folder-open"></i> Abrir</a>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5"><i class="fa-regular fa-folder-open fs-3 d-block mb-2 opacity-50"></i> Nenhum trabalho encontrado{{ request()->hasAny(['q', 'status', 'tipo', 'area', 'pendencia']) ? ' com esses filtros' : '' }}.</div>
                @endforelse
            </div>
            <div class="mt-3">{{ $trabalhos->links() }}</div>
        </div>
    </div>
</x-app-layout>
