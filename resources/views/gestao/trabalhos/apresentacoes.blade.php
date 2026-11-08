@php $fmt = fn ($n) => $n === null ? '—' : number_format((float) $n, 2, ',', '.'); @endphp
<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-11">

            <div class="d-flex align-items-center gap-3 mb-4">
                <a href="{{ route('gestao.trabalhos.index', $evento) }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-arrow-left"></i></a>
                <div>
                    <h3 class="fw-bold m-0">Avaliação das apresentações</h3>
                    <small class="text-secondary">{{ $evento->titulo }} · trabalhos aprovados avaliados na feira/sessão de apresentação</small>
                </div>
            </div>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
            @if($errors->any()) <div class="alert alert-danger"><ul class="mb-0 small">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div> @endif

            @unless($config)
                <div class="alert alert-warning">Crie a chamada de trabalhos antes de configurar a avaliação das apresentações.</div>
            @else
            {{-- Configuração e critérios --}}
            <form action="{{ route('gestao.trabalhos.apresentacoes.config', $evento) }}" method="POST" data-validar class="glass-card bg-white mb-4"
                  x-data="criteriosApresentacao({{ \Illuminate\Support\Js::from(old('criterios', $criterios->map(fn ($c) => ['id' => $c->id, 'nome' => $c->nome, 'descricao' => $c->descricao, 'peso' => (float) $c->peso, 'nota_max' => (float) $c->nota_max])->values())) }}, {{ \Illuminate\Support\Js::from(collect(\App\Models\CriterioApresentacao::PADROES)->map(fn ($p) => ['id' => null, 'nome' => $p[0], 'descricao' => $p[1], 'peso' => 1, 'nota_max' => 10])->values()) }}, {{ $criteriosBloqueados ? 'true' : 'false' }})">
                @csrf @method('PUT')
                <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-sliders me-1"></i> Configuração</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <div class="form-check form-switch">
                            <input type="hidden" name="avaliacao_apresentacao_ativa" value="0">
                            <input class="form-check-input" type="checkbox" name="avaliacao_apresentacao_ativa" value="1" id="apAtiva" @checked(old('avaliacao_apresentacao_ativa', $config->avaliacao_apresentacao_ativa))>
                            <label class="form-check-label fw-bold" for="apAtiva">Avaliar a apresentação dos trabalhos aprovados</label>
                        </div>
                        <div class="form-check form-switch mt-2">
                            <input type="hidden" name="autor_ve_nota_apresentacao" value="0">
                            <input class="form-check-input" type="checkbox" name="autor_ve_nota_apresentacao" value="1" id="apVe" @checked(old('autor_ve_nota_apresentacao', $config->autor_ve_nota_apresentacao))>
                            <label class="form-check-label" for="apVe">Autor vê a nota e os pareceres da apresentação (anônimos)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold text-secondary">Peso da apresentação na nota geral (%)</label>
                        <input type="number" min="0" max="100" name="peso_apresentacao" data-mascara="inteiro" data-nome="Peso da apresentação" class="form-control" value="{{ old('peso_apresentacao', $config->peso_apresentacao ?? 30) }}" required>
                        <div class="form-text">Nota geral = texto × (100 − peso) + apresentação × peso. Usada no ranking.</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="small fw-bold text-secondary m-0">Critérios da apresentação (nota ponderada de 0 a 10)</label>
                    <div class="d-flex gap-2" x-show="!bloqueado">
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="padroes()">Carregar critérios padrão</button>
                        <button type="button" class="btn btn-sm btn-outline-success" @click="adicionar()"><i class="fa-solid fa-plus"></i> Critério</button>
                    </div>
                </div>
                <div class="alert alert-warning small py-2" x-show="bloqueado" x-cloak>Já existem notas lançadas: os critérios estão bloqueados.</div>
                <div class="alert alert-info small py-2" x-show="!bloqueado && linhas.length === 0" x-cloak>Defina ao menos um critério para ativar a avaliação.</div>
                <template x-for="(c, i) in linhas" :key="i">
                    <div class="row g-2 align-items-start mb-2">
                        <input type="hidden" :name="`criterios[${i}][id]`" :value="c.id ?? ''" :disabled="bloqueado">
                        <div class="col-md-4"><input type="text" class="form-control form-control-sm" placeholder="Nome" data-nome="Nome do critério" :name="`criterios[${i}][nome]`" x-model="c.nome" :disabled="bloqueado" required></div>
                        <div class="col-md-4"><input type="text" class="form-control form-control-sm" placeholder="Descrição (opcional)" :name="`criterios[${i}][descricao]`" x-model="c.descricao" :disabled="bloqueado"></div>
                        <div class="col-md-2"><input type="number" step="0.1" min="0.1" class="form-control form-control-sm" placeholder="Peso" data-nome="Peso do critério" :name="`criterios[${i}][peso]`" x-model="c.peso" :disabled="bloqueado" required></div>
                        <div class="col-md-1"><input type="number" step="0.1" min="0.1" class="form-control form-control-sm" placeholder="Máx" data-nome="Nota máxima" :name="`criterios[${i}][nota_max]`" x-model="c.nota_max" :disabled="bloqueado" required></div>
                        <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-light text-danger" @click="remover(i)" x-show="!bloqueado"><i class="fa-solid fa-trash"></i></button></div>
                    </div>
                </template>
                <div class="text-end mt-3"><button class="btn btn-primary-custom fw-bold"><i class="fa-regular fa-floppy-disk me-1"></i> Salvar configuração</button></div>
            </form>

            {{-- Trabalhos aprovados --}}
            <div class="glass-card bg-white mb-5">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h6 class="fw-bold text-success m-0"><i class="fa-solid fa-person-chalkboard me-1"></i> Trabalhos aprovados ({{ $trabalhos->count() }})</h6>
                    <form method="GET" class="d-flex gap-2 align-items-center">
                        <select name="atividade" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Todas as sessões</option>
                            @foreach($atividades as $a)
                                <option value="{{ $a->id }}" @selected($atividadeId === $a->id)>{{ $a->titulo }} ({{ $a->data_inicio->format('d/m H:i') }})</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                @if(!$config->avaliacao_apresentacao_ativa)
                    <div class="alert alert-secondary small">A avaliação das apresentações está <strong>desativada</strong>. Ative e salve a configuração acima para atribuir avaliadores.</div>
                @endif

                <form action="{{ route('gestao.trabalhos.apresentacoes.lote', $evento) }}" method="POST" id="formLote">
                    @csrf
                    @if($atividadeId) <input type="hidden" name="atividade" value="{{ $atividadeId }}"> @endif
                    <div class="table-responsive">
                        <table class="table table-sm align-middle small">
                            <thead><tr><th style="width:30px"><input type="checkbox" onclick="document.querySelectorAll('.chk-trab').forEach(c => c.checked = this.checked)"></th><th>Trabalho</th><th>Sessão</th><th>Apresentou?</th><th>Avaliadores</th><th class="text-end">Texto</th><th class="text-end">Apresentação</th><th class="text-end">Geral</th></tr></thead>
                            <tbody>
                            @forelse($trabalhos as $t)
                                <tr>
                                    <td><input type="checkbox" class="chk-trab" name="trabalhos[]" value="{{ $t->id }}"></td>
                                    <td><a href="{{ route('gestao.trabalhos.show', $t) }}" class="fw-bold text-decoration-none">{{ $t->codigo }}</a><div class="text-muted">{{ \Illuminate\Support\Str::limit($t->titulo, 55) }}</div></td>
                                    <td>{{ $t->sessao?->titulo ?? '—' }}</td>
                                    <td>@if($t->apresentado) <span class="badge bg-success">Sim</span> @else <span class="badge bg-secondary bg-opacity-25 text-dark">Não</span> @endif</td>
                                    <td>
                                        @forelse($t->avaliacoesApresentacao as $av)
                                            <div class="d-flex align-items-center gap-1">
                                                <span>{{ $av->avaliador->name }}</span>
                                                <span class="badge {{ $av->status === 'concluida' ? 'bg-success' : ($av->status === 'recusada' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $av->statusRotulo() }}@if($av->nota_final !== null) · {{ $fmt($av->nota_final) }} @endif</span>
                                                @if($av->status !== 'concluida')
                                                    <button type="submit" form="rm{{ $av->id }}" class="btn btn-sm btn-link text-danger p-0" title="Remover" onclick="return confirm('Remover este avaliador?')"><i class="fa-solid fa-xmark"></i></button>
                                                @endif
                                            </div>
                                        @empty <span class="text-muted">—</span> @endforelse
                                    </td>
                                    <td class="text-end">{{ $fmt($t->nota_final) }}</td>
                                    <td class="text-end fw-bold">{{ $fmt($t->nota_apresentacao) }}</td>
                                    <td class="text-end fw-bold text-success">{{ $fmt($t->notaGeral()) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-4">Nenhum trabalho aprovado{{ $atividadeId ? ' nesta sessão' : '' }}. Trabalhos com ressalvas entram aqui depois da correção e da aprovação.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($config->avaliacao_apresentacao_ativa && $trabalhos->isNotEmpty())
                        <div class="border rounded-3 p-3 bg-light">
                            <div class="fw-bold small mb-2">Atribuir avaliadores aos trabalhos marcados{{ $atividadeId ? ' (ou, sem marcar nenhum, a todos desta sessão)' : '' }}</div>
                            <div class="row g-2">
                                @forelse($avaliadores as $u)
                                    <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="avaliadores[]" value="{{ $u->id }}" id="lav{{ $u->id }}"><label class="form-check-label small" for="lav{{ $u->id }}">{{ $u->name }} <span class="text-muted">({{ $u->carga }} pendente(s))</span></label></div></div>
                                @empty <div class="col-12 small text-muted">Nenhum avaliador designado neste evento (use Papéis do evento).</div> @endforelse
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-end mt-3">
                                <div><label class="small fw-bold text-secondary">Prazo (opcional)</label><input type="date" name="prazo" class="form-control form-control-sm" min="{{ now()->addDay()->format('Y-m-d') }}"></div>
                                <button class="btn btn-primary-custom btn-sm fw-bold"><i class="fa-solid fa-user-plus me-1"></i> Atribuir</button>
                            </div>
                            <div class="form-text">Quem tem conflito de interesse com o trabalho (autor/responsável) é ignorado automaticamente.</div>
                        </div>
                    @endif
                </form>
                @foreach($trabalhos as $t) @foreach($t->avaliacoesApresentacao as $av)
                    <form id="rm{{ $av->id }}" action="{{ route('gestao.trabalhos.apresentacoes.remover', $av) }}" method="POST" class="d-none">@csrf @method('DELETE')</form>
                @endforeach @endforeach
            </div>
            @endunless
        </div>
    </div>

    <script>
        function criteriosApresentacao(iniciais, padroes, bloqueado) {
            return {
                linhas: iniciais.map(c => ({ ...c })), bloqueado,
                adicionar() { this.linhas.push({ id: null, nome: '', descricao: '', peso: 1, nota_max: 10 }); },
                remover(i) { this.linhas.splice(i, 1); },
                padroes() { if (this.linhas.length && !confirm('Substituir os critérios atuais pelos padrão?')) return; this.linhas = padroes.map(c => ({ ...c })); },
            };
        }
    </script>
</x-app-layout>
