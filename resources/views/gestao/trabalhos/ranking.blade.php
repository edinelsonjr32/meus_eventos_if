@php
    $fmt = fn ($n) => $n === null ? '—' : number_format($n, 2, ',', '.');
    $filtros = request()->only(['tipo', 'area', 'status']);
    $tipoAtual = request('tipo');
    $totalGeral = $contagemTipos->sum();
    $medalha = [1 => ['#facc15', '1º'], 2 => ['#cbd5e1', '2º'], 3 => ['#d6a06b', '3º']];
@endphp
<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-11">

            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('gestao.trabalhos.index', $evento) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
                    <div>
                        <h3 class="fw-bold m-0">Notas e ranking</h3>
                        <small class="text-secondary">{{ $evento->titulo }} · uso restrito à gestão (contém dados da avaliação)</small>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('gestao.trabalhos.premiacao.index', $evento) }}" class="btn btn-warning fw-bold"><i class="fa-solid fa-trophy"></i> Premiação</a>
                    <div class="btn-group">
                        <a href="{{ route('gestao.trabalhos.exportar-notas', array_merge(['evento' => $evento->id, 'relatorio' => 'resumo', 'formato' => 'xlsx'], $filtros)) }}" class="btn btn-outline-success fw-bold"><i class="fa-solid fa-file-excel me-1"></i> Ranking (Excel)</a>
                        <a href="{{ route('gestao.trabalhos.exportar-notas', array_merge(['evento' => $evento->id, 'relatorio' => 'resumo', 'formato' => 'csv'], $filtros)) }}" class="btn btn-outline-secondary fw-bold">CSV</a>
                    </div>
                    <div class="btn-group">
                        <a href="{{ route('gestao.trabalhos.exportar-notas', array_merge(['evento' => $evento->id, 'relatorio' => 'detalhado', 'formato' => 'xlsx'], $filtros)) }}" class="btn btn-outline-success fw-bold"><i class="fa-solid fa-file-excel me-1"></i> Pareceres (Excel)</a>
                        <a href="{{ route('gestao.trabalhos.exportar-notas', array_merge(['evento' => $evento->id, 'relatorio' => 'detalhado', 'formato' => 'csv'], $filtros)) }}" class="btn btn-outline-secondary fw-bold">CSV</a>
                    </div>
                </div>
            </div>

            {{-- Abas por categoria (tipos de trabalho aceitos no evento) --}}
            <ul class="nav nav-pills gap-2 mb-3 flex-wrap">
                <li class="nav-item"><a class="nav-link {{ !$tipoAtual ? 'active' : 'bg-white border' }} fw-bold" href="{{ route('gestao.trabalhos.ranking', array_merge(['evento' => $evento->id], request()->except(['tipo', 'page']))) }}"><i class="fa-solid fa-layer-group me-1"></i> Todas <span class="badge bg-secondary bg-opacity-25 text-dark">{{ $totalGeral }}</span></a></li>
                @foreach($categorias as $tipo => $rotulo)
                    <li class="nav-item"><a class="nav-link {{ $tipoAtual === $tipo ? 'active' : 'bg-white border' }} fw-bold" href="{{ route('gestao.trabalhos.ranking', array_merge(['evento' => $evento->id], request()->except(['page']), ['tipo' => $tipo])) }}"><i class="fa-solid fa-medal me-1"></i> {{ $rotulo }} <span class="badge bg-secondary bg-opacity-25 text-dark">{{ $contagemTipos[$tipo] ?? 0 }}</span></a></li>
                @endforeach
            </ul>

            <div id="ranking-app" data-evento="{{ $evento->id }}">
                {{-- Resumo (acompanha os filtros) --}}
                <div class="row g-3 mb-3">
                    @foreach([['r-qtd', 'Trabalhos', 'fa-file-lines', '#1d4ed8', '#dbeafe'], ['r-media', 'Média da pontuação', 'fa-chart-line', '#15803d', '#dcfce7'], ['r-max', 'Maior pontuação', 'fa-arrow-trend-up', '#a16207', '#fef9c3'], ['r-div', 'Divergência alta', 'fa-triangle-exclamation', '#b45309', '#fef3c7'], ['r-pend', 'Avaliação pendente', 'fa-hourglass-half', '#7e22ce', '#f3e8ff']] as [$id, $rot, $ic, $cor, $fundo])
                        <div class="col-6 col-md">
                            <div class="glass-card bg-white p-3 d-flex align-items-center gap-3">
                                <div class="rounded p-2" style="background:{{ $fundo }};color:{{ $cor }};"><i class="fa-solid {{ $ic }}"></i></div>
                                <div><div class="fs-5 fw-bold lh-1" id="{{ $id }}">—</div><small class="text-secondary" style="font-size:.7rem;">{{ $rot }}</small></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Filtros interativos --}}
                <div class="glass-card bg-white mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-lg-4">
                            <label class="small fw-bold text-secondary">Buscar</label>
                            <div class="position-relative"><i class="fa-solid fa-magnifying-glass position-absolute text-secondary" style="top:11px;left:12px;"></i>
                                <input type="search" id="r-busca" class="form-control form-control-sm ps-5" placeholder="Protocolo, título ou autor"></div>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="small fw-bold text-secondary">Área</label>
                            <select id="r-area" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                @foreach($areas as $a) <option value="{{ $a->id }}">{{ $a->nome }}</option> @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-lg-3">
                            <label class="small fw-bold text-secondary">Situação</label>
                            <select id="r-situacao" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                @foreach(['aprovado' => 'Aprovado', 'aprovado_escrita' => 'Aprovado na parte escrita', 'aguardando_certificado' => 'Aguardando emissão de certificado', 'concluido' => 'Concluído', 'avaliado' => 'Avaliado', 'em_avaliacao' => 'Em avaliação', 'aguardando_correcao' => 'Aguardando correção', 'resubmetido' => 'Resubmetido', 'reprovado' => 'Reprovado'] as $v => $r) <option value="{{ $v }}">{{ $r }}</option> @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-lg-1">
                            <label class="small fw-bold text-secondary">Mostrar</label>
                            <select id="r-top" class="form-select form-select-sm"><option value="">Todos</option><option value="3">Top 3</option><option value="10">Top 10</option></select>
                        </div>
                        <div class="col-6 col-lg-2 d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="r-limpar"><i class="fa-solid fa-eraser"></i> Limpar</button>
                            <button type="button" class="btn btn-sm btn-outline-success flex-fill" id="r-csv" title="Baixar o que está na tela (CSV)"><i class="fa-solid fa-file-csv"></i></button>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @foreach([['com-nota', 'Só com nota', 'fa-star'], ['div-alta', 'Divergência alta', 'fa-triangle-exclamation'], ['pendente', 'Avaliação pendente', 'fa-hourglass-half'], ['premiado', 'Premiados', 'fa-trophy']] as [$chave, $rot, $ic])
                            <label class="r-chip btn btn-sm btn-outline-secondary mb-0"><input type="checkbox" class="d-none" data-chip="{{ $chave }}"><i class="fa-solid {{ $ic }}"></i> {{ $rot }}</label>
                        @endforeach
                        <span class="small text-muted align-self-center ms-auto" id="r-contagem"></span>
                    </div>
                </div>

                <div class="glass-card bg-white p-0 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small" id="r-tabela">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3" style="width:36px"></th>
                                    <th data-ordem="pos" style="cursor:pointer" title="Posição na categoria">#</th>
                                    <th>Protocolo</th><th data-ordem="titulo" style="cursor:pointer">Título</th><th>Categoria</th>
                                    <th class="text-center" data-ordem="texto" style="cursor:pointer">Nota média</th><th class="text-center" data-ordem="apres" style="cursor:pointer">Apresentação</th><th class="text-center" data-ordem="geral" style="cursor:pointer">Geral</th>
                                    <th>Notas individuais</th><th>Recomendações</th><th>Situação</th><th></th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($linhas as $l)
                                @php
                                    $t = $l['trabalho'];
                                    $divAlta = $l['divergencia'] !== null && $l['divergencia'] > $limiteDivergencia;
                                    $pendente = $l['atribuidas'] > $l['concluidas'];
                                    $premio = $premios[$t->id] ?? null;
                                    $pont = $l['pontuacao'];
                                    $posCat = $l['posicao_categoria'];
                                @endphp
                                <tr class="r-linha" data-tipo="{{ $t->tipo_trabalho }}" data-area="{{ $t->area_tematica_id }}" data-situacao="{{ $t->statusFinal() }}"
                                    data-busca="{{ mb_strtolower($t->codigo . ' ' . $t->titulo . ' ' . $t->autores->pluck('nome_completo')->implode(' ')) }}"
                                    data-pont="{{ $pont ?? '' }}" data-texto="{{ $l['nota'] ?? '' }}" data-apres="{{ $l['nota_apresentacao'] ?? '' }}" data-geral="{{ $l['nota_geral'] ?? '' }}"
                                    data-pos="{{ $posCat ?? 999 }}" data-titulo="{{ mb_strtolower($t->titulo) }}" data-divalta="{{ $divAlta ? 1 : 0 }}" data-pendente="{{ $pendente ? 1 : 0 }}" data-premiado="{{ $premio ? 1 : 0 }}"
                                    data-codigo="{{ $t->codigo }}" data-rotulo="{{ \App\Services\RelatorioNotas::categoria($t->tipo_trabalho) }}">
                                    <td class="ps-3"><button type="button" class="btn btn-sm btn-light r-expandir" title="Ver critérios e notas de cada avaliador"><i class="fa-solid fa-chevron-down"></i></button></td>
                                    <td class="fw-bold text-nowrap">
                                        @if($posCat !== null && $posCat <= 3)
                                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle fw-bold" style="width:26px;height:26px;background:{{ $medalha[$posCat][0] }};color:#422006;font-size:.72rem;" title="{{ $posCat }}º na categoria (pontuação {{ $fmt($pont) }})">{{ $posCat }}º</span>
                                        @else {{ $posCat ?? '—' }} @endif
                                        @if(!$l['elegivel_premio'] && $pont !== null) <div class="text-muted fw-normal" style="font-size:.62rem;" title="Só concorre à colocação quem foi aprovado nas duas partes">provisório</div> @endif
                                        <div class="text-muted fw-normal" style="font-size:.65rem;">geral {{ $l['posicao'] ?? '—' }}</div>
                                    </td>
                                    <td class="fw-bold text-nowrap">{{ $t->codigo }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($t->titulo, 60) }}
                                        @if($premio) <div><x-badge tipo="premio" :valor="(string) $premio->colocacao" /></div> @endif
                                    </td>
                                    <td>{{ \App\Services\RelatorioNotas::categoria($t->tipo_trabalho) ?: '—' }}</td>
                                    <td class="text-center">
                                        <span class="fw-bold {{ $l['nota'] !== null && $l['nota'] >= $notaMinima ? 'text-success' : 'text-dark' }}">{{ $fmt($l['nota']) }}</span>
                                        <div class="text-muted">{{ $l['concluidas'] }}/{{ $l['atribuidas'] }} concl.</div>
                                    </td>
                                    <td class="text-center">{{ $fmt($l['nota_apresentacao'] ?? null) }}</td>
                                    <td class="text-center" style="min-width:110px;">
                                        <span class="fw-bold">{{ $fmt($l['nota_geral'] ?? null) }}</span>
                                        @if($pont !== null)
                                            <div class="progress mt-1" style="height:5px;" title="Pontuação {{ $fmt($pont) }} de 10"><div class="progress-bar {{ $pont >= $notaMinima ? 'bg-success' : 'bg-warning' }}" style="width: {{ min(100, $pont * 10) }}%"></div></div>
                                        @endif
                                    </td>
                                    <td>
                                        {{ collect($l['notas'])->map(fn ($n) => number_format($n, 2, ',', '.'))->implode(' · ') ?: '—' }}
                                        @if($divAlta)
                                            <div class="text-warning fw-bold"><i class="fa-solid fa-triangle-exclamation"></i> divergência {{ $fmt($l['divergencia']) }}</div>
                                        @endif
                                    </td>
                                    <td>{{ \App\Services\RelatorioNotas::rotuloRecomendacoes($l['recomendacoes']) ?: '—' }}</td>
                                    <td>
                                        <x-badge tipo="trabalho" :valor="$t->statusFinal()" :extra="$t->extraSituacao()" />
                                        @if($t->decisao) <div class="text-muted">{{ ['aprovado' => 'Aprovado', 'aprovado_com_ressalvas' => 'Com ressalvas', 'reprovado' => 'Reprovado'][$t->decisao] }}</div> @endif
                                    </td>
                                    <td class="text-end pe-3"><a href="{{ route('gestao.trabalhos.show', $t) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-folder-open"></i> Abrir</a></td>
                                </tr>
                                <tr class="r-detalhe d-none bg-light">
                                    <td></td>
                                    <td colspan="11" class="py-3">
                                        <div class="row g-3">
                                            <div class="col-lg-6">
                                                <div class="fw-bold mb-1">Média por critério</div>
                                                @forelse($l['por_criterio'] as $nomeCriterio => $media)
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <span style="width:42%">{{ $nomeCriterio }}</span>
                                                        <div class="progress flex-fill" style="height:6px;"><div class="progress-bar bg-success" style="width: {{ $media !== null ? min(100, $media * 10) : 0 }}%"></div></div>
                                                        <strong style="width:48px;text-align:right">{{ $fmt($media) }}</strong>
                                                    </div>
                                                @empty <span class="text-muted">Sem critérios definidos.</span> @endforelse
                                            </div>
                                            <div class="col-lg-6">
                                                <div class="fw-bold mb-1">Avaliadores (parte escrita)</div>
                                                @forelse($l['avaliacoes_detalhe'] as $av)
                                                    <div class="d-flex justify-content-between"><span>{{ $av['avaliador'] }} @if($av['desempate'])<span class="sb" style="background:#fef3c7;color:#b45309;border-color:#fcd34d;"><i class="fa-solid fa-scale-balanced"></i> Desempate</span>@endif</span>
                                                        <span><strong>{{ $fmt($av['nota']) }}</strong> <span class="text-muted">· {{ $av['recomendacao'] ?? '—' }}</span></span></div>
                                                @empty <span class="text-muted">Nenhuma avaliação concluída.</span> @endforelse
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="12" class="text-center text-muted py-5">Nenhum trabalho encontrado.</td></tr>
                            @endforelse
                            <tr id="r-vazio" class="d-none"><td colspan="12" class="text-center text-muted py-5"><i class="fa-solid fa-filter-circle-xmark fs-3 d-block mb-2"></i> Nenhum trabalho com esses filtros. <a href="#" id="r-vazio-limpar">Limpar filtros</a></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <p class="small text-muted mt-2">A nota média considera as avaliações concluídas da rodada vigente (escala 0–10). A posição (#) vale só para trabalhos aprovados nas duas partes e é dentro da categoria, pela pontuação (nota geral: texto + apresentação); os demais aparecem como provisórios. Abaixo dela, a posição geral pela nota do texto. Posições empatadas dividem o mesmo número.
                Clique no cabeçalho para ordenar e na seta da linha para ver critérios e avaliadores. Em "Média por critério" e nos pareceres completos, use as exportações.</p>
        </div>
    </div>

    <style>
        .r-chip { cursor: pointer; }
        .r-chip:has(input:checked) { background: #16a34a; border-color: #16a34a; color: #fff; }
        #r-tabela th[data-ordem]:hover { color: #16a34a; }
        #r-tabela th.ord-asc::after { content: ' ▲'; } #r-tabela th.ord-desc::after { content: ' ▼'; }
        .r-expandir i { transition: transform .15s; } .r-expandir.aberto i { transform: rotate(180deg); }
    </style>
    <script>
        (function () {
            const raiz = document.getElementById('ranking-app');
            if (!raiz) return;
            const $ = (s) => raiz.querySelector(s);
            const tbody = $('#r-tabela tbody');
            const pares = Array.from(tbody.querySelectorAll('tr.r-linha')).map((l) => ({ linha: l, detalhe: l.nextElementSibling }));
            const estado = { busca: '', area: '', situacao: '', top: '', chips: new Set(), ordem: null, dir: 'desc' };
            const num = (v) => (v === '' || v == null ? null : parseFloat(v));
            const fmt = (n) => n === null ? '—' : n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            function visivel(d) {
                if (estado.busca && !d.busca.includes(estado.busca)) return false;
                if (estado.area && d.area !== estado.area) return false;
                if (estado.situacao && d.situacao !== estado.situacao) return false;
                if (estado.chips.has('com-nota') && num(d.pont) === null) return false;
                if (estado.chips.has('div-alta') && d.divalta !== '1') return false;
                if (estado.chips.has('pendente') && d.pendente !== '1') return false;
                if (estado.chips.has('premiado') && d.premiado !== '1') return false;
                return true;
            }

            function aplicar() {
                let lista = pares.filter((p) => visivel(p.linha.dataset));
                if (estado.ordem) {
                    const chave = estado.ordem;
                    lista.sort((a, b) => {
                        const x = chave === 'titulo' ? a.linha.dataset.titulo : (num(a.linha.dataset[chave]) ?? (estado.dir === 'asc' ? Infinity : -Infinity));
                        const y = chave === 'titulo' ? b.linha.dataset.titulo : (num(b.linha.dataset[chave]) ?? (estado.dir === 'asc' ? Infinity : -Infinity));
                        const r = chave === 'titulo' ? x.localeCompare(y) : x - y;
                        return estado.dir === 'asc' ? r : -r;
                    });
                } else {
                    lista.sort((a, b) => (num(b.linha.dataset.pont) ?? -1) - (num(a.linha.dataset.pont) ?? -1) || a.linha.dataset.codigo.localeCompare(b.linha.dataset.codigo));
                }
                if (estado.top) lista = lista.slice(0, parseInt(estado.top, 10));

                const mostrar = new Set(lista.map((p) => p.linha));
                pares.forEach((p) => { if (!mostrar.has(p.linha)) { p.linha.classList.add('d-none'); p.detalhe.classList.add('d-none'); } });
                const vazio = $('#r-vazio');
                lista.forEach((p) => { p.linha.classList.remove('d-none'); tbody.insertBefore(p.linha, vazio); tbody.insertBefore(p.detalhe, vazio); if (!p.linha.classList.contains('aberta')) p.detalhe.classList.add('d-none'); });
                vazio.classList.toggle('d-none', lista.length > 0 || pares.length === 0);

                const pont = lista.map((p) => num(p.linha.dataset.pont)).filter((n) => n !== null);
                $('#r-qtd').textContent = lista.length;
                $('#r-media').textContent = pont.length ? fmt(pont.reduce((a, b) => a + b, 0) / pont.length) : '—';
                $('#r-max').textContent = pont.length ? fmt(Math.max(...pont)) : '—';
                $('#r-div').textContent = lista.filter((p) => p.linha.dataset.divalta === '1').length;
                $('#r-pend').textContent = lista.filter((p) => p.linha.dataset.pendente === '1').length;
                $('#r-contagem').textContent = `${lista.length} de ${pares.length} trabalho(s)`;
                raiz.querySelectorAll('th[data-ordem]').forEach((th) => th.classList.remove('ord-asc', 'ord-desc'));
                if (estado.ordem) raiz.querySelector(`th[data-ordem="${estado.ordem}"]`)?.classList.add(estado.dir === 'asc' ? 'ord-asc' : 'ord-desc');
            }

            $('#r-busca').addEventListener('input', (e) => { estado.busca = e.target.value.trim().toLowerCase(); aplicar(); });
            $('#r-area').addEventListener('change', (e) => { estado.area = e.target.value; aplicar(); });
            $('#r-situacao').addEventListener('change', (e) => { estado.situacao = e.target.value; aplicar(); });
            $('#r-top').addEventListener('change', (e) => { estado.top = e.target.value; aplicar(); });
            raiz.querySelectorAll('[data-chip]').forEach((c) => c.addEventListener('change', () => { c.checked ? estado.chips.add(c.dataset.chip) : estado.chips.delete(c.dataset.chip); aplicar(); }));
            raiz.querySelectorAll('th[data-ordem]').forEach((th) => th.addEventListener('click', () => {
                const chave = th.dataset.ordem;
                estado.dir = (estado.ordem === chave && estado.dir === 'desc') ? 'asc' : (chave === 'pos' || chave === 'titulo' ? 'asc' : 'desc');
                if (estado.ordem === chave && estado.dir === 'asc' && (chave === 'pos' || chave === 'titulo') && th.classList.contains('ord-asc')) estado.dir = 'desc';
                estado.ordem = chave; aplicar();
            }));
            function limpar() {
                Object.assign(estado, { busca: '', area: '', situacao: '', top: '', ordem: null, dir: 'desc' }); estado.chips.clear();
                $('#r-busca').value = ''; $('#r-area').value = ''; $('#r-situacao').value = ''; $('#r-top').value = '';
                raiz.querySelectorAll('[data-chip]').forEach((c) => { c.checked = false; }); aplicar();
            }
            $('#r-limpar').addEventListener('click', limpar);
            $('#r-vazio-limpar').addEventListener('click', (e) => { e.preventDefault(); limpar(); });
            tbody.addEventListener('click', (e) => {
                const b = e.target.closest('.r-expandir'); if (!b) return;
                const linha = b.closest('tr'); const det = linha.nextElementSibling;
                const abrir = det.classList.contains('d-none');
                det.classList.toggle('d-none', !abrir); linha.classList.toggle('aberta', abrir); b.classList.toggle('aberto', abrir);
            });
            $('#r-csv').addEventListener('click', () => {
                const cab = ['Posição na categoria', 'Protocolo', 'Título', 'Categoria', 'Nota média', 'Apresentação', 'Geral'];
                const linhas = pares.filter((p) => !p.linha.classList.contains('d-none')).map((p) => { const d = p.linha.dataset; return [d.pos === '999' ? '' : d.pos, d.codigo, p.linha.cells[3].innerText.split('\n')[0].trim(), d.rotulo, d.texto, d.apres, d.geral]; });
                const csv = '﻿' + [cab, ...linhas].map((r) => r.map((c) => '"' + String(c ?? '').replace(/"/g, '""') + '"').join(';')).join('\r\n');
                const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })); a.download = 'ranking-visivel.csv'; a.click();
            });
            aplicar();
        })();
    </script>
</x-app-layout>
