<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-7 col-lg-9">
            <div class="d-flex align-items-center gap-3 mb-4">
                <a href="{{ route('admin.eventos.show', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
                <div>
                    <h3 class="fw-bold m-0"><i class="fa-solid fa-pen text-success me-1"></i> Editar atividade</h3>
                    <small class="text-secondary">{{ $evento->titulo }}</small>
                </div>
            </div>

            @if($errors->any()) <div class="alert alert-danger small"><i class="fa-solid fa-circle-exclamation me-1"></i> {{ $errors->first() }}</div> @endif

            <form method="POST" action="{{ route('admin.atividades.update', $atividade->id) }}" data-validar class="glass-card bg-white">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-12">
                        <label class="small fw-bold text-secondary" for="titulo">Título *</label>
                        <input id="titulo" name="titulo" class="form-control" maxlength="255" required value="{{ old('titulo', $atividade->titulo) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-secondary" for="tipo">Tipo *</label>
                        <select id="tipo" name="tipo" class="form-select" required>
                            @foreach($tipos as $k => $r) <option value="{{ $k }}" @selected(old('tipo', $atividade->tipo) === $k)>{{ $r }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-secondary" for="inicio">Data e hora de início *</label>
                        <input id="inicio" type="datetime-local" name="data_inicio" class="form-control" required value="{{ old('data_inicio', $atividade->data_inicio?->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-secondary" for="resp">Responsável / palestrante</label>
                        <input id="resp" name="responsavel" class="form-control" maxlength="255" list="equipe-nomes" value="{{ old('responsavel', $atividade->responsavel) }}">
                        <datalist id="equipe-nomes">@foreach($equipe as $nome)<option value="{{ $nome }}">@endforeach</datalist>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-secondary" for="local">Local da atividade</label>
                        <select id="local" name="local_id" class="form-select">
                            <option value="">— sem local definido —</option>
                            @foreach($locais as $lc)<option value="{{ $lc->id }}" @selected((int) old('local_id', $atividade->local_id) === $lc->id)>{{ $lc->rotulo() }}{{ $lc->capacidade ? ' · ' . $lc->capacidade . ' pessoas' : '' }}{{ $lc->ativo ? '' : ' (inativo)' }}</option>@endforeach
                        </select>
                        <div class="form-text small"><a href="{{ route('admin.locais.index', $evento->id) }}" target="_blank">Gerenciar locais</a></div>
                        <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="ignorar_conflito" value="1" id="ic"><label class="form-check-label small" for="ic">Ignorar conflito de horário ou capacidade do local</label></div>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-secondary" for="ch">Carga horária (horas) *</label>
                        <input id="ch" type="number" name="carga_horaria" class="form-control" min="1" max="1000" required value="{{ old('carga_horaria', $atividade->carga_horaria) }}">
                        <div class="form-text small">Consta no certificado de presença. O horário de término é recalculado por ela.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-secondary" for="vagas">Vagas</label>
                        <input id="vagas" type="number" name="vagas" class="form-control" min="0" value="{{ old('vagas', $atividade->vagas) }}" placeholder="0 = ilimitado">
                        <div class="form-text small">{{ $inscritos }} inscrito(s) hoje.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-secondary" for="vn">Voluntários necessários</label>
                        <input id="vn" type="number" name="voluntarios_necessarios" class="form-control" min="0" max="200" value="{{ old('voluntarios_necessarios', $atividade->voluntarios_necessarios) }}" placeholder="0 = sem limite">
                        <div class="form-text small">Capacidade da demanda de voluntários desta atividade; vários voluntários podem ser vinculados.</div>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('admin.eventos.show', $evento->id) }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button class="btn btn-success fw-bold px-4"><i class="fa-regular fa-floppy-disk"></i> Salvar alterações</button>
                </div>
            </form>

            {{-- Voluntários desta atividade --}}
            <div class="glass-card bg-white mt-4">
                <h6 class="fw-bold text-success mb-1"><i class="fa-solid fa-hand-holding-heart me-1"></i> Voluntários da atividade</h6>
                @if($demanda)
                    <p class="small text-muted mb-2">Vincule, remova ou troque os voluntários que apoiam esta atividade. Cada vínculo é conferido contra vagas, horário e a disponibilidade informada.</p>
                    @include('admin.voluntarios._vinculos', ['d' => $demanda, 'evento' => $evento, 'aprovados' => $aprovados])
                    <div class="small mt-2"><a href="{{ route('admin.voluntarios.index', $evento->id) }}">Gerenciar inscrições e demandas de voluntários</a></div>
                @else
                    <p class="small text-muted mb-2">Esta atividade ainda não tem demanda de voluntários.</p>
                    <form action="{{ route('admin.voluntarios.demandas.gerar', $evento->id) }}" method="POST">@csrf<button class="btn btn-sm btn-outline-success fw-bold"><i class="fa-solid fa-wand-magic-sparkles"></i> Criar demanda de voluntários</button></form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
