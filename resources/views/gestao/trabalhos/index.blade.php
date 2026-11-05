<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-11">

            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                <div>
                    <h3 class="fw-bold m-0">Trabalhos submetidos</h3>
                    <small class="text-secondary">{{ $evento->titulo }}</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('gestao.trabalhos.exportar', array_merge(['evento' => $evento->id, 'formato' => 'xlsx'], request()->only(['status', 'tipo', 'area', 'q']))) }}" class="btn btn-outline-success fw-bold">
                        <i class="fa-solid fa-file-excel me-1"></i> Excel
                    </a>
                    <a href="{{ route('gestao.trabalhos.exportar', array_merge(['evento' => $evento->id, 'formato' => 'csv'], request()->only(['status', 'tipo', 'area', 'q']))) }}" class="btn btn-outline-secondary fw-bold">
                        <i class="fa-solid fa-file-csv me-1"></i> CSV
                    </a>
                    <a href="{{ route('gestao.trabalhos.config', $evento) }}" class="btn btn-outline-secondary fw-bold">
                        <i class="fa-solid fa-sliders me-1"></i> Configurar chamada
                    </a>
                </div>
            </div>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
            @if($errors->any())     <div class="alert alert-danger small">{{ $errors->first() }}</div> @endif
            @if(session('erros_lote'))
                <div class="alert alert-warning small"><ul class="mb-0 ps-3">@foreach(session('erros_lote') as $erro) <li>{{ $erro }}</li> @endforeach</ul></div>
            @endif

            @if(!$config || !$config->estaAberta())
                <div class="alert alert-warning small">A chamada está <strong>fechada</strong> (inativa ou fora do período). Ajuste em "Configurar chamada".</div>
            @endif

            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach($contagem as $status => $total)
                    <a href="{{ request()->fullUrlWithQuery(['status' => $status]) }}" class="badge bg-secondary bg-opacity-10 text-dark text-decoration-none p-2">
                        {{ \App\Models\Trabalho::STATUS[$status] ?? $status }}: {{ $total }}
                    </a>
                @endforeach
            </div>

            <form method="GET" class="glass-card bg-white mb-3">
                <div class="row g-2">
                    <div class="col-md-4"><input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Título, protocolo, autor ou CPF"></div>
                    <div class="col-md-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Todos os status</option>
                            @foreach(\App\Models\Trabalho::STATUS as $v => $r) <option value="{{ $v }}" @selected(request('status') === $v)>{{ $r }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="tipo" class="form-select form-select-sm">
                            <option value="">Todos os tipos</option>
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
                        <button class="btn btn-sm btn-dark flex-fill">Filtrar</button>
                        <a href="{{ route('gestao.trabalhos.index', $evento) }}" class="btn btn-sm btn-light">Limpar</a>
                    </div>
                </div>
            </form>

            <form id="lote" action="{{ route('gestao.trabalhos.lote', $evento) }}" method="POST" class="glass-card bg-white mb-3">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="small fw-bold text-secondary">Ação nos selecionados</label>
                        <select name="acao" class="form-select form-select-sm">
                            <option value="triagem">Iniciar triagem</option>
                            <option value="atribuir">Atribuir avaliadores</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="small fw-bold text-secondary">Avaliadores <span class="fw-normal">(somente ao atribuir)</span></label>
                        <select name="avaliadores[]" class="form-select form-select-sm" multiple size="3">
                            @foreach($avaliadores as $u) <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->carga }} em aberto)</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small fw-bold text-secondary">Prazo</label>
                        <input type="date" name="prazo" class="form-control form-control-sm" min="{{ now()->addDay()->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-dark w-100 fw-bold">Aplicar</button>
                    </div>
                </div>
            </form>

            <div class="glass-card bg-white p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="bg-light">
                            <tr><th class="ps-3"><input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.sel-trabalho').forEach(c => c.checked = this.checked)"></th><th>Protocolo</th><th>Título / Autores</th><th>Tipo</th><th>Área</th><th>Status</th><th>Aval.</th><th>Enviado em</th><th></th></tr>
                        </thead>
                        <tbody>
                        @forelse($trabalhos as $t)
                            <tr>
                                <td class="ps-3"><input type="checkbox" form="lote" name="trabalhos[]" value="{{ $t->id }}" class="form-check-input sel-trabalho"></td>
                                <td class="fw-bold">{{ $t->codigo }}</td>
                                <td>
                                    <div class="fw-bold">{{ \Illuminate\Support\Str::limit($t->titulo, 70) }}</div>
                                    <span class="text-muted">{{ $t->autores->pluck('nome_completo')->take(3)->implode(', ') }}{{ $t->autores->count() > 3 ? ' +' . ($t->autores->count() - 3) : '' }}</span>
                                </td>
                                <td>{{ \App\Models\TrabalhoConfig::TIPOS[$t->tipo_trabalho] ?? '—' }}</td>
                                <td>{{ $t->area->nome ?? '—' }}</td>
                                <td><span class="badge bg-secondary bg-opacity-10 text-dark">{{ $t->statusRotulo() }}</span></td>
                                <td>{{ $t->avaliacoes->where('status', '!=', 'recusada')->count() }}/{{ $config?->avaliadores_por_trabalho ?? 2 }}</td>
                                <td>{{ $t->submetido_em?->format('d/m/Y H:i') }}</td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('gestao.trabalhos.show', $t) }}" class="btn btn-sm btn-light">Abrir</a>
                                    @if($t->arquivoAtual) <a href="{{ route('trabalhos.arquivo', $t) }}" target="_blank" class="btn btn-sm btn-outline-danger" title="PDF"><i class="fa-solid fa-file-pdf"></i></a> @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-5">Nenhum trabalho encontrado.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-3">{{ $trabalhos->links() }}</div>
        </div>
    </div>
</x-app-layout>
