@php
    $fmtData = fn ($d) => $d ? $d->format('d/m H:i') : null;
    $pendentes = $trabalhos->filter(fn ($t) => $t->agendaPendenteDeAviso())->count();
    $semHorario = $trabalhos->filter(fn ($t) => !$t->agendado())->count();
@endphp
<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-11">

            <div class="d-flex align-items-center gap-3 mb-4">
                <a href="{{ route('gestao.trabalhos.index', $evento) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
                <div class="flex-grow-1">
                    <h3 class="fw-bold m-0">Agenda das apresentações orais</h3>
                    <small class="text-secondary">{{ $evento->titulo }} · salas virtuais, horários e aviso aos autores e avaliadores</small>
                </div>
                <a href="{{ route('gestao.trabalhos.apresentacoes.index', $evento) }}" class="btn btn-outline-secondary btn-sm fw-bold"><i class="fa-solid fa-clipboard-check me-1"></i> Avaliação das apresentações</a>
            </div>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
            @if($errors->any()) <div class="alert alert-danger"><ul class="mb-0 small">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div> @endif

            <div class="alert alert-light border small">
                <strong>Como funciona:</strong> cadastre as salas (o mesmo link serve para várias apresentações), marque os trabalhos e programe-os em sequência (dia, hora, duração e intervalo).
                Depois envie o aviso: autores e avaliadores recebem sala e horário por e-mail e ganham o botão <strong>Entrar na sala</strong> na plataforma. Um lembrete automático sai 30 minutos antes.
            </div>

            {{-- ===== Salas ===== --}}
            <div class="glass-card bg-white mb-4" x-data="{ nova: {{ $salas->isEmpty() || old('_form') === 'nova' ? 'true' : 'false' }} }">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-success m-0"><i class="fa-solid fa-video me-1"></i> Salas virtuais ({{ $salas->count() }})</h6>
                    <button type="button" class="btn btn-sm btn-primary-custom" @click="nova = !nova"><i class="fa-solid fa-plus"></i> Nova sala</button>
                </div>

                <div x-show="nova" x-cloak class="p-3 rounded-3 bg-light border mb-3">
                    <form action="{{ route('gestao.trabalhos.agenda.salas.store', $evento) }}" method="POST" class="row g-2">
                        @csrf <input type="hidden" name="_form" value="nova">
                        <div class="col-md-3"><label class="small fw-bold">Nome *</label><input name="nome" class="form-control form-control-sm" maxlength="120" placeholder="Ex.: Sala 1 — Meet" value="{{ old('_form') === 'nova' ? old('nome') : '' }}" required></div>
                        <div class="col-md-5"><label class="small fw-bold">Link da videoconferência *</label><input type="url" name="link" class="form-control form-control-sm" maxlength="500" placeholder="https://meet.google.com/abc-defg-hij" value="{{ old('_form') === 'nova' ? old('link') : '' }}" required></div>
                        <div class="col-md-4"><label class="small fw-bold">Instruções (opcional)</label><input name="instrucoes" class="form-control form-control-sm" maxlength="2000" placeholder="Ex.: entre 10 min antes; câmera ligada" value="{{ old('_form') === 'nova' ? old('instrucoes') : '' }}"></div>
                        <div class="col-12 text-end"><button class="btn btn-primary-custom btn-sm fw-bold"><i class="fa-solid fa-check"></i> Cadastrar sala</button></div>
                    </form>
                </div>

                @forelse($salas as $s)
                    <div class="border rounded-3 p-3 mb-2" x-data="{ editar: {{ old('_form') === 'sala'.$s->id && $errors->any() ? 'true' : 'false' }} }">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="fw-bold">{{ $s->nome }} @unless($s->ativo)<span class="badge bg-secondary">inativa</span>@endunless <span class="badge bg-light text-dark border">{{ $s->trabalhos_count }} apresentação(ões)</span></div>
                                <div class="small text-secondary" style="overflow-wrap:anywhere;"><i class="fa-solid fa-link me-1"></i>{{ $s->link }}</div>
                                @if($s->instrucoes)<div class="small text-muted">{{ $s->instrucoes }}</div>@endif
                            </div>
                            <div class="d-flex gap-2 align-items-start">
                                <button type="button" class="btn btn-sm btn-light border" @click="editar = !editar"><i class="fa-solid fa-pen"></i> Editar</button>
                                <form action="{{ route('gestao.trabalhos.agenda.salas.destroy', [$evento, $s]) }}" method="POST" onsubmit="return confirm('Excluir esta sala?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light border" title="Excluir"><i class="fa-solid fa-trash"></i></button></form>
                            </div>
                        </div>
                        <div x-show="editar" x-cloak class="mt-3 p-3 rounded-3 bg-light border">
                            <form action="{{ route('gestao.trabalhos.agenda.salas.update', [$evento, $s]) }}" method="POST" class="row g-2">
                                @csrf @method('PUT') <input type="hidden" name="_form" value="sala{{ $s->id }}">
                                @php $meu = old('_form') === 'sala'.$s->id; @endphp
                                <div class="col-md-3"><label class="small fw-bold">Nome *</label><input name="nome" class="form-control form-control-sm" maxlength="120" value="{{ $meu ? old('nome') : $s->nome }}" required></div>
                                <div class="col-md-5"><label class="small fw-bold">Link *</label><input type="url" name="link" class="form-control form-control-sm" maxlength="500" value="{{ $meu ? old('link') : $s->link }}" required></div>
                                <div class="col-md-4"><label class="small fw-bold">Instruções</label><input name="instrucoes" class="form-control form-control-sm" maxlength="2000" value="{{ $meu ? old('instrucoes') : $s->instrucoes }}"></div>
                                <div class="col-12 d-flex justify-content-between align-items-center">
                                    <div class="form-check form-switch mb-0"><input type="hidden" name="ativo" value="0"><input class="form-check-input" type="checkbox" role="switch" name="ativo" value="1" id="sa{{ $s->id }}" @checked($meu ? old('ativo') : $s->ativo)><label class="form-check-label small" for="sa{{ $s->id }}">Ativa (aparece na programação)</label></div>
                                    <button class="btn btn-primary-custom btn-sm fw-bold">Salvar sala</button>
                                </div>
                                <div class="col-12 small text-muted">Mudar o link ou o nome vale para todas as apresentações da sala. Se já houve aviso, reenvie para que todos recebam o novo link.</div>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="small text-muted mb-0">Cadastre as salas virtuais (Meet, Zoom, Teams). Um mesmo link atende a várias apresentações em horários diferentes.</p>
                @endforelse
            </div>

            {{-- ===== Programação ===== --}}
            <form id="prog" action="{{ route('gestao.trabalhos.agenda.programar', $evento) }}" method="POST" class="glass-card bg-white mb-4">
                @csrf
                <h6 class="fw-bold text-success mb-1"><i class="fa-regular fa-calendar-check me-1"></i> Programar em sequência</h6>
                <p class="small text-muted">Marque os trabalhos na tabela, escolha a sala e o horário da primeira apresentação: as demais seguem uma após a outra (ordem do protocolo), com a duração e o intervalo informados.</p>
                <div class="row g-2 align-items-end">
                    <div class="col-md-3"><label class="small fw-bold">Sala *</label>
                        <select name="sala_id" class="form-select form-select-sm" required>
                            <option value="">— escolha —</option>
                            @foreach($salas->where('ativo', true) as $s)<option value="{{ $s->id }}" @selected((int) old('sala_id') === $s->id)>{{ $s->nome }}</option>@endforeach
                        </select></div>
                    <div class="col-md-3"><label class="small fw-bold">Primeira apresentação *</label><input type="datetime-local" name="inicio" class="form-control form-control-sm" value="{{ old('inicio') }}" required></div>
                    <div class="col-md-2"><label class="small fw-bold">Duração (min) *</label><input type="number" name="duracao" min="1" max="480" class="form-control form-control-sm" value="{{ old('duracao', $duracaoPadrao) }}" required></div>
                    <div class="col-md-2"><label class="small fw-bold">Intervalo (min)</label><input type="number" name="intervalo" min="0" max="240" class="form-control form-control-sm" value="{{ old('intervalo', 5) }}"></div>
                    <div class="col-md-2"><button class="btn btn-primary-custom btn-sm fw-bold w-100" @disabled($salas->where('ativo', true)->isEmpty())><i class="fa-solid fa-wand-magic-sparkles me-1"></i> Programar marcados</button></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="ignorar_conflito" value="1" id="ign"><label class="form-check-label small" for="ign">Ignorar conflito de horário na sala</label></div></div>
                </div>
            </form>

            {{-- ===== Trabalhos ===== --}}
            <div class="glass-card bg-white">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h6 class="fw-bold text-success m-0">Apresentações ({{ $trabalhos->count() }}) <span class="text-muted fw-normal small">· {{ $semHorario }} sem horário · {{ $pendentes }} com aviso pendente</span></h6>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('gestao.trabalhos.agenda.index', [$evento, 'todas' => $todas ? 0 : 1]) }}" class="btn btn-sm btn-light border">{{ $todas ? 'Só orais' : 'Incluir pôsteres' }}</a>
                        <button type="submit" form="prog" formaction="{{ route('gestao.trabalhos.agenda.notificar', $evento) }}" class="btn btn-sm btn-outline-success fw-bold" onclick="return confirm('Enviar sala e horário aos autores e avaliadores dos trabalhos marcados?')"><i class="fa-solid fa-paper-plane me-1"></i> Avisar marcados</button>
                        <button type="submit" form="avisar-todos" class="btn btn-sm btn-success fw-bold" @disabled($pendentes === 0) onclick="return confirm('Enviar aviso a todas as apresentações com aviso pendente?')"><i class="fa-solid fa-bell me-1"></i> Avisar pendentes ({{ $pendentes }})</button>
                    </div>
                </div>
                <form id="avisar-todos" action="{{ route('gestao.trabalhos.agenda.notificar', $evento) }}" method="POST">@csrf</form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle small">
                        <thead><tr><th style="width:30px"><input type="checkbox" onclick="document.querySelectorAll('.sel-ag').forEach(c => c.checked = this.checked)" title="Marcar todos"></th><th>Trabalho</th><th>Sala</th><th>Horário</th><th>Aviso</th><th>Avaliadores</th><th></th></tr></thead>
                        <tbody>
                        @forelse($trabalhos as $t)
                            <tr>
                                <td><input type="checkbox" form="prog" class="sel-ag" name="trabalhos[]" value="{{ $t->id }}"></td>
                                <td>
                                    <a href="{{ route('gestao.trabalhos.show', $t) }}" class="fw-bold text-decoration-none">{{ $t->codigo }}</a>
                                    <span class="badge bg-light text-dark border">{{ \App\Models\Trabalho::MODALIDADES[$t->modalidade_apresentacao] ?? '—' }}</span>
                                    <div class="text-muted">{{ \Illuminate\Support\Str::limit($t->titulo, 70) }}</div>
                                </td>
                                <td>@if($t->sala)<i class="fa-solid fa-video text-success me-1"></i>{{ $t->sala->nome }}@else <span class="text-muted">—</span>@endif</td>
                                <td>
                                    @if($t->agendado())
                                        <strong>{{ $fmtData($t->apresentacao_inicio) }}</strong>@if($t->apresentacao_fim) – {{ $t->apresentacao_fim->format('H:i') }}@endif
                                        @if(in_array($t->id, $conflitos, true)) <span class="badge bg-danger" title="Duas apresentações na mesma sala ao mesmo tempo">conflito</span>@endif
                                    @else <span class="text-muted">Sem horário</span> @endif
                                </td>
                                <td>
                                    @if(!$t->agendado()) <span class="text-muted">—</span>
                                    @elseif($t->agendaPendenteDeAviso()) <span class="badge bg-warning text-dark">{{ $t->agenda_notificada_em ? 'alterado: reenviar' : 'não avisado' }}</span>
                                    @else <span class="badge bg-success-subtle text-success-emphasis border">avisado {{ $t->agenda_notificada_em->format('d/m H:i') }}</span> @endif
                                </td>
                                <td>{{ $t->avaliacoesApresentacao->where('status', '!=', 'recusada')->map(fn ($a) => $a->avaliador?->name)->filter()->implode(', ') ?: '—' }}</td>
                                <td class="text-end">
                                    @if($t->agendado())
                                        <form action="{{ route('gestao.trabalhos.agenda.limpar', [$evento, $t]) }}" method="POST" class="d-inline" onsubmit="return confirm('Remover sala e horário deste trabalho?')">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0" title="Remover horário"><i class="fa-solid fa-xmark"></i></button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Nenhum trabalho aprovado {{ $todas ? '' : 'na modalidade oral ' }}ainda.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
