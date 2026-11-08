<x-app-layout>
    <div class="row justify-content-center" x-data="listaCredenciamento({ credenciados: {{ $credenciados }}, pendentes: {{ $pendentes }}, total: {{ $total }} })">
        <div class="col-xl-11">

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('admin.eventos.credenciamento.index', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
                    <div>
                        <h3 class="fw-bold m-0">Lista de inscritos</h3>
                        <small class="text-secondary">{{ $evento->titulo }} · status do credenciamento de cada inscrito</small>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.eventos.credenciamento.index', $evento->id) }}" class="btn btn-success fw-bold"><i class="fa-solid fa-id-badge"></i> Recepção (check-in)</a>
                    <a href="{{ route('admin.eventos.credenciamento.lista.exportar', array_filter(['evento' => $evento->id, 'status' => $status, 'q' => $busca])) }}" class="btn btn-outline-success fw-bold"><i class="fa-solid fa-file-csv"></i> Exportar CSV</a>
                </div>
            </div>

            {{-- Resumo (os números acompanham os check-ins feitos nesta tela) --}}
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3"><div class="glass-card bg-white p-3"><div class="fs-4 fw-bold lh-1" x-text="total">{{ $total }}</div><small class="text-secondary">Inscritos</small></div></div>
                <div class="col-6 col-md-3"><div class="glass-card bg-white p-3"><div class="fs-4 fw-bold lh-1 text-success" x-text="credenciados">{{ $credenciados }}</div><small class="text-secondary">Check-in feito</small></div></div>
                <div class="col-6 col-md-3"><div class="glass-card bg-white p-3"><div class="fs-4 fw-bold lh-1" style="color:#b45309;" x-text="pendentes">{{ $pendentes }}</div><small class="text-secondary">Pendentes</small></div></div>
                <div class="col-6 col-md-3"><div class="glass-card bg-white p-3">
                    <div class="fs-4 fw-bold lh-1"><span x-text="total ? Math.round(credenciados * 100 / total) : 0">{{ $total ? round($credenciados * 100 / $total) : 0 }}</span>%</div><small class="text-secondary">Comparecimento</small>
                    <div class="progress mt-1" style="height:5px;"><div class="progress-bar bg-success" :style="`width:${total ? credenciados * 100 / total : 0}%`"></div></div></div></div>
            </div>

            <form method="GET" class="glass-card bg-white mb-3">
                <div class="d-flex flex-wrap gap-2 align-items-end">
                    <div class="btn-group" role="group" aria-label="Status">
                        @foreach([['', 'Todos', 'fa-users'], ['checkin', 'Check-in feito', 'fa-circle-check'], ['pendente', 'Pendentes', 'fa-hourglass-half']] as [$v, $rot, $ic])
                            <button type="submit" name="status" value="{{ $v }}" class="btn {{ ($status ?? '') === $v ? 'btn-success' : 'btn-outline-secondary' }} fw-bold"><i class="fa-solid {{ $ic }}"></i> {{ $rot }}</button>
                        @endforeach
                    </div>
                    <div class="flex-fill" style="min-width:220px;">
                        <label class="small fw-bold text-secondary">Buscar</label>
                        <input type="search" name="q" value="{{ $busca }}" class="form-control form-control-sm" placeholder="Nome, CPF, e-mail ou matrícula">
                    </div>
                    <button class="btn btn-sm btn-dark"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
                    <a href="{{ route('admin.eventos.credenciamento.lista', $evento->id) }}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-eraser"></i> Limpar</a>
                </div>
            </form>

            <div class="glass-card bg-white p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="bg-light">
                            <tr><th class="ps-3">Participante</th><th>Vínculo</th><th>Curso / Turma</th><th>Inscrito em</th><th>Status</th><th class="text-end pe-3">Ações</th></tr>
                        </thead>
                        <tbody>
                        @forelse($inscricoes as $i)
                            @php $p = $i->participante; @endphp
                            <tr x-data="{ feito: {{ $i->checkin_at ? 'true' : 'false' }}, hora: '{{ $i->checkin_at?->format('d/m H:i') }}', carregando: false }" :class="feito ? 'table-success' : ''">
                                <td class="ps-3">
                                    <div class="fw-bold">{{ $p->nome_completo }}</div>
                                    <div class="text-muted">{{ $p->cpf }} · {{ $p->email }}</div>
                                </td>
                                <td>{{ ucfirst($p->tipo_vinculo) }}@if($p->matricula)<div class="text-muted">{{ $p->matricula }}</div>@endif</td>
                                <td>{{ $p->turma ? ($p->turma->curso->nome ?? '') : '—' }}@if($p->turma)<div class="text-muted">{{ $p->turma->nome_completo }}</div>@endif</td>
                                <td class="text-nowrap">{{ $i->data_inscricao ? \Illuminate\Support\Carbon::parse($i->data_inscricao)->format('d/m/Y H:i') : '—' }}</td>
                                <td class="text-nowrap">
                                    <template x-if="feito"><span><x-badge tipo="checkin" valor="feito" /> <span class="text-muted" x-text="hora"></span></span></template>
                                    <template x-if="!feito"><span><x-badge tipo="checkin" valor="pendente" /></span></template>
                                </td>
                                <td class="text-end pe-3 text-nowrap">
                                    <button type="button" class="btn btn-sm btn-success" x-show="!feito" :disabled="carregando"
                                            @click="carregando = true; fazerCheckin({{ $p->id }}).then((d) => { if (d) { feito = true; hora = d.checkin_at; } }).finally(() => carregando = false)">
                                        <i class="fa-solid fa-check"></i> Fazer check-in
                                    </button>
                                    <a href="{{ route('admin.eventos.credenciamento.etiqueta', [$evento->id, $p->id]) }}" target="_blank" class="btn btn-sm btn-outline-secondary" @click="feito = true; if (!hora) hora = 'agora'; setTimeout(() => atualizar(), 1500)"><i class="fa-solid fa-print"></i> Etiqueta</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5"><i class="fa-solid fa-users-slash fs-3 d-block mb-2"></i> Nenhum inscrito {{ $status === 'checkin' ? 'com check-in' : ($status === 'pendente' ? 'pendente' : '') }} {{ $busca ? 'para esta busca' : '' }}.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-3">{{ $inscricoes->links() }}</div>
        </div>
    </div>

    <script>
        function listaCredenciamento(inicial) {
            return {
                ...inicial,
                async atualizar() {
                    try {
                        const r = await fetch(`{{ route('admin.eventos.credenciamento.stats', $evento->id) }}`, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                        if (r.ok) { const d = await r.json(); this.credenciados = d.credenciados; this.pendentes = d.pendentes; this.total = d.total; }
                    } catch (e) { /* mantém */ }
                },
                // Faz o check-in no servidor (idempotente). Os contadores vêm sempre do servidor.
                async fazerCheckin(participanteId) {
                    try {
                        const res = await fetch(`/admin/eventos/{{ $evento->id }}/credenciamento/checkin/${participanteId}`, {
                            method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }, credentials: 'same-origin',
                        });
                        const d = await res.json();
                        if (!d.success) return null;
                        this.credenciados = d.credenciados; this.pendentes = d.pendentes; this.total = d.total;
                        if (d.ja_realizado && window.alerta) window.alerta('warning', d.message, { titulo: 'Já credenciado(a)' });
                        return d;
                    } catch (e) { alert('Erro no check-in.'); return null; }
                },
            };
        }
    </script>
</x-app-layout>
