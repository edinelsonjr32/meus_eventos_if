<x-app-layout>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.eventos.show', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
            <div><h3 class="fw-bold m-0">Voluntários</h3><small class="text-secondary">{{ $evento->titulo }}</small></div>
        </div>
        <a href="{{ route('admin.propostas.index', $evento->id) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-chalkboard-user"></i> Propostas de minicursos</a>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div> @endif

    <div x-data="{ aba: '{{ session('aba', $errors->any() ? 'atividades' : 'inscritos') }}' }">
        <ul class="nav nav-pills mb-3 gap-1">
            <li class="nav-item"><button type="button" class="nav-link" :class="{ 'active': aba === 'inscritos' }" @click="aba = 'inscritos'" style="color:inherit"><i class="fa-solid fa-users me-1"></i> Inscritos ({{ $voluntarios->count() }})</button></li>
            <li class="nav-item"><button type="button" class="nav-link" :class="{ 'active': aba === 'atividades' }" @click="aba = 'atividades'" style="color:inherit"><i class="fa-solid fa-list-check me-1"></i> Atividades dos voluntários ({{ $demandas->count() }})</button></li>
            <li class="nav-item"><button type="button" class="nav-link" :class="{ 'active': aba === 'chamadas' }" @click="aba = 'chamadas'" style="color:inherit"><i class="fa-solid fa-bullhorn me-1"></i> Chamadas ({{ $chamadas->count() }})</button></li>
        </ul>

        {{-- ===== Chamadas ===== --}}
        <div x-show="aba === 'chamadas'" x-cloak>
            <div class="glass-card" x-data="{ nova: {{ $chamadas->isEmpty() ? 'true' : 'false' }} }">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-success m-0">Chamadas de voluntários</h6>
                    <button type="button" class="btn btn-sm btn-primary-custom" @click="nova = !nova"><i class="fa-solid fa-plus"></i> Nova chamada</button>
                </div>
                <div x-show="nova" x-cloak class="mb-4 p-3 rounded-3 bg-light border">@include('admin.voluntarios._form-chamada', ['acao' => route('admin.voluntarios.chamadas.store', $evento->id), 'metodo' => null, 'chamada' => null])</div>

                @forelse($chamadas as $c)
                    <div class="border rounded-3 p-3 mb-2" x-data="{ editar: false }">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div class="min-w-0"><div class="fw-bold texto-quebra">{{ $c->titulo }} <x-badge tipo="chamada" :valor="$c->situacao()" /></div>
                                <div class="small text-secondary">{{ $c->inicio ? $c->inicio->format('d/m/Y H:i') : 'já aberta' }} → {{ $c->fim ? $c->fim->format('d/m/Y H:i') : 'sem prazo' }} · {{ $c->vagas ? $c->vagas . ' vaga(s)' : 'sem limite de vagas' }} · {{ $c->voluntarios_count }} inscrito(s)</div></div>
                            <div class="d-flex gap-2 align-items-start">
                                <button type="button" class="btn btn-sm btn-light border" @click="editar = !editar"><i class="fa-solid fa-pen"></i> Editar</button>
                                <form action="{{ route('admin.voluntarios.chamadas.destroy', [$evento->id, $c->id]) }}" method="POST" onsubmit="return confirm('Excluir esta chamada?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light border"><i class="fa-solid fa-trash"></i></button></form>
                            </div>
                        </div>
                        <div x-show="editar" x-cloak class="mt-3 p-3 rounded-3 bg-light border">@include('admin.voluntarios._form-chamada', ['acao' => route('admin.voluntarios.chamadas.update', [$evento->id, $c->id]), 'metodo' => 'PUT', 'chamada' => $c])</div>
                    </div>
                @empty
                    <p class="small text-muted mb-0">Nenhuma chamada criada ainda.</p>
                @endforelse
            </div>
        </div>

        {{-- ===== Atividades (demandas) ===== --}}
        <div x-show="aba === 'atividades'" x-cloak>
            <div class="glass-card" x-data="{ nova: {{ $demandas->isEmpty() ? 'true' : 'false' }} }">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-success m-0">Atividades em que voluntários atuam</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <form action="{{ route('admin.voluntarios.demandas.gerar', $evento->id) }}" method="POST">@csrf<button class="btn btn-sm btn-light border" title="Cria a demanda de voluntários das atividades do evento que ainda não têm uma"><i class="fa-solid fa-wand-magic-sparkles"></i> Gerar das atividades do evento</button></form>
                        <button type="button" class="btn btn-sm btn-primary-custom" @click="nova = !nova"><i class="fa-solid fa-plus"></i> Nova atividade</button>
                    </div>
                </div>
                <div x-show="nova" x-cloak class="mb-4 p-3 rounded-3 bg-light border">@include('admin.voluntarios._form-demanda', ['acao' => route('admin.voluntarios.demandas.store', $evento->id), 'metodo' => null, 'demanda' => null])</div>

                @forelse($demandas as $d)
                    <div class="border rounded-3 p-3 mb-2" x-data="{ editar: false }">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="fw-bold texto-quebra">{{ $d->titulo }}</div>
                                <div class="small text-secondary">
                                    @if($d->data_inicio){{ $d->data_inicio->format('d/m H:i') }}@if($d->data_fim) a {{ $d->data_fim->format('H:i') }}@endif · @endif
                                    @if($d->local){{ $d->local }} · @endif{{ rtrim(rtrim(number_format($d->carga_horaria, 1, ',', ''), '0'), ',') }} h · {{ $d->voluntarios_count }}{{ $d->vagas ? '/' . $d->vagas : '' }} voluntário(s){{ $d->vagas && $d->voluntarios_count >= $d->vagas ? ' · completa' : '' }}
                                </div>
                                @if($d->responsavel_nome)<div class="small"><i class="fa-solid fa-user-tie me-1 text-success"></i> Responsável: <strong>{{ $d->responsavel_nome }}</strong>@if($d->responsavel_contato) · {{ $d->responsavel_contato }}@endif</div>@endif
                                @include('admin.voluntarios._vinculos', ['d' => $d, 'evento' => $evento, 'aprovados' => $aprovados])
                            </div>
                            <div class="d-flex gap-2 align-items-start">
                                <button type="button" class="btn btn-sm btn-light border" @click="editar = !editar"><i class="fa-solid fa-pen"></i> Editar</button>
                                <form action="{{ route('admin.voluntarios.demandas.destroy', [$evento->id, $d->id]) }}" method="POST" onsubmit="return confirm('Excluir esta atividade? Os voluntários serão desvinculados.')">@csrf @method('DELETE')<button class="btn btn-sm btn-light border"><i class="fa-solid fa-trash"></i></button></form>
                            </div>
                        </div>
                        <div x-show="editar" x-cloak class="mt-3 p-3 rounded-3 bg-light border">@include('admin.voluntarios._form-demanda', ['acao' => route('admin.voluntarios.demandas.update', [$evento->id, $d->id]), 'metodo' => 'PUT', 'demanda' => $d])</div>
                    </div>
                @empty
                    <p class="small text-muted mb-0">As atividades do evento ganham aqui uma demanda de apoio automaticamente. Cadastre também as demais (credenciamento, apoio a minicursos, recepção...) e depois vincule os voluntários aprovados.</p>
                @endforelse
            </div>
        </div>

        {{-- ===== Inscritos ===== --}}
        <div x-show="aba === 'inscritos'">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="{{ route('admin.voluntarios.index', $evento->id) }}" class="text-decoration-none"><span class="sb {{ $status ? 'sb-externo' : 'sb-ok' }}">Todos · {{ $contagem->sum() }}</span></a>
                @foreach(\App\Models\Voluntario::STATUS as $chave => $rotulo)
                    <a href="{{ route('admin.voluntarios.index', [$evento->id, 'status' => $chave]) }}" class="text-decoration-none"><x-badge tipo="voluntario" :valor="$chave" :extra="$contagem->get($chave, 0)" /></a>
                @endforeach
                <form method="GET" class="ms-auto d-flex gap-2">
                    @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nome ou e-mail">
                    <button class="btn btn-sm btn-light border"><i class="fa-solid fa-magnifying-glass"></i></button>
                </form>
            </div>

            @forelse($voluntarios as $vol)
                @php $equipe = $certificados->get($vol->participante_id); @endphp
                <div class="glass-card mb-3" id="vol-{{ $vol->id }}" x-data="{ aberto: {{ session('abrir') == $vol->id ? 'true' : 'false' }} }">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div class="min-w-0">
                            <div class="fw-bold texto-quebra">{{ $vol->usuario->name }} <x-badge tipo="voluntario" :valor="$vol->status" /></div>
                            <div class="small text-secondary">{{ $vol->usuario->email }}@if($vol->telefone) · {{ $vol->telefone }}@endif @if($vol->participante) · CPF {{ $vol->participante->cpf }}@endif · {{ $vol->chamada->titulo }}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-light border" @click="aberto = !aberto"><i class="fa-solid" :class="aberto ? 'fa-chevron-up' : 'fa-chevron-down'"></i> Detalhes</button>
                    </div>

                    <div x-show="aberto" x-cloak class="mt-3">
                        <div class="row g-3 small">
                            <div class="col-md-4"><div class="fw-bold text-secondary">Motivação</div><div class="texto-quebra">{{ $vol->motivacao ?: '—' }}</div></div>
                            <div class="col-md-4"><div class="fw-bold text-secondary">Disponibilidade</div><div class="texto-quebra">{{ $vol->disponibilidadeResumo() ?: '—' }}</div></div>
                            <div class="col-md-4"><div class="fw-bold text-secondary">Habilidades</div><div class="texto-quebra">{{ $vol->habilidades ?: '—' }}</div></div>
                        </div>
                        <hr>
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <form action="{{ route('admin.voluntarios.decisao', [$evento->id, $vol->id]) }}" method="POST">@csrf
                                    <label class="small fw-bold">Situação</label>
                                    <select name="status" class="form-select form-select-sm mb-2">
                                        @foreach(['aprovado' => 'Aprovar', 'lista_espera' => 'Lista de espera', 'reprovado' => 'Não selecionar', 'inscrito' => 'Voltar para análise'] as $v => $r)<option value="{{ $v }}" @selected($vol->status === $v)>{{ $r }}</option>@endforeach
                                    </select>
                                    <textarea name="parecer" rows="2" class="form-control form-control-sm mb-2" placeholder="Mensagem ao voluntário (opcional)">{{ $vol->parecer }}</textarea>
                                    <button class="btn btn-sm btn-primary-custom w-100">Salvar situação</button>
                                </form>
                            </div>
                            <div class="col-lg-7">
                                @if($vol->status === 'aprovado')
                                    <div class="small fw-bold mb-1">Atividades vinculadas</div>
                                    @forelse($vol->demandas as $d)
                                        <div class="d-flex justify-content-between align-items-center border rounded-3 px-2 py-1 mb-1 small">
                                            <span>{{ $d->titulo }}@if($d->data_inicio) <span class="text-muted">· {{ $d->data_inicio->format('d/m H:i') }}</span>@endif</span>
                                            <form action="{{ route('admin.voluntarios.desalocar', [$evento->id, $vol->id, $d->id]) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-sm btn-light border py-0" title="Remover"><i class="fa-solid fa-xmark"></i></button></form>
                                        </div>
                                    @empty
                                        <div class="small text-muted mb-1">Nenhuma atividade ainda.</div>
                                    @endforelse
                                    @if($demandas->isNotEmpty())
                                        <form action="{{ route('admin.voluntarios.alocar', [$evento->id, $vol->id]) }}" method="POST" class="d-flex flex-wrap gap-2 mt-2">@csrf
                                            <select name="demanda_id" class="form-select form-select-sm" style="max-width:260px" required>
                                                <option value="">Vincular a uma atividade…</option>
                                                @foreach($demandas->whereNotIn('id', $vol->demandas->pluck('id')) as $d)<option value="{{ $d->id }}">{{ $d->titulo }}{{ $d->data_inicio ? ' (' . $d->data_inicio->format('d/m H:i') . ')' : '' }}{{ $vol->faltaDisponibilidade($d) ? ' ⚠ fora da disponibilidade' : '' }}</option>@endforeach
                                            </select>
                                            <div class="form-check d-flex align-items-center"><input class="form-check-input me-1" type="checkbox" name="ignorar_conflito" value="1" id="ci{{ $vol->id }}"><label class="form-check-label small" for="ci{{ $vol->id }}">ignorar conflito de horário/disponibilidade</label></div>
                                            <button class="btn btn-sm btn-outline-success fw-bold"><i class="fa-solid fa-link"></i> Vincular</button>
                                        </form>
                                    @endif

                                    <div class="mt-3 pt-3 border-top">
                                        @if($equipe && $equipe->certificadoEmitido())
                                            <span class="sb sb-ok"><i class="fa-solid fa-award"></i> Certificado emitido · {{ $equipe->carga_horaria_trabalho }} h</span>
                                            <a href="{{ route('admin.equipe.certificado', $equipe->id) }}" target="_blank" class="btn btn-sm btn-light border"><i class="fa-solid fa-file-pdf"></i> Abrir</a>
                                        @endif
                                        <form action="{{ route('admin.voluntarios.certificado', [$evento->id, $vol->id]) }}" method="POST" class="d-flex flex-wrap gap-2 align-items-center mt-2">@csrf
                                            <input type="number" name="carga_horaria" min="1" class="form-control form-control-sm" style="width:130px" placeholder="Horas ({{ (int) ceil($vol->cargaHoraria()) ?: 'auto' }})">
                                            <button class="btn btn-sm btn-primary-custom"><i class="fa-solid fa-award"></i> {{ $equipe && $equipe->certificadoEmitido() ? 'Atualizar certificado' : 'Gerar certificado de voluntário' }}</button>
                                        </form>
                                        <div class="form-text">Sem horas informadas, usa a soma das atividades vinculadas.</div>
                                    </div>
                                @else
                                    <div class="small text-muted">Aprove o voluntário para vinculá-lo a atividades e gerar o certificado.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="glass-card text-center text-muted py-5">Nenhum voluntário {{ $status ? 'com esta situação' : 'inscrito ainda' }}.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
