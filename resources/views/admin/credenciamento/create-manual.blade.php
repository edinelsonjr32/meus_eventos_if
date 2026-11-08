<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-8">

            <div class="d-flex align-items-center gap-3 mb-4">
                <a href="{{ route('admin.eventos.credenciamento.index', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
                <div>
                    <h3 class="fw-bold m-0"><i class="fa-solid fa-user-plus text-success me-1"></i> Cadastrar e credenciar</h3>
                    <small class="text-secondary">{{ $evento->titulo }}</small>
                </div>
            </div>

            <div class="alert alert-light border small"><i class="fa-solid fa-circle-info me-1 text-primary"></i> O participante será cadastrado, inscrito no evento e terá o <strong>check-in confirmado na hora</strong>.</div>

            @if(session('error')) <div class="alert alert-danger small"><i class="fa-solid fa-circle-exclamation me-1"></i> {{ session('error') }}</div> @endif

            <form method="post" action="{{ route('admin.eventos.credenciamento.storeManual', $evento->id) }}" data-validar x-data="{ tipoVinculo: '{{ old('tipo_vinculo', 'aluno') }}' }">
                @csrf

                <div class="glass-card bg-white mb-4">
                    <h6 class="fw-bold text-success mb-3"><i class="fa-regular fa-id-card me-1"></i> Dados pessoais</h6>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="small fw-bold text-secondary" for="nome">Nome completo *</label>
                            <input id="nome" type="text" name="nome_completo" class="form-control" data-nome="Nome completo" value="{{ old('nome_completo') }}" required>
                        </div>
                        <div class="col-md-5">
                            <label class="small fw-bold text-secondary" for="cpf">CPF *</label>
                            <input id="cpf" type="text" name="cpf" class="form-control" data-mascara="cpf" data-nome="CPF" maxlength="14" pattern="\d{3}\.\d{3}\.\d{3}-\d{2}" title="Informe o CPF completo (000.000.000-00)" value="{{ old('cpf') }}" placeholder="000.000.000-00" required>
                        </div>
                        <div class="col-md-7">
                            <label class="small fw-bold text-secondary" for="email">E-mail *</label>
                            <input id="email" type="email" name="email" class="form-control" data-nome="E-mail" value="{{ old('email') }}" required>
                        </div>
                        <div class="col-md-5">
                            <label class="small fw-bold text-secondary" for="vinculo">Vínculo *</label>
                            <select id="vinculo" name="tipo_vinculo" x-model="tipoVinculo" class="form-select" required>
                                <option value="aluno">Aluno (IFPA)</option>
                                <option value="servidor">Servidor (IFPA)</option>
                                <option value="externo">Externo (comunidade)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="glass-card bg-white mb-4" x-show="tipoVinculo !== 'externo'" x-cloak>
                    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-building-columns me-1"></i> Dados institucionais</h6>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="small fw-bold text-secondary" for="matricula">Matrícula *</label>
                            <input id="matricula" type="text" name="matricula" class="form-control" data-nome="Matrícula" value="{{ old('matricula') }}" :required="tipoVinculo !== 'externo'" :disabled="tipoVinculo === 'externo'">
                        </div>
                        <div class="col-md-7" x-show="tipoVinculo === 'aluno'">
                            <label class="small fw-bold text-secondary" for="turma">Turma / curso *</label>
                            <select id="turma" name="turma_id" class="form-select" data-nome="Turma" :required="tipoVinculo === 'aluno'" :disabled="tipoVinculo !== 'aluno'">
                                <option value="">Selecione a turma…</option>
                                @foreach($turmas as $turma)
                                    <option value="{{ $turma->id }}" @selected(old('turma_id') == $turma->id)>{{ $turma->nome_completo }} ({{ $turma->curso->nome ?? 'Sem curso' }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-end gap-2 mb-5">
                    <a href="{{ route('admin.eventos.credenciamento.index', $evento->id) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark"></i> Cancelar</a>
                    <button type="submit" class="btn btn-success fw-bold px-4"><i class="fa-solid fa-user-check"></i> Cadastrar e credenciar</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
