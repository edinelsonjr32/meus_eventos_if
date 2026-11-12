<x-app-layout>
    <div class="row justify-content-center"><div class="col-xl-8">
        <div class="d-flex align-items-center gap-3 mb-4">
            <a href="{{ route('voluntariado.meu') }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
            <div><h3 class="fw-bold m-0">Quero ser voluntário(a)</h3><small class="text-secondary">{{ $chamada->evento->titulo }} · {{ $chamada->titulo }}</small></div>
        </div>

        @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div>@endif

        @if($chamada->descricao || $chamada->requisitos)
            <div class="glass-card mb-4 small">
                @if($chamada->descricao)<div class="texto-quebra mb-2">{{ $chamada->descricao }}</div>@endif
                @if($chamada->requisitos)<div><strong>Requisitos:</strong> <span class="texto-quebra">{{ $chamada->requisitos }}</span></div>@endif
                <div class="mt-2 text-muted">{{ $chamada->vagas ? $chamada->vagas . ' vaga(s)' : 'Sem limite de vagas' }}{{ $chamada->fim ? ' · inscrições até ' . $chamada->fim->format('d/m/Y H:i') : '' }}</div>
            </div>
        @endif

        <form action="{{ route('voluntariado.store', $chamada) }}" method="POST" class="glass-card">
            @csrf
            <div class="alert alert-light border small"><i class="fa-solid fa-user-check me-1 text-success"></i> Inscrevendo como <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}).</div>
            <div class="mb-3"><label class="small fw-bold">Por que você quer ser voluntário(a)? *</label><textarea name="motivacao" rows="4" class="form-control" required>{{ old('motivacao') }}</textarea></div>
            <div class="mb-3"><label class="small fw-bold">Dias e horários em que pode ajudar *</label><textarea name="disponibilidade" rows="3" class="form-control" placeholder="Ex.: terça e quarta, manhã e tarde" required>{{ old('disponibilidade') }}</textarea></div>
            <div class="mb-3"><label class="small fw-bold">Habilidades ou experiências úteis</label><textarea name="habilidades" rows="2" class="form-control" placeholder="Ex.: fotografia, informática, atendimento ao público">{{ old('habilidades') }}</textarea></div>
            <div class="mb-4"><label class="small fw-bold">Telefone / WhatsApp</label><input type="text" name="telefone" class="form-control" maxlength="30" value="{{ old('telefone') }}"></div>
            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between">
                <a href="{{ route('voluntariado.meu') }}" class="btn btn-light fw-bold">Cancelar</a>
                <button class="btn btn-primary-custom fw-bold"><i class="fa-solid fa-hand-holding-heart me-1"></i> Enviar inscrição</button>
            </div>
        </form>
    </div></div>
</x-app-layout>
