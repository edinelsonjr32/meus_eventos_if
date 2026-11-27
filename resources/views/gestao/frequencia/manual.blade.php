<x-app-layout>
    <div class="row justify-content-center"><div class="col-xl-9">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <a href="{{ route('gestao.frequencia.index') }}" class="btn btn-light fw-bold"><i class="fa-solid fa-arrow-left me-1"></i> Voltar</a>
            <a href="{{ route('gestao.frequencia.qr', $atividade) }}" class="btn btn-success fw-bold"><i class="fa-solid fa-qrcode me-1"></i> Exibir QR Code</a>
        </div>
        <h3 class="fw-bold mb-1">Presença manual</h3>
        <p class="text-secondary">{{ $atividade->titulo }} · {{ $atividade->evento->titulo }} · <strong>{{ $total }}</strong> presença(s)</p>

        @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
        @if($errors->any()) <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div> @endif

        <div class="glass-card bg-white mb-4">
            <h6 class="fw-bold text-success"><span class="badge bg-success me-2">1</span> Procure o participante</h6>
            <form method="GET" action="{{ route('gestao.frequencia.manual', $atividade) }}" class="d-flex flex-wrap gap-2">
                <input type="search" name="q" value="{{ $busca }}" class="form-control flex-grow-1" style="min-width:220px;width:auto" placeholder="Nome, CPF, e-mail ou matrícula" minlength="3" required autofocus>
                <button class="btn btn-primary-custom fw-bold"><i class="fa-solid fa-magnifying-glass me-1"></i> Buscar</button>
            </form>

            @if($resultados !== null)
                <div class="mt-3">
                    @forelse($resultados as $p)
                        @php $cpf = preg_replace('/\D/', '', (string) $p->cpf); @endphp
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
                            <div class="min-w-0">
                                <div class="fw-bold texto-quebra">{{ $p->nome_completo }}</div>
                                <small class="text-muted">CPF ***.{{ substr($cpf, 3, 3) }}.{{ substr($cpf, 6, 3) }}-**@if($p->matricula) · matrícula {{ $p->matricula }}@endif @if($p->turma) · {{ $p->turma->nome_completo }}@endif</small>
                            </div>
                            @if(in_array($p->id, $presentes))
                                <span class="badge text-bg-success"><i class="fa-solid fa-check me-1"></i> Já presente</span>
                            @else
                                <form method="POST" action="{{ route('gestao.frequencia.registrar', [$atividade, $p]) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-success fw-bold"><i class="fa-solid fa-user-check me-1"></i> Registrar presença</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">Ninguém encontrado. Confira o que foi digitado ou cadastre a pessoa abaixo.</p>
                    @endforelse
                </div>
            @endif
        </div>

        <div class="glass-card bg-white mb-4" x-data="{ aberto: {{ $errors->any() || ($resultados !== null && $resultados->isEmpty()) ? 'true' : 'false' }} }">
            <button type="button" class="btn btn-link p-0 fw-bold text-success text-decoration-none" @click="aberto = !aberto"><span class="badge bg-success me-2">2</span> A pessoa não tem cadastro <i class="fa-solid" :class="aberto ? 'fa-chevron-up' : 'fa-chevron-down'"></i></button>
            <form method="POST" action="{{ route('gestao.frequencia.novo', $atividade) }}" class="mt-3" x-show="aberto" x-cloak>
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-12"><label class="small fw-bold">Nome completo *</label><input type="text" name="nome_completo" class="form-control" value="{{ old('nome_completo') }}" required></div>
                    <div class="col-md-5"><label class="small fw-bold">CPF *</label><input type="text" name="cpf" class="form-control" value="{{ old('cpf') }}" maxlength="14" inputmode="numeric" placeholder="000.000.000-00" required></div>
                    <div class="col-md-7"><label class="small fw-bold">E-mail *</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" required></div>
                </div>
                <x-campos-vinculo idp="manual" />
                <button class="btn btn-primary-custom fw-bold"><i class="fa-solid fa-user-plus me-1"></i> Cadastrar e registrar presença</button>
            </form>
        </div>

        <div class="glass-card bg-white">
            <h6 class="fw-bold">Últimas presenças</h6>
            @forelse($ultimas as $f)
                <div class="d-flex justify-content-between gap-2 py-1 border-bottom small"><span class="texto-quebra">{{ $f->participante?->nome_completo ?? '—' }}</span><span class="text-muted text-nowrap">{{ $f->data_registro?->format('d/m H:i') }}</span></div>
            @empty
                <p class="text-muted small mb-0">Nenhuma presença registrada ainda.</p>
            @endforelse
        </div>
    </div></div>
</x-app-layout>
