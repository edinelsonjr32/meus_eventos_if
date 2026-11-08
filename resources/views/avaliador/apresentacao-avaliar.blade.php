@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');
@endphp
<x-app-layout>
    {{-- Avaliação da apresentação: mesma estrutura da avaliação do texto, com critérios e notas próprios --}}
    <div class="row justify-content-center">
        <div class="col-xl-10">

            <div class="d-flex align-items-center gap-3 mb-4">
                <a href="{{ route('avaliador.apresentacoes.index') }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <h4 class="fw-bold m-0">{{ $trabalho->titulo }}</h4>
                    <small class="text-secondary">{{ $trabalho->evento->titulo }} · {{ $trabalho->codigo }}
                        @if($trabalho->sessao) · {{ $trabalho->sessao->titulo }} ({{ $trabalho->sessao->data_inicio->format('d/m H:i') }}) @endif @if($avaliacao->prazo) · prazo {{ $avaliacao->prazo->format('d/m/Y') }} @endif</small>
                </div>
            </div>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 small">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div>
            @endif
            <div class="alert alert-info small"><i class="fa-solid fa-person-chalkboard me-1"></i> Você está avaliando a <strong>apresentação</strong> do trabalho (oral/pôster), não o texto.</div>
            @if($cega)
                <div class="alert alert-info small"><i class="fa-solid fa-user-secret me-1"></i> Avaliação cega: a identificação dos autores não é exibida.</div>
            @endif
            @if(!$editavel)
                <div class="alert alert-secondary small">Esta avaliação está <strong>{{ strtolower($avaliacao->statusRotulo()) }}</strong> e não pode mais ser editada.</div>
            @endif

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="glass-card bg-white mb-4">
                        <h6 class="fw-bold text-success mb-3">Trabalho</h6>
                        <dl class="row small mb-2">
                            <dt class="col-sm-4 text-secondary">Categoria</dt><dd class="col-sm-8">{{ \App\Models\TrabalhoConfig::TIPOS[$trabalho->tipo_trabalho] ?? '—' }}</dd>
                            <dt class="col-sm-4 text-secondary">Área</dt><dd class="col-sm-8">{{ $trabalho->area->nome ?? '—' }}</dd>
                            <dt class="col-sm-4 text-secondary">Modalidade</dt><dd class="col-sm-8">{{ \App\Models\Trabalho::MODALIDADES[$trabalho->modalidade_apresentacao] ?? '—' }}</dd>
                            <dt class="col-sm-4 text-secondary">Palavras-chave</dt><dd class="col-sm-8">{{ implode(', ', $trabalho->palavras_chave ?? []) ?: '—' }}</dd>
                        </dl>
                        @if(!$cega && $autores->isNotEmpty())
                            <div class="small mb-2"><strong>Autores:</strong> {{ $autores->map(fn ($a) => $a->nome_completo . ' (' . $a->tipoRotulo() . ')')->implode('; ') }}</div>
                        @endif
                        <hr>
                        <h6 class="fw-bold">Resumo</h6>
                        <p class="small" style="white-space: pre-line;">{{ $trabalho->resumo }}</p>
                        @if($trabalho->abstract)
                            <h6 class="fw-bold">Abstract</h6>
                            <p class="small" style="white-space: pre-line;">{{ $trabalho->abstract }}</p>
                        @endif
                        <a href="{{ route('avaliador.apresentacoes.arquivo', $avaliacao) }}" target="_blank" class="btn btn-outline-danger btn-sm w-100">
                            <i class="fa-solid fa-file-pdf me-1"></i> Abrir PDF do trabalho
                        </a>
                    </div>
                </div>

                <div class="col-lg-7">
                    <form method="POST" x-data="notasForm({{ \Illuminate\Support\Js::from($criterios->map(fn ($c) => ['id' => $c->id, 'peso' => $c->peso, 'max' => $c->nota_max])->values()) }}, {{ \Illuminate\Support\Js::from($criterios->mapWithKeys(fn ($c) => [$c->id => old("notas.{$c->id}", $notas->get($c->id)?->nota)])) }})">
                        @csrf
                        <div class="glass-card bg-white mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-success m-0">Critérios da apresentação</h6>
                                <span class="badge bg-success fs-6" x-text="'Nota: ' + media()"></span>
                            </div>

                            @foreach($criterios as $c)
                                <div class="mb-3 pb-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-bold small">{{ $c->nome }} <span class="text-muted fw-normal">(peso {{ $fmt($c->peso) }})</span></div>
                                            @if($c->descricao) <div class="small text-muted">{{ $c->descricao }}</div> @endif
                                        </div>
                                        <div style="min-width: 110px;" class="input-group input-group-sm">
                                            <input type="text" inputmode="decimal" name="notas[{{ $c->id }}]" x-model="notas[{{ $c->id }}]" class="form-control text-end" placeholder="0" data-mascara="nota" data-max="{{ $c->nota_max }}" data-nome="{{ $c->nome }}" @disabled(!$editavel)>
                                            <span class="input-group-text">/ {{ $fmt($c->nota_max) }}</span>
                                        </div>
                                    </div>
                                    <textarea name="comentarios[{{ $c->id }}]" rows="1" class="form-control form-control-sm mt-2" placeholder="Comentário (opcional)" @disabled(!$editavel)>{{ old("comentarios.{$c->id}", $notas->get($c->id)?->comentario) }}</textarea>
                                </div>
                            @endforeach
                        </div>

                        <div class="glass-card bg-white mb-4">
                            <h6 class="fw-bold text-success mb-3">Parecer</h6>
                            <label class="small fw-bold text-secondary">Parecer sobre a apresentação * (mín. 10 caracteres)</label>
                            <textarea name="parecer" rows="6" class="form-control mb-3" @disabled(!$editavel)>{{ old('parecer', $avaliacao->parecer) }}</textarea>

                            <label class="small fw-bold text-secondary">Comentário confidencial à gestão (opcional)</label>
                            <textarea name="parecer_confidencial" rows="3" class="form-control mb-3" @disabled(!$editavel)>{{ old('parecer_confidencial', $avaliacao->status === 'recusada' ? '' : $avaliacao->parecer_confidencial) }}</textarea>

                        </div>

                        @if($editavel)
                            <div class="d-flex flex-wrap gap-2 justify-content-end mb-3">
                                <button type="submit" formaction="{{ route('avaliador.apresentacoes.salvar', $avaliacao) }}" class="btn btn-outline-secondary fw-bold">
                                    <i class="fa-regular fa-floppy-disk me-1"></i> Salvar rascunho
                                </button>
                                <button type="submit" formaction="{{ route('avaliador.apresentacoes.concluir', $avaliacao) }}" class="btn btn-primary-custom fw-bold"
                                        onclick="return confirm('Concluir a avaliação da apresentação? Depois disso não será possível editar.')">
                                    <i class="fa-solid fa-check me-1"></i> Concluir avaliação
                                </button>
                            </div>
                        @endif
                    </form>

                    @if($editavel)
                        <form action="{{ route('avaliador.apresentacoes.recusar', $avaliacao) }}" method="POST" class="glass-card bg-white mb-5" x-data="{ aberto: false }">
                            @csrf
                            <button type="button" class="btn btn-sm btn-light text-danger" @click="aberto = !aberto">Não posso avaliar esta apresentação</button>
                            <div x-show="aberto" x-cloak class="mt-2">
                                <textarea name="motivo" rows="2" class="form-control form-control-sm mb-2" placeholder="Motivo (ex.: conflito de interesse)"></textarea>
                                <button class="btn btn-sm btn-danger" onclick="return confirm('Recusar esta avaliação?')">Recusar avaliação</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        function notasForm(criterios, iniciais) {
            return {
                notas: Object.fromEntries(Object.entries(iniciais).map(([k, v]) => [k, v ?? ''])),
                media() {
                    let soma = 0, pesos = 0, validas = 0;
                    criterios.forEach(c => {
                        pesos += c.peso;
                        const v = parseFloat(String(this.notas[c.id] ?? '').replace(',', '.'));
                        if (!isNaN(v) && c.max > 0) { soma += Math.min(Math.max(v, 0), c.max) / c.max * c.peso; validas++; }
                    });
                    if (!validas || !pesos) return '—';
                    return (soma / pesos * 10).toFixed(2).replace('.', ',');
                },
            };
        }
    </script>
</x-app-layout>
