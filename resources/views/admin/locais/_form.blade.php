@php $l = $local ?? null; $id = $l?->id ?? 'n'; @endphp
<form action="{{ $acao }}" method="POST" class="row g-2">
    @csrf
    @if($l) @method('PUT') @endif
    <div class="col-md-4"><label class="small fw-bold">Nome *</label><input type="text" name="nome" class="form-control form-control-sm" minlength="2" maxlength="120" placeholder="Ex.: Laboratório de Informática 2" value="{{ old('nome', $l?->nome) }}" required></div>
    <div class="col-md-3"><label class="small fw-bold">Bloco / referência</label><input type="text" name="bloco" class="form-control form-control-sm" maxlength="120" placeholder="Ex.: Bloco B, 1º andar" value="{{ old('bloco', $l?->bloco) }}"></div>
    <div class="col-md-3"><label class="small fw-bold">Tipo *</label>
        <select name="tipo" class="form-select form-select-sm">@foreach($tipos as $k => $r)<option value="{{ $k }}" @selected(old('tipo', $l?->tipo ?? 'sala') === $k)>{{ $r }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="small fw-bold">Capacidade</label><input type="number" name="capacidade" min="1" max="100000" class="form-control form-control-sm" placeholder="pessoas" value="{{ old('capacidade', $l?->capacidade) }}"></div>
    <div class="col-12"><label class="small fw-bold">Equipamentos disponíveis</label><input type="text" name="equipamentos" class="form-control form-control-sm" maxlength="2000" placeholder="Ex.: projetor, 30 computadores, caixa de som" value="{{ old('equipamentos', $l?->equipamentos) }}"></div>
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="form-check form-switch mb-0"><input type="hidden" name="ativo" value="0"><input class="form-check-input" type="checkbox" role="switch" name="ativo" value="1" id="at{{ $id }}" @checked(old('ativo', $l?->ativo ?? true))><label class="form-check-label small" for="at{{ $id }}">Ativo (aparece nas listas de escolha)</label></div>
        <button class="btn btn-primary-custom btn-sm"><i class="fa-solid fa-check"></i> {{ $l ? 'Salvar local' : 'Cadastrar local' }}</button>
    </div>
</form>
