@props(['tipo' => 'aluno', 'matricula' => null, 'turmaId' => null, 'compacto' => false, 'idp' => 'cv', 'usarOld' => true])
@php
    // Dados vindos do formulário (old) têm prioridade sobre os do cadastro
    $tipoAtual = $usarOld ? old('tipo_vinculo', $tipo ?: 'aluno') : ($tipo ?: 'aluno');
    $turmaAtual = (string) ($usarOld ? old('turma_id', $turmaId) : $turmaId);
    $cursos = \App\Models\Curso::with(['turmas' => fn ($q) => $q->orderBy('ano', 'desc')])->orderBy('nome')->get();
    $cursoAtual = (string) ($cursos->first(fn ($c) => $c->turmas->contains('id', (int) $turmaAtual))?->id ?? '');
    $dados = $cursos->map(fn ($c) => ['id' => (string) $c->id, 'nome' => $c->nome, 'turmas' => $c->turmas->map(fn ($t) => ['id' => (string) $t->id, 'ano' => (string) $t->ano])->values()])->values();
@endphp
<div x-data="{ tipo: @js($tipoAtual), curso: @js($cursoAtual), turma: @js($turmaAtual), cursos: @js($dados), get turmas() { return (this.cursos.find(c => c.id === this.curso) || { turmas: [] }).turmas; } }"
     x-effect="if (!turmas.some(t => t.id === turma)) turma = ''">
    <div class="mb-3">
        <label class="fw-bold small text-secondary text-uppercase mb-1" for="{{ $idp }}-tipo">Tipo de cadastro *</label>
        <select id="{{ $idp }}-tipo" name="tipo_vinculo" x-model="tipo" class="form-select" required>
            <option value="aluno">Aluno (discente do IFPA)</option>
            <option value="servidor">Servidor (docente ou técnico)</option>
            <option value="externo">Comunidade externa</option>
        </select>
        @error('tipo_vinculo') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
    </div>

    <div x-show="tipo === 'aluno' || tipo === 'servidor'" x-cloak class="bg-light p-3 rounded-3 mb-3 border">
        <div class="mb-3">
            <label class="fw-bold small text-dark mb-1" for="{{ $idp }}-mat" x-text="tipo === 'aluno' ? 'Matrícula *' : 'Matrícula SIAPE *'"></label>
            <input id="{{ $idp }}-mat" type="text" name="matricula" class="form-control bg-white" maxlength="50" value="{{ $usarOld ? old('matricula', $matricula) : $matricula }}" :disabled="tipo === 'externo'" :required="tipo !== 'externo'">
            @error('matricula') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
        </div>

        <div x-show="tipo === 'aluno'" x-cloak>
            <div class="mb-3">
                <label class="fw-bold small text-dark mb-1" for="{{ $idp }}-curso">Curso *</label>
                <select id="{{ $idp }}-curso" x-model="curso" class="form-select bg-white" :required="tipo === 'aluno'">
                    <option value="">Selecione o curso...</option>
                    <template x-for="c in cursos" :key="c.id"><option :value="c.id" x-text="c.nome" :selected="c.id === curso"></option></template>
                </select>
            </div>
            <div>
                <label class="fw-bold small text-dark mb-1" for="{{ $idp }}-turma">Turma (ano) *</label>
                <select id="{{ $idp }}-turma" name="turma_id" x-model="turma" class="form-select bg-white" :disabled="tipo !== 'aluno' || !curso" :required="tipo === 'aluno'">
                    <option value="" x-text="curso ? 'Selecione a turma...' : 'Escolha o curso primeiro'"></option>
                    <template x-for="t in turmas" :key="t.id"><option :value="t.id" x-text="t.ano" :selected="t.id === turma"></option></template>
                </select>
                @error('turma_id') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
            </div>
        </div>
    </div>
</div>
