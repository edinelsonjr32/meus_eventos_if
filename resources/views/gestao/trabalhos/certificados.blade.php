<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-11">

            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('gestao.trabalhos.index', $evento) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
                    <div>
                        <h3 class="fw-bold m-0">Apresentações e certificados</h3>
                        <small class="text-secondary">{{ $evento->titulo }} · {{ $apresentados }} de {{ $aprovados }} aprovado(s) apresentado(s)</small>
                    </div>
                </div>
                <a href="{{ route('gestao.trabalhos.config', $evento) }}" class="btn btn-outline-secondary fw-bold"><i class="fa-solid fa-sliders me-1"></i> Textos do certificado</a>
            </div>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
            @if($errors->any())     <div class="alert alert-danger small">{{ $errors->first() }}</div> @endif
            @if(session('erros_lote'))
                <div class="alert alert-warning small"><ul class="mb-0 ps-3">@foreach(session('erros_lote') as $erro) <li>{{ $erro }}</li> @endforeach</ul></div>
            @endif

            @if($config?->exige_apresentacao_para_certificado ?? true)
                <div class="alert alert-info small">Certificados só são emitidos para trabalhos <strong>aprovados e apresentados</strong> (configurável no evento).</div>
            @endif

            <form method="GET" class="glass-card bg-white mb-3">
                <div class="row g-2">
                    <div class="col-md-5"><input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Título, protocolo ou autor"></div>
                    <div class="col-md-3">
                        <select name="apresentado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="sim" @selected(request('apresentado') === 'sim')>Apresentados</option>
                            <option value="nao" @selected(request('apresentado') === 'nao')>Não apresentados</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-1"><button class="btn btn-sm btn-dark flex-fill">Filtrar</button><a href="{{ route('gestao.trabalhos.certificados.index', $evento) }}" class="btn btn-sm btn-light">Limpar</a></div>
                </div>
            </form>

            <form id="lote" action="{{ route('gestao.trabalhos.certificados.lote', $evento) }}" method="POST" class="glass-card bg-white mb-3">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="small fw-bold text-secondary">Ação nos selecionados</label>
                        <select name="acao" class="form-select form-select-sm">
                            <option value="apresentado">Marcar como apresentado</option>
                            <option value="nao_apresentado">Desmarcar apresentação</option>
                            <option value="sessao">Definir sessão</option>
                            <option value="emitir">Emitir certificados</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="small fw-bold text-secondary">Sessão <span class="fw-normal">(somente ao definir sessão)</span></label>
                        <select name="atividade_id" class="form-select form-select-sm">
                            <option value="">— sem sessão —</option>
                            @foreach($atividades as $at) <option value="{{ $at->id }}">{{ $at->titulo }} ({{ $at->data_inicio->format('d/m H:i') }})</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-5 d-flex gap-2 flex-wrap">
                        <button class="btn btn-sm btn-dark fw-bold">Aplicar</button>
                        <button name="acao" value="sincronizar" class="btn btn-sm btn-outline-secondary fw-bold" title="Confirma apresentações com base na frequência das sessões">Sincronizar com frequência</button>
                        <button name="acao" value="emitir_todos" class="btn btn-sm btn-outline-success fw-bold" onclick="return confirm('Emitir certificados de todos os trabalhos elegíveis e avisar os autores por e-mail?')">Emitir todos elegíveis</button>
                    </div>
                </div>
            </form>

            <div class="glass-card bg-white p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3"><input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.sel-trabalho').forEach(c => c.checked = this.checked)"></th>
                                <th>Protocolo</th><th>Título / Autores</th><th>Sessão</th><th>Apresentado</th><th>Certificados</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($trabalhos as $t)
                            <tr>
                                <td class="ps-3"><input type="checkbox" form="lote" name="trabalhos[]" value="{{ $t->id }}" class="form-check-input sel-trabalho"></td>
                                <td class="fw-bold">{{ $t->codigo }}</td>
                                <td>
                                    <div class="fw-bold">{{ \Illuminate\Support\Str::limit($t->titulo, 60) }}</div>
                                    <span class="text-muted">{{ $t->autores->pluck('nome_completo')->take(3)->implode(', ') }}</span>
                                </td>
                                <td>{{ $t->sessao->titulo ?? '—' }}</td>
                                <td><x-badge tipo="sim-nao" :valor="$t->apresentado ? 'apresentado' : 'nao_apresentado'" /></td>
                                <td>{{ $t->certificados->whereNull('revogado_em')->count() }}/{{ $t->autores->count() }}
                                    @if($t->motivo) <div class="text-muted">({{ $t->motivo }})</div> @endif</td>
                                <td class="text-end pe-3"><a href="{{ route('gestao.trabalhos.show', $t) }}" class="btn btn-sm btn-light">Abrir</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-5">Nenhum trabalho aprovado encontrado.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-3">{{ $trabalhos->links() }}</div>
        </div>
    </div>
</x-app-layout>
