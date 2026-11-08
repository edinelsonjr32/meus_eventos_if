<x-app-layout>
    <div class="row justify-content-center" x-data="kioskApp()">
        <div class="col-xl-10">

            {{-- Cabeçalho --}}
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ auth()->user()->role === 'admin' ? route('admin.eventos.show', $evento->id) : route('recepcao.index') }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
                    <div>
                        <h3 class="fw-bold m-0"><i class="fa-solid fa-id-badge text-success me-1"></i> Recepção &amp; Credenciamento</h3>
                        <small class="text-secondary">{{ $evento->titulo }}</small>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <a href="{{ route('admin.eventos.credenciamento.lista', $evento->id) }}" class="btn btn-outline-primary fw-bold"><i class="fa-solid fa-list-check"></i> Lista de inscritos</a>
                    <a href="{{ route('admin.eventos.credenciamento.create', $evento->id) }}" class="btn btn-tone-teal fw-bold"><i class="fa-solid fa-user-plus"></i> Cadastrar na hora</a>
                </div>
            </div>

            {{-- Resumo ao vivo (sempre vindo do servidor) --}}
            <div class="row g-3 mb-4">
                <div class="col-4"><div class="glass-card bg-white p-3 d-flex align-items-center gap-3">
                    <div class="rounded p-2" style="background:#dcfce7;color:#15803d;"><i class="fa-solid fa-circle-check fs-5"></i></div>
                    <div><div class="fs-4 fw-bold lh-1" x-text="stats.credenciados">{{ $totalCredenciados }}</div><small class="text-secondary">Check-in feito</small></div></div></div>
                <div class="col-4"><div class="glass-card bg-white p-3 d-flex align-items-center gap-3">
                    <div class="rounded p-2" style="background:#fef3c7;color:#b45309;"><i class="fa-solid fa-hourglass-half fs-5"></i></div>
                    <div><div class="fs-4 fw-bold lh-1" x-text="stats.total - stats.credenciados">{{ $totalInscritos - $totalCredenciados }}</div><small class="text-secondary">Pendentes</small></div></div></div>
                <div class="col-4"><div class="glass-card bg-white p-3">
                    <div class="d-flex justify-content-between align-items-baseline"><span class="fs-4 fw-bold lh-1"><span x-text="stats.total ? Math.round(stats.credenciados * 100 / stats.total) : 0">{{ $totalInscritos ? round($totalCredenciados * 100 / $totalInscritos) : 0 }}</span>%</span><small class="text-secondary"><span x-text="stats.credenciados">{{ $totalCredenciados }}</span> / <span x-text="stats.total">{{ $totalInscritos }}</span></small></div>
                    <div class="progress mt-2" style="height:6px;"><div class="progress-bar bg-success" :style="`width:${stats.total ? stats.credenciados * 100 / stats.total : 0}%`"></div></div>
                    <small class="text-secondary">Comparecimento</small></div></div>
            </div>

            {{-- Busca --}}
            <div class="glass-card p-4 mb-4 text-white" style="background: linear-gradient(135deg, #064e3b 0%, #047857 100%);">
                <label class="fw-bold mb-2 text-white text-opacity-75 small text-uppercase" for="busca-participante">Buscar participante</label>
                <div class="position-relative">
                    <i class="fa-solid fa-magnifying-glass position-absolute text-secondary" style="top:18px;left:20px;font-size:1.1rem;"></i>
                    <input id="busca-participante" type="text" x-model="query" @input.debounce.300ms="search()" @keydown.enter.prevent="entrar()" @keydown.escape="query = ''; results = []"
                           class="form-control form-control-lg ps-5 pe-5 border-0 shadow-lg py-3 fs-5" placeholder="Digite o nome, CPF ou e-mail…" autocomplete="off" autofocus>
                    <button type="button" class="btn btn-sm btn-light position-absolute" style="top:14px;right:14px;" x-show="query" @click="query = ''; results = []; $el.parentElement.querySelector('input').focus()" title="Limpar busca"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="small mt-2 text-white text-opacity-75"><i class="fa-regular fa-keyboard me-1"></i> Com um único resultado pendente, <kbd>Enter</kbd> faz o check-in.</div>
            </div>

            {{-- Resultados --}}
            <div class="glass-card bg-white p-0 overflow-hidden" x-show="query !== ''" x-cloak>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light small">
                    <span class="fw-bold text-secondary text-uppercase" style="font-size:.7rem;letter-spacing:.04em;">Resultados</span>
                    <span class="text-muted"><span x-show="buscando"><span class="spinner-border spinner-border-sm me-1"></span> buscando…</span><span x-show="!buscando" x-text="results.length + ' encontrado(s)'"></span></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead>
                            <tr><th class="ps-3">Participante</th><th>Vínculo</th><th>Curso / Turma</th><th>Status</th><th class="text-end pe-3">Ações</th></tr>
                        </thead>
                        <tbody>
                            <template x-for="item in results" :key="item.participante.id">
                                <tr :class="item.checkin_at ? 'table-success' : ''">
                                    <td class="ps-3 py-3">
                                        <div class="fw-bold" x-text="item.participante.nome_completo"></div>
                                        <div class="text-muted"><i class="fa-regular fa-id-card me-1"></i><span x-text="item.participante.cpf"></span></div>
                                    </td>
                                    <td><span class="sb" :class="'sb-' + item.participante.tipo_vinculo" x-text="item.participante.tipo_vinculo.charAt(0).toUpperCase() + item.participante.tipo_vinculo.slice(1)"></span></td>
                                    <td>
                                        <template x-if="item.participante.turma && item.participante.turma.curso">
                                            <div><div class="fw-bold" x-text="item.participante.turma.curso.nome"></div><div class="text-muted" x-text="item.participante.turma.nome_completo"></div></div>
                                        </template>
                                        <template x-if="item.participante.tipo_vinculo === 'aluno' && !item.participante.turma"><span class="sb sb-pend"><i class="fa-solid fa-triangle-exclamation"></i> Aluno sem turma</span></template>
                                        <template x-if="item.participante.tipo_vinculo !== 'aluno'"><span class="text-muted">—</span></template>
                                    </td>
                                    <td class="text-nowrap">
                                        <template x-if="item.checkin_at"><span class="sb sb-ok"><i class="fa-solid fa-circle-check"></i> Check-in feito</span></template>
                                        <template x-if="!item.checkin_at"><span class="sb sb-pend"><i class="fa-solid fa-hourglass-half"></i> Pendente</span></template>
                                    </td>
                                    <td class="text-end pe-3 text-nowrap">
                                        <button type="button" x-show="!item.checkin_at" @click="doCheckin(item)" :disabled="item.carregando === true" class="btn btn-sm btn-success">
                                            <span x-show="item.carregando === true" class="spinner-border spinner-border-sm"></span><i x-show="item.carregando !== true" class="fa-solid fa-check"></i> Fazer check-in
                                        </button>
                                        <button type="button" @click="printBadge(item)" class="btn btn-sm btn-tone-amber"><i class="fa-solid fa-print"></i> Etiqueta</button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="results.length === 0 && !buscando && query.length > 2">
                                <td colspan="5" class="text-center py-5">
                                    <i class="fa-solid fa-user-slash fs-1 text-secondary opacity-25 d-block mb-3"></i>
                                    <h5 class="fw-bold">Ninguém encontrado</h5>
                                    <p class="text-muted small mb-3">O participante não está pré-inscrito neste evento.</p>
                                    <a href="{{ route('admin.eventos.credenciamento.create', $evento->id) }}" class="btn btn-success fw-bold"><i class="fa-solid fa-user-plus"></i> Cadastrar novo participante</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="query === ''" class="glass-card bg-white text-center py-5">
                <i class="fa-solid fa-id-badge fs-1 text-secondary opacity-25 mb-3"></i>
                <h5 class="text-secondary fw-bold">Aguardando pesquisa…</h5>
                <p class="text-muted small mb-0">Comece a digitar para encontrar inscritos.</p>
            </div>
        </div>
    </div>

    <script>
        function kioskApp() {
            return {
                query: '',
                results: [],
                buscando: false,
                stats: { credenciados: {{ $totalCredenciados }}, total: {{ $totalInscritos }} },

                init() {},

                async atualizarStats() {
                    try {
                        const r = await fetch(`{{ route('admin.eventos.credenciamento.stats', $evento->id) }}`, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                        if (r.ok) { const d = await r.json(); this.stats.credenciados = d.credenciados; this.stats.total = d.total; }
                    } catch (e) { /* mantém o último valor */ }
                },

                async search() {
                    if (this.query.length < 2) { this.results = []; return; }
                    this.buscando = true;
                    try {
                        const res = await fetch(`{{ route('admin.eventos.credenciamento.search', $evento->id) }}?q=${encodeURIComponent(this.query)}`, {
                            headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
                        });
                        this.results = res.ok ? (await res.json()).map((i) => ({ ...i, carregando: false })) : [];
                    } catch (e) { console.error(e); }
                    finally { this.buscando = false; }
                },

                // Enter: se há um único resultado pendente, faz o check-in
                entrar() {
                    const pendentes = this.results.filter((i) => !i.checkin_at);
                    if (this.results.length === 1 && pendentes.length === 1) this.doCheckin(pendentes[0]);
                },

                // O servidor é quem decide se o check-in é novo: a tela nunca conta sozinha (evita contar duas vezes
                // com duplo clique, resultado desatualizado ou outro operador já ter credenciado a pessoa).
                async doCheckin(item) {
                    if (item.carregando) return;
                    item.carregando = true;
                    try {
                        const res = await fetch(`/admin/eventos/{{ $evento->id }}/credenciamento/checkin/${item.participante_id}`, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        });
                        const data = await res.json();
                        if (data.success) {
                            item.checkin_at = data.checkin_at;
                            this.stats.credenciados = data.credenciados;
                            this.stats.total = data.total;
                            if (window.alerta) {
                                data.ja_realizado
                                    ? window.alerta('warning', data.message, { titulo: 'Já credenciado(a)' })
                                    : window.alerta('success', item.participante.nome_completo + ' credenciado(a) às ' + data.checkin_at + '.', { titulo: 'Check-in feito', ms: 3500 });
                            }
                        }
                    } catch (e) { alert('Erro no check-in.'); }
                    finally { item.carregando = false; }
                },

                async printBadge(item) {
                    const pId = item.participante_id || item.participante.id;
                    // Credencia primeiro (idempotente) e só então abre a etiqueta
                    if (!item.checkin_at) { await this.doCheckin(item); }
                    window.open(`/admin/eventos/{{ $evento->id }}/credenciamento/etiqueta/${pId}`, '_blank', 'width=400,height=600');
                    setTimeout(() => this.atualizarStats(), 1500);
                }
            }
        }
    </script>
</x-app-layout>
