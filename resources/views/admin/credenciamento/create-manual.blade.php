<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-800 leading-tight">
            Cadastro Manual e Credenciamento
        </h2>
    </x-slot>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('admin.eventos.credenciamento.index', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div>
                        <h3 class="fw-bold m-0">Cadastrar e Credenciar</h3>
                        <small class="text-secondary">Evento: {{ $evento->titulo }}</small>
                    </div>
                </div>
            </div>

            <div class="glass-card p-6 bg-white shadow-lg" x-data="{ tipoVinculo: '{{ old('tipo_vinculo', 'aluno') }}' }">

                <p class="text-sm text-gray-500 mb-6">Preencha os dados. O participante será cadastrado, inscrito no evento e terá o check-in confirmado imediatamente.</p>

                @if(session('error'))
                    <div class="alert alert-danger small p-2 mb-3">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger small border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-4">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ route('admin.eventos.credenciamento.storeManual', $evento->id) }}" class="space-y-4">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Nome Completo *</label>
                            <input type="text" name="nome_completo" class="form-control" value="{{ old('nome_completo') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase">CPF *</label>
                            <input type="text" name="cpf" class="form-control cpf-mask" value="{{ old('cpf') }}" placeholder="000.000.000-00" required>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Email *</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Tipo de Vínculo *</label>
                            <select name="tipo_vinculo" x-model="tipoVinculo" class="form-select" required>
                                <option value="aluno">Aluno (IFPA)</option>
                                <option value="servidor">Servidor (IFPA)</option>
                                <option value="externo">Externo (Comunidade)</option>
                            </select>
                        </div>
                    </div>

                    <div x-show="tipoVinculo !== 'externo'" class="mt-4 p-4 bg-light rounded-lg border border-gray-200" style="display: none;">
                        <h5 class="text-md font-bold text-gray-700 mb-3">Dados Institucionais</h5>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Matrícula</label>
                            <input type="text" name="matricula" class="form-control" value="{{ old('matricula') }}">
                        </div>

                        <div x-show="tipoVinculo === 'aluno'">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Turma *</label>
                            <select name="turma_id" class="form-select">
                                <option value="">Selecione a Turma/Curso...</option>
                                @foreach($turmas as $turma)
                                    <option value="{{ $turma->id }}" @selected(old('turma_id') == $turma->id)>
                                        {{ $turma->nome_completo }} ({{ $turma->curso->nome ?? 'Sem Curso' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="pt-4 d-flex justify-content-end">
                        <button type="submit" class="btn btn-success px-4 py-2 font-bold shadow-sm">
                            <i class="fa-solid fa-user-plus me-2"></i> Cadastrar e Credenciar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        $(document).ready(function(){
            $('.cpf-mask').mask('000.000.000-00');
        });
    </script>
</x-app-layout>
