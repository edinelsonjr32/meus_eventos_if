<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-800 leading-tight">
            Cadastro Manual: {{ $atividade->titulo }}
        </h2>
    </x-slot>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="glass-card p-6 bg-white shadow-lg" x-data="{ tipoVinculo: '{{ old('tipo_vinculo', 'externo') }}' }">

                <h4 class="text-xl font-bold text-gray-800 mb-2">Registrar Novo Participante</h4>
                <p class="text-sm text-gray-500 mb-6">Cadastre os dados e confirme a presença na atividade. Campos marcados com * são obrigatórios.</p>

                @if ($errors->any())
                    <div class="alert alert-danger small border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-4">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ route('admin.atividades.participantes.store', $atividade->id) }}" class="space-y-4">
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
                                <option value="externo">Externo (Comunidade)</option>
                                <option value="aluno">Aluno (IFPA)</option>
                                <option value="servidor">Servidor (IFPA)</option>
                            </select>
                        </div>
                    </div>

                    <div x-show="tipoVinculo !== 'externo'" x-transition:enter class="mt-4 p-4 bg-light rounded-lg border border-gray-200" style="display: none;">
                        <h5 class="text-md font-bold text-gray-700 mb-3">Dados Institucionais</h5>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Matrícula @if($errors->has('matricula')) * @endif</label>
                            <input type="text" name="matricula" class="form-control" value="{{ old('matricula') }}">
                        </div>

                        <div x-show="tipoVinculo === 'aluno'" x-transition:enter.duration.500ms>
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

                    <div class="pt-4 flex justify-end">
                        <button type="submit" class="btn btn-success px-4 py-2 font-bold shadow-sm">
                            <i class="fa-solid fa-clipboard-check me-2"></i> Confirmar Presença
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function(){
            $('.cpf-mask').mask('000.000.000-00');
        });
    </script>
</x-app-layout>
