@php $c = $chamada; $id = $c?->id ?? 'n'; @endphp
<form action="{{ $acao }}" method="POST" class="row g-3">
    @csrf
    @if($metodo) @method($metodo) @endif
    <div class="col-md-8"><label class="small fw-bold">Título da chamada *</label><input type="text" name="titulo" class="form-control" maxlength="255" value="{{ old('titulo', $c?->titulo ?? 'Chamada de voluntários') }}" required></div>
    <div class="col-md-4"><label class="small fw-bold">Vagas (em branco = sem limite)</label><input type="number" name="vagas" min="1" class="form-control" value="{{ old('vagas', $c?->vagas) }}"></div>
    <div class="col-12"><label class="small fw-bold">Descrição / o que o voluntário fará</label><textarea name="descricao" rows="3" class="form-control">{{ old('descricao', $c?->descricao) }}</textarea></div>
    <div class="col-12"><label class="small fw-bold">Requisitos</label><textarea name="requisitos" rows="2" class="form-control" placeholder="Ex.: ser aluno do campus, disponibilidade nos dois dias...">{{ old('requisitos', $c?->requisitos) }}</textarea></div>
    <div class="col-md-3"><label class="small fw-bold">Abre em</label><input type="datetime-local" name="inicio" class="form-control" value="{{ old('inicio', $c?->inicio?->format('Y-m-d\TH:i')) }}"></div>
    <div class="col-md-3"><label class="small fw-bold">Encerra em</label><input type="datetime-local" name="fim" class="form-control" value="{{ old('fim', $c?->fim?->format('Y-m-d\TH:i')) }}"></div>
    <div class="col-md-6 d-flex flex-wrap justify-content-between align-items-end gap-2">
        <div class="form-check form-switch mb-0"><input type="hidden" name="ativa" value="0"><input class="form-check-input" type="checkbox" role="switch" name="ativa" value="1" id="vat{{ $id }}" @checked(old('ativa', $c?->ativa ?? true))><label class="form-check-label small fw-bold" for="vat{{ $id }}">Chamada ativa</label></div>
        <button class="btn btn-primary-custom"><i class="fa-solid fa-check"></i> {{ $c ? 'Salvar chamada' : 'Criar chamada' }}</button>
    </div>
</form>
