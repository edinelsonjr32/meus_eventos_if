@php $d = $demanda; $id = $d?->id ?? 'n'; @endphp
<form action="{{ $acao }}" method="POST" class="row g-2">
    @csrf
    @if($metodo) @method($metodo) @endif
    <div class="col-md-6"><label class="small fw-bold">Atividade / demanda *</label><input type="text" name="titulo" class="form-control form-control-sm" maxlength="255" placeholder="Ex.: Credenciamento, apoio ao minicurso..." value="{{ old('titulo', $d?->titulo) }}" required></div>
    <div class="col-md-6"><label class="small fw-bold">Vinculada à atividade da programação</label>
        <select name="atividade_id" class="form-select form-select-sm"><option value="">— nenhuma —</option>
            @foreach($atividades as $at)<option value="{{ $at->id }}" @selected((int) old('atividade_id', $d?->atividade_id) === $at->id)>{{ $at->titulo }} ({{ $at->data_inicio->format('d/m H:i') }})</option>@endforeach
        </select></div>
    <div class="col-md-3"><label class="small fw-bold">Início</label><input type="datetime-local" name="data_inicio" class="form-control form-control-sm" value="{{ old('data_inicio', $d?->data_inicio?->format('Y-m-d\TH:i')) }}"></div>
    <div class="col-md-3"><label class="small fw-bold">Término</label><input type="datetime-local" name="data_fim" class="form-control form-control-sm" value="{{ old('data_fim', $d?->data_fim?->format('Y-m-d\TH:i')) }}"></div>
    <div class="col-md-3"><label class="small fw-bold">Local</label><input type="text" name="local" class="form-control form-control-sm" maxlength="255" value="{{ old('local', $d?->local) }}"></div>
    <div class="col-md-1"><label class="small fw-bold">Vagas</label><input type="number" name="vagas" min="1" class="form-control form-control-sm" value="{{ old('vagas', $d?->vagas) }}"></div>
    <div class="col-md-2"><label class="small fw-bold">Horas *</label><input type="number" step="0.5" min="0.5" name="carga_horaria" class="form-control form-control-sm" value="{{ old('carga_horaria', $d?->carga_horaria ?? 4) }}" required></div>
    <div class="col-md-6"><label class="small fw-bold">Servidor responsável (quem procurar)</label><input type="text" name="responsavel_nome" class="form-control form-control-sm" maxlength="255" placeholder="Nome do servidor" value="{{ old('responsavel_nome', $d?->responsavel_nome) }}"></div>
    <div class="col-md-6"><label class="small fw-bold">Contato / sala do responsável</label><input type="text" name="responsavel_contato" class="form-control form-control-sm" maxlength="255" placeholder="E-mail, telefone ou sala" value="{{ old('responsavel_contato', $d?->responsavel_contato) }}"></div>
    <div class="col-12"><label class="small fw-bold">Descrição (o que o voluntário deve fazer)</label><textarea name="descricao" rows="2" class="form-control form-control-sm">{{ old('descricao', $d?->descricao) }}</textarea></div>
    <div class="col-12 text-end"><button class="btn btn-primary-custom btn-sm"><i class="fa-solid fa-check"></i> {{ $d ? 'Salvar atividade' : 'Criar atividade' }}</button></div>
</form>
