@php $c = $chamada; @endphp
<form action="{{ $acao }}" method="POST" class="row g-3">
    @csrf
    @if($metodo) @method($metodo) @endif
    <div class="col-md-8">
        <label class="small fw-bold" for="t{{ $c?->id ?? 'n' }}">Título da chamada *</label>
        <input id="t{{ $c?->id ?? 'n' }}" type="text" name="titulo" class="form-control" maxlength="255" value="{{ old('titulo', $c?->titulo ?? 'Chamada de minicursos e oficinas') }}" required>
    </div>
    <div class="col-md-4">
        <label class="small fw-bold">Máx. de propostas por pessoa</label>
        <input type="number" name="max_por_usuario" min="1" max="20" class="form-control" value="{{ old('max_por_usuario', $c?->max_por_usuario ?? 3) }}">
    </div>
    <div class="col-12">
        <label class="small fw-bold">Orientações aos proponentes</label>
        <x-editor-rico name="descricao" :value="old('descricao', $c?->descricao)" modo="simples" :altura="160" placeholder="Prazos, critérios, carga horária aceita, o que a organização oferece (laboratório, projetor...)" />
    </div>
    <div class="col-md-6">
        <label class="small fw-bold d-block">Tipos aceitos</label>
        @php $aceitos = old('tipos_aceitos', $c?->tipos_aceitos ?? array_keys(\App\Models\ChamadaProposta::TIPOS)); @endphp
        @foreach(\App\Models\ChamadaProposta::TIPOS as $valor => $rotulo)
            <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="tipos_aceitos[]" value="{{ $valor }}" id="ta{{ $c?->id ?? 'n' }}{{ $valor }}" @checked(in_array($valor, $aceitos))><label class="form-check-label" for="ta{{ $c?->id ?? 'n' }}{{ $valor }}">{{ $rotulo }}</label></div>
        @endforeach
    </div>
    <div class="col-md-3"><label class="small fw-bold">Abre em</label><input type="datetime-local" name="inicio" class="form-control" value="{{ old('inicio', $c?->inicio?->format('Y-m-d\TH:i')) }}"></div>
    <div class="col-md-3"><label class="small fw-bold">Encerra em</label><input type="datetime-local" name="fim" class="form-control" value="{{ old('fim', $c?->fim?->format('Y-m-d\TH:i')) }}"></div>
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="form-check form-switch mb-0"><input type="hidden" name="ativa" value="0"><input class="form-check-input" type="checkbox" role="switch" name="ativa" value="1" id="at{{ $c?->id ?? 'n' }}" @checked(old('ativa', $c?->ativa ?? true))><label class="form-check-label small fw-bold" for="at{{ $c?->id ?? 'n' }}">Chamada ativa</label></div>
        <button class="btn btn-primary-custom"><i class="fa-solid fa-check"></i> {{ $c ? 'Salvar chamada' : 'Criar chamada' }}</button>
    </div>
</form>
