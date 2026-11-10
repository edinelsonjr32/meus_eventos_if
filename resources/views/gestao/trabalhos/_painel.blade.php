@php
    $metaAvaliadores = $trabalho->evento->trabalhoConfig?->avaliadores_por_trabalho ?? 2;
    $ativas = $trabalho->avaliacoes->where('status', '!=', 'recusada');
@endphp

@if(session('erros_lote'))
    <div class="alert alert-warning small">
        <ul class="mb-0 ps-3">@foreach(session('erros_lote') as $erro) <li>{{ $erro }}</li> @endforeach</ul>
    </div>
@endif


{{-- Etapas do trabalho: onde ele está e o que falta --}}
@php
    $cfgEt = $trabalho->evento->trabalhoConfig;
    $apAtiva = (bool) $cfgEt?->avaliacao_apresentacao_ativa;
    $aprovado = $trabalho->status === 'aprovado';
    $concluidasAp = $trabalho->avaliacoesApresentacao()->where('status', 'concluida')->count();
    $totalAp = $trabalho->avaliacoesApresentacao()->where('status', '!=', 'recusada')->count();
    $etapas = [
        ['1. Triagem', in_array($trabalho->status, ['submetido', 'resubmetido', 'em_triagem', 'devolvido_triagem'], true) ? 'atual' : 'ok', null],
        ['2. Avaliação do texto', in_array($trabalho->status, ['em_avaliacao', 'avaliado', 'aguardando_correcao'], true) ? 'atual' : ($trabalho->nota_final !== null ? 'ok' : 'futuro'), $trabalho->nota_final !== null ? 'nota ' . number_format($trabalho->nota_final, 2, ',', '.') : null],
        ['3. Decisão', $aprovado ? 'ok' : ($trabalho->status === 'avaliado' ? 'atual' : 'futuro'), $aprovado ? 'aprovado' : null],
        ['4. Apresentação', $trabalho->apresentado ? 'ok' : ($aprovado ? 'atual' : 'futuro'), $trabalho->apresentado ? 'apresentado' : ($aprovado ? 'marcar abaixo' : null)],
    ];
    if ($apAtiva) {
        $etapas[] = ['5. Avaliação da apresentação', $trabalho->nota_apresentacao !== null && $concluidasAp >= $totalAp && $totalAp > 0 ? 'ok' : ($aprovado ? 'atual' : 'futuro'), $totalAp ? "{$concluidasAp}/{$totalAp} concluída(s)" : ($aprovado ? 'atribuir avaliadores' : null)];
    }
    if ($apAtiva) {
        $etapas[] = [($apAtiva ? '6' : '5') . '. Resultado final', $trabalho->aprovadoCompleto() && $aprovado ? 'ok' : ($aprovado && $trabalho->resultado_apresentacao === 'reprovado' ? 'atual' : 'futuro'),
            $aprovado ? ($trabalho->aprovadoCompleto() ? 'Aprovado (texto + apresentação)' : ($trabalho->resultado_apresentacao === 'reprovado' ? 'apresentação não aprovada' : 'aprovado só na parte escrita')) : null];
    }
    $situ = $trabalho->statusFinal();
    $etapas[] = [($apAtiva ? '7' : '5') . '. Certificado', $situ === 'concluido' ? 'ok' : ($situ === 'aguardando_certificado' ? 'atual' : 'futuro'),
        $situ === 'concluido' ? 'emitidos — fluxo concluído' : ($situ === 'aguardando_certificado' ? 'aguardando emissão' : null)];
@endphp
<div class="glass-card bg-white mb-4">
    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-route me-1"></i> Etapas</h6>
    @if(!empty($pendenciasTrabalho))
        <div class="mb-3 pb-2 border-bottom">
            <div class="small fw-bold text-secondary mb-1">Pendências</div>
            @foreach($pendenciasTrabalho as $chave) <div class="mb-1"><x-badge tipo="pendencia" :valor="$chave" /></div> @endforeach
        </div>
    @endif
    <ul class="list-unstyled small mb-0">
        @foreach($etapas as [$nome, $estado, $detalhe])
            <li class="d-flex align-items-center gap-2 mb-1">
                <i class="fa-solid {{ $estado === 'ok' ? 'fa-circle-check text-success' : ($estado === 'atual' ? 'fa-circle-dot text-primary' : 'fa-circle text-secondary opacity-25') }}"></i>
                <span class="{{ $estado === 'atual' ? 'fw-bold' : ($estado === 'futuro' ? 'text-muted' : '') }}">{{ $nome }}</span>
                @if($detalhe) <span class="text-muted">· {{ $detalhe }}</span> @endif
            </li>
        @endforeach
    </ul>
    @if($aprovado)
        <a href="#card-apresentacao" class="btn btn-sm btn-outline-success w-100 mt-2"><i class="fa-solid fa-person-chalkboard me-1"></i> Ir para Apresentação (marcar como apresentado)</a>
    @endif
</div>

{{-- Triagem --}}
<div class="glass-card bg-white mb-4">
    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-filter me-1"></i> Triagem</h6>

    @if($acoesTriagem->isEmpty())
        <p class="small text-muted m-0">Nenhuma ação de triagem disponível para o status atual ({{ $trabalho->statusRotulo() }}).</p>
    @else
        <form action="{{ route('gestao.trabalhos.triagem', $trabalho) }}" method="POST">
            @csrf
            @if($acoesTriagem->contains(true))
                <label class="small fw-bold text-secondary">Justificativa (obrigatória para devolver ou rejeitar)</label>
                <textarea name="justificativa" rows="3" class="form-control form-control-sm mb-2" maxlength="3000" placeholder="Explique ao autor o que precisa ser ajustado ou o motivo da rejeição">{{ old('justificativa') }}</textarea>
            @endif

            <div class="d-grid gap-2">
                @foreach($acoesTriagem as $para => $exigeJustificativa)
                    @php
                        $cancelaEnvio = $trabalho->status === 'em_avaliacao' && $para === 'em_triagem';
                        $faltam = max(0, ($minimoAvaliadores ?? 1) - ($atribuidosNaRodada ?? 0));
                        $bloqueado = $para === 'em_avaliacao' && $faltam > 0;
                        $rotulos = [
                            'em_triagem' => $cancelaEnvio ? ['Cancelar envio para avaliação', 'btn-outline-warning', 'fa-ban'] : ['Iniciar triagem', 'btn-primary-custom', 'fa-play'],
                            'em_avaliacao' => ['Encaminhar para avaliação', 'btn-success', 'fa-share'],
                            'devolvido_triagem' => ['Devolver ao autor para ajustes', 'btn-warning', 'fa-rotate-left'],
                            'rejeitado_triagem' => ['Rejeitar na triagem', 'btn-outline-danger', 'fa-ban'],
                        ][$para];
                    @endphp
                    <button type="submit" name="para" value="{{ $para }}" class="btn btn-sm {{ $rotulos[1] }} fw-bold" @disabled($bloqueado)
                            @if($para === 'rejeitado_triagem') data-confirmar="Rejeitar este trabalho? O autor será avisado por e-mail." @endif
                            @if($cancelaEnvio) data-confirmar="Cancelar o envio para avaliação? O trabalho volta à triagem; você poderá remover ou trocar os avaliadores e encaminhar de novo." @endif>
                        <i class="fa-solid {{ $rotulos[2] }} me-1"></i> {{ $rotulos[0] }}
                    </button>
                    @if($bloqueado)
                        <div class="small text-danger"><i class="fa-solid fa-circle-exclamation me-1"></i> Faltam {{ $faltam }} avaliador(es): a chamada exige no mínimo {{ $minimoAvaliadores }} ({{ $atribuidosNaRodada }} atribuído(s)).</div>
                    @endif
                    @if($cancelaEnvio)
                        <div class="small text-muted">Atribuiu o avaliador errado? Cancele o envio, remova-o em <em>Avaliadores</em>, atribua o correto e encaminhe de novo.</div>
                    @endif
                @endforeach
            </div>
        </form>
    @endif
</div>

{{-- Avaliadores --}}
<div class="glass-card bg-white mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-success m-0"><i class="fa-solid fa-user-check me-1"></i> Avaliadores</h6>
        <span class="sb" style="{{ $ativas->count() >= $metaAvaliadores ? 'background:#dcfce7;color:#15803d;border-color:#86efac;' : 'background:#fef3c7;color:#b45309;border-color:#fcd34d;' }}" title="Avaliadores atribuídos / mínimo exigido">
            <i class="fa-solid {{ $ativas->count() >= $metaAvaliadores ? 'fa-circle-check' : 'fa-triangle-exclamation' }}"></i> {{ $ativas->count() }}/{{ $metaAvaliadores }} (mín.)
        </span>
    </div>

    @forelse($trabalho->avaliacoes as $av)
        <div class="d-flex justify-content-between align-items-start small mb-2 pb-2 border-bottom">
            <div>
                <div class="fw-bold">{{ $av->avaliador->name }} @if($av->desempate)<span class="sb" style="background:#fef3c7;color:#b45309;border-color:#fcd34d;"><i class="fa-solid fa-scale-balanced"></i> Desempate</span>@endif</div>
                <div class="text-muted"><x-badge tipo="avaliacao" :valor="$av->status" />@if($av->prazo) · prazo {{ $av->prazo->format('d/m/Y') }} @endif</div>
            </div>
            @if(in_array($av->status, ['pendente', 'em_andamento'], true))
                <form action="{{ route('gestao.trabalhos.avaliadores.destroy', [$trabalho, $av]) }}" method="POST"
                      onsubmit="return confirm('Remover {{ addslashes($av->avaliador->name) }} deste trabalho?{{ $av->status === 'em_andamento' ? ' A avaliação em andamento (rascunho) será descartada.' : '' }}')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" title="Remover avaliador"><i class="fa-solid fa-user-minus"></i> Remover</button>
                </form>
            @endif
        </div>
    @empty
        <p class="small text-muted">Nenhum avaliador atribuído.</p>
    @endforelse

    @if($podeAtribuir)
        <form action="{{ route('gestao.trabalhos.avaliadores.store', $trabalho) }}" method="POST" class="mt-3">
            @csrf
            <label class="small fw-bold text-secondary" for="selAvaliadores">Atribuir avaliadores</label>
            @if($avaliadores->isNotEmpty())
                @php
                    $livres = $avaliadores->filter(fn ($u) => !$u->impedimento);
                    $atribuidosJa = $avaliadores->filter(fn ($u) => in_array($u->impedimento['tipo'] ?? null, ['atribuido', 'recusou'], true));
                    $comConflito = $avaliadores->filter(fn ($u) => ($u->impedimento['tipo'] ?? null) === 'conflito');
                @endphp
                <select name="avaliadores[]" id="selAvaliadores" class="form-select js-select-avaliadores" multiple data-placeholder="Busque e selecione os avaliadores…" style="width:100%">
                    @if($livres->isNotEmpty())
                        <optgroup label="Disponíveis">
                            @foreach($livres as $u) <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->carga }} em aberto)</option> @endforeach
                        </optgroup>
                    @endif
                    @if($atribuidosJa->isNotEmpty())
                        <optgroup label="Já atribuídos a este trabalho">
                            @foreach($atribuidosJa as $u) <option value="{{ $u->id }}" disabled data-tipo="{{ $u->impedimento['tipo'] }}">{{ $u->name }} — {{ $u->impedimento['tipo'] === 'recusou' ? 'recusou a avaliação' : 'já atribuído' }}</option> @endforeach
                        </optgroup>
                    @endif
                    @if($comConflito->isNotEmpty())
                        <optgroup label="Conflito de interesse (não pode avaliar)">
                            @foreach($comConflito as $u) <option value="{{ $u->id }}" disabled data-tipo="conflito">{{ $u->name }} — {{ $u->impedimento['motivo'] }}</option> @endforeach
                        </optgroup>
                    @endif
                </select>
                @if($livres->isEmpty())
                    <div class="alert alert-warning small py-2 mt-2 mb-0"><i class="fa-solid fa-triangle-exclamation me-1"></i> Nenhum avaliador disponível para este trabalho: {{ $atribuidosJa->count() }} já atribuído(s)/recusou e {{ $comConflito->count() }} com conflito de interesse.</div>
                @elseif($atribuidosJa->isNotEmpty() || $comConflito->isNotEmpty())
                    <div class="small mt-2">
                        @if($atribuidosJa->isNotEmpty()) <span class="sb" style="background:#e0e7ff;color:#4338ca;border-color:#a5b4fc;"><i class="fa-solid fa-user-check"></i> {{ $atribuidosJa->count() }} já atribuído(s)</span> @endif
                        @if($comConflito->isNotEmpty()) <span class="sb" style="background:#fee2e2;color:#b91c1c;border-color:#fca5a5;"><i class="fa-solid fa-user-slash"></i> {{ $comConflito->count() }} com conflito</span> @endif
                    </div>
                @endif
                <div class="form-text">Digite para filtrar. Quem já está atribuído ou tem conflito (ex.: é autor do trabalho e não pode se avaliar) aparece desabilitado, com o motivo.</div>
            @else
                <p class="small text-muted">Não há avaliadores disponíveis. Designe avaliadores em "Papéis" do evento.</p>
            @endif

            @if($avaliadores->isNotEmpty())
                <label class="small fw-bold text-secondary mt-2">Prazo (opcional)</label>
                <input type="date" name="prazo" class="form-control form-control-sm mb-2" min="{{ now()->addDay()->format('Y-m-d') }}">
                <button class="btn btn-sm btn-outline-success w-100 fw-bold" @disabled($livres->isEmpty())><i class="fa-solid fa-user-plus me-1"></i> Atribuir selecionados</button>
            @endif
        </form>
    @else
        <p class="small text-muted mt-2 mb-0">Atribuição disponível apenas em triagem ou avaliação.</p>
    @endif
</div>


{{-- Resultado das avaliações --}}
@if($trabalho->avaliacoes->isNotEmpty())
<div class="glass-card bg-white mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-success m-0"><i class="fa-solid fa-chart-simple me-1"></i> Resultado das avaliações</h6>
        @if($resultado['rodada'] > 1) <span class="badge bg-info text-dark">Rodada {{ $resultado['rodada'] }}</span> @endif
    </div>

    <div class="d-flex gap-3 mb-3">
        <div class="text-center flex-fill border rounded-3 p-2">
            <div class="small text-muted">Nota média</div>
            <div class="fs-4 fw-bold {{ $resultado['media'] !== null && $resultado['media'] >= $resultado['notaMinima'] ? 'text-success' : 'text-dark' }}">
                {{ $resultado['media'] !== null ? number_format($resultado['media'], 2, ',', '.') : '—' }}
            </div>
            <div class="small text-muted">mínimo {{ rtrim(rtrim(number_format($resultado['notaMinima'], 2, ',', ''), '0'), ',') }}</div>
        </div>
        <div class="text-center flex-fill border rounded-3 p-2">
            <div class="small text-muted">Concluídas</div>
            <div class="fs-4 fw-bold">{{ $resultado['concluidas'] }}/{{ $trabalho->avaliacoes->where('rodada', $resultado['rodada'])->where('status', '!=', 'recusada')->count() }}</div>
        </div>
    </div>

    @if($resultado['divergenciaAlta'])
        <div class="alert alert-warning small py-2">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            Divergência alta entre avaliadores ({{ number_format($resultado['divergencia'], 2, ',', '.') }} pontos).
            Adicione um <strong>avaliador de desempate</strong>.
            @php $livresDesempate = $avaliadores->filter(fn ($u) => !$u->impedimento); @endphp
            @if(!$resultado['motivoSemDesempate'])
                @if($livresDesempate->isEmpty())
                    <div class="mt-2 text-danger">Nenhum avaliador disponível para o desempate (todos já atribuídos ou com conflito de interesse).</div>
                @else
                    <form action="{{ route('gestao.trabalhos.desempate', $trabalho) }}" method="POST" class="mt-2">
                        @csrf
                        <select name="avaliador_id" class="form-select js-select-avaliadores" data-placeholder="Escolha o avaliador de desempate…" style="width:100%" required>
                            <option value=""></option>
                            @foreach($livresDesempate as $u) <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->carga }} em aberto)</option> @endforeach
                        </select>
                        <input type="date" name="prazo" class="form-control form-control-sm mt-2" min="{{ now()->addDay()->format('Y-m-d') }}" title="Prazo (opcional)">
                        <button class="btn btn-sm btn-warning w-100 fw-bold mt-2" data-confirmar="Adicionar avaliador de desempate? @if($trabalho->status === 'avaliado') O trabalho voltará para 'Em avaliação' até ele concluir. @endif"><i class="fa-solid fa-scale-balanced"></i> Adicionar avaliador de desempate</button>
                    </form>
                @endif
            @elseif(str_contains($resultado['motivoSemDesempate'], 'Já existe'))
                <div class="mt-2"><i class="fa-solid fa-scale-balanced me-1"></i> Avaliador de desempate já atribuído: aguardando a avaliação.</div>
            @endif
        </div>
    @endif

    @foreach($trabalho->avaliacoes->where('rodada', $resultado['rodada']) as $av)
        <details class="small mb-2 border rounded-3 p-2">
            <summary class="fw-bold">
                {{ $av->avaliador->name }} @if($av->desempate)<span class="sb" style="background:#fef3c7;color:#b45309;border-color:#fcd34d;"><i class="fa-solid fa-scale-balanced"></i> Desempate</span>@endif
                <span class="fw-normal text-muted">
                    — {{ $av->statusRotulo() }}@if($av->status === 'concluida'), nota {{ number_format($av->nota_final, 2, ',', '.') }}, {{ $av->recomendacaoRotulo() }}@endif
                </span>
            </summary>
            @if($av->status === 'concluida')
                <table class="table table-sm mt-2 mb-2">
                    @foreach($av->notas as $n)
                        <tr><td>{{ $n->criterio->nome }}</td><td class="text-end">{{ rtrim(rtrim(number_format($n->nota, 2, ',', ''), '0'), ',') }}/{{ rtrim(rtrim(number_format($n->criterio->nota_max, 2, ',', ''), '0'), ',') }}</td></tr>
                        @if($n->comentario) <tr><td colspan="2" class="text-muted">{{ $n->comentario }}</td></tr> @endif
                    @endforeach
                </table>
                <div class="fw-bold">Parecer</div>
                <p class="texto-quebra">{{ $av->parecer ?: '(sem parecer escrito)' }}</p>
                @if($av->parecer_confidencial)
                    <div class="fw-bold text-danger">Confidencial (só gestão)</div>
                    <p class="texto-quebra">{{ $av->parecer_confidencial }}</p>
                @endif
            @elseif($av->status === 'recusada')
                <p class="mt-2 mb-0"><strong>Motivo da recusa:</strong> {{ $av->parecer_confidencial }}</p>
            @else
                <p class="mt-2 mb-0 text-muted">Ainda sem parecer.</p>
            @endif
        </details>
    @endforeach

    <div class="d-grid gap-2 mt-3">
        @if($resultado['podeEncerrar'])
            <form action="{{ route('gestao.trabalhos.encerrar', $trabalho) }}" method="POST"
                  onsubmit="return confirm('Encerrar a avaliação sem aguardar os demais avaliadores?')">
                @csrf <button class="btn btn-sm btn-outline-secondary w-100 fw-bold">Encerrar avaliação agora</button>
            </form>
        @endif
        @if($resultado['podeReavaliar'])
            <form action="{{ route('gestao.trabalhos.reavaliar', $trabalho) }}" method="POST">
                @csrf <button class="btn btn-sm btn-outline-primary w-100 fw-bold">Enviar versão corrigida aos mesmos avaliadores</button>
            </form>
        @endif
    </div>
</div>
@endif

{{-- Trabalho reenviado após correção: aprovação direta --}}
@if($trabalho->status === 'resubmetido')
<div class="glass-card bg-white mb-4 border border-success">
    <h6 class="fw-bold text-success mb-2"><i class="fa-solid fa-rotate me-1"></i> Reenviado após correção</h6>
    <p class="small text-muted mb-2">O autor enviou a versão corrigida. Se as ressalvas foram atendidas, aprove o trabalho agora; se preferir, reavalie ou reprove na <em>Decisão final</em> abaixo.</p>
    <form action="{{ route('gestao.trabalhos.decisao', $trabalho) }}" method="POST">
        @csrf
        <input type="hidden" name="decisao" value="aprovado">
        <label class="small fw-bold text-secondary">Mensagem ao autor (opcional)</label>
        <textarea name="justificativa" rows="2" class="form-control form-control-sm mb-2" placeholder="Ex.: Ressalvas atendidas. Parabéns!"></textarea>
        <button class="btn btn-success w-100 fw-bold" data-confirmar="Aprovar este trabalho? O status passa para Aprovado e os autores serão avisados por e-mail."><i class="fa-solid fa-circle-check"></i> Aprovar trabalho</button>
    </form>
</div>
@endif

{{-- Decisão --}}
@if($resultado['podeDecidir'])
<div class="glass-card bg-white mb-4" x-data="{ decisao: '{{ old('decisao', $resultado['media'] !== null && $resultado['media'] >= $resultado['notaMinima'] ? 'aprovado' : 'reprovado') }}' }">
    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-gavel me-1"></i> Decisão final</h6>
    @if($resultado['media'] !== null)
        <p class="small text-muted">Sugestão pela nota: {{ $resultado['media'] >= $resultado['notaMinima'] ? 'aprovar' : 'reprovar' }} (média {{ number_format($resultado['media'], 2, ',', '.') }}). A decisão é sempre sua.</p>
    @endif
    <form action="{{ route('gestao.trabalhos.decisao', $trabalho) }}" method="POST">
        @csrf
        <select name="decisao" class="form-select form-select-sm mb-2" x-model="decisao">
            <option value="aprovado">Aprovar</option>
            <option value="aprovado_com_ressalvas">Aprovar com ressalvas (solicita correção)</option>
            <option value="reprovado">Reprovar</option>
        </select>
        <div class="mb-2">
            <label class="small fw-bold text-secondary">Prazo para correção <span class="fw-normal">(obrigatório ao aprovar com ressalvas)</span></label>
            <input type="date" name="prazo_correcao" class="form-control form-control-sm" min="{{ now()->addDay()->format('Y-m-d') }}" value="{{ old('prazo_correcao') }}">
        </div>
        <label class="small fw-bold text-secondary">Mensagem ao autor <span class="fw-normal">(obrigatória ao reprovar ou aprovar com ressalvas)</span></label>
        <textarea name="justificativa" rows="4" class="form-control form-control-sm mb-2" placeholder="Orientações, ressalvas ou motivo da decisão">{{ old('justificativa') }}</textarea>
        <button class="btn btn-sm btn-primary-custom w-100 fw-bold" onclick="return confirm('Registrar a decisão? Os autores serão avisados por e-mail.')">Registrar decisão</button>
    </form>
</div>
@elseif($trabalho->decisao)
<div class="glass-card bg-white mb-4">
    <h6 class="fw-bold text-success mb-2"><i class="fa-solid fa-gavel me-1"></i> Decisão registrada</h6>
    <p class="small mb-1"><strong>{{ ['aprovado' => 'Aprovado', 'aprovado_com_ressalvas' => 'Aprovado com ressalvas', 'reprovado' => 'Reprovado'][$trabalho->decisao] }}</strong>
        em {{ $trabalho->decisao_em?->format('d/m/Y H:i') }}</p>
    @if($trabalho->decisao_justificativa) <p class="small text-muted" style="white-space: pre-line;">{{ $trabalho->decisao_justificativa }}</p> @endif

    @if($trabalho->status === 'aguardando_correcao')
        <p class="small mb-2">Prazo de correção: <strong>{{ $trabalho->prazo_correcao?->format('d/m/Y') }}</strong></p>
        <form action="{{ route('gestao.trabalhos.prorrogar', $trabalho) }}" method="POST" class="d-flex gap-2">
            @csrf
            <input type="date" name="prazo_correcao" class="form-control form-control-sm" min="{{ now()->addDay()->format('Y-m-d') }}" required>
            <button class="btn btn-sm fw-bold text-nowrap btn-outline-warning"><i class="fa-solid fa-calendar-plus"></i> Prorrogar</button>
        </form>
    @endif
</div>
@endif


{{-- Apresentação e certificados --}}
@if($trabalho->status === 'aprovado' || $trabalho->certificados->isNotEmpty())
<div class="glass-card bg-white mb-4" id="card-apresentacao">
    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-person-chalkboard me-1"></i> Apresentação</h6>

    <div class="small mb-2">
        @if($trabalho->apresentado)
            <x-badge tipo="sim-nao" valor="apresentado" />
            <span class="text-muted">{{ $trabalho->apresentado_em?->format('d/m/Y H:i') }} · {{ match ($trabalho->apresentacao_origem) { 'frequencia' => 'confirmado pela frequência', 'avaliacao' => 'confirmado pela avaliação da apresentação', default => 'registrado pela gestão' } }}</span>
        @else
            <x-badge tipo="sim-nao" valor="nao_apresentado" />
        @endif
    </div>

    @if($trabalho->status === 'aprovado')
        @php $apresentadores = $trabalho->autores->where('apresentador', true); @endphp
        <div class="mb-3 pb-2 border-bottom">
            <label class="small fw-bold text-secondary">Apresentador(es)</label>
            <div class="small mb-1">
                @forelse($apresentadores as $ap) <span class="sb" style="background:#e0e7ff;color:#4338ca;border-color:#a5b4fc;"><i class="fa-solid fa-person-chalkboard"></i> {{ $ap->nome_completo }}</span> @empty <span class="text-muted">Nenhum apresentador definido.</span> @endforelse
                @if(in_array('vincular_apresentador', $pendenciasTrabalho ?? [], true))
                    <div class="text-danger mt-1"><i class="fa-solid fa-circle-exclamation me-1"></i> Nenhum apresentador com conta vinculada: a presença na sessão não confirmará a apresentação sozinha (o apresentador precisa de conta com o mesmo CPF/e-mail do autor). A gestão ainda pode marcar manualmente.</div>
                @endif
            </div>
            <div class="small text-secondary mb-1">Autores do trabalho — escolha quem apresenta:</div>
            @foreach($trabalho->autores as $au)
                <form action="{{ route('gestao.trabalhos.apresentador', $trabalho) }}" method="POST" class="d-flex align-items-center justify-content-between gap-2 mb-1">
                    @csrf
                    <input type="hidden" name="autor_id" value="{{ $au->id }}">
                    <span class="small">{{ $au->nome_completo }} <span class="text-muted">({{ $au->tipoRotulo() }})</span></span>
                    @if($au->apresentador)
                        <input type="hidden" name="acao" value="remover">
                        <button class="btn btn-sm btn-outline-danger text-nowrap" data-confirmar="Remover {{ addslashes($au->nome_completo) }} como apresentador(a)?"><i class="fa-solid fa-user-minus"></i> Remover</button>
                    @else
                        <input type="hidden" name="acao" value="definir">
                        <button class="btn btn-sm btn-outline-primary text-nowrap"><i class="fa-solid fa-user-tag"></i> Tornar apresentador</button>
                    @endif
                </form>
            @endforeach
        </div>
        <form action="{{ route('gestao.trabalhos.apresentacao', $trabalho) }}" method="POST" class="mb-2">
            @csrf
            <label class="small fw-bold text-secondary">Sessão de apresentação</label>
            <div class="d-flex gap-2">
                <select name="atividade_id" class="form-select form-select-sm">
                    <option value="">— sem sessão —</option>
                    @foreach($certificacao['atividades'] as $at)
                        <option value="{{ $at->id }}" @selected($trabalho->atividade_id === $at->id)>{{ $at->titulo }} ({{ $at->data_inicio->format('d/m H:i') }})</option>
                    @endforeach
                </select>
                <button name="acao" value="sessao" class="btn btn-sm fw-bold btn-outline-success"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
            </div>
            <div class="form-text">Com sessão definida, a presença do autor apresentador na atividade confirma a apresentação automaticamente.</div>
        </form>

        <form action="{{ route('gestao.trabalhos.apresentacao', $trabalho) }}" method="POST">
            @csrf
            @if($trabalho->apresentado)
                <button name="acao" value="desmarcar" class="btn btn-sm btn-light text-danger w-100" onclick="return confirm('Desmarcar a apresentação?')">Desmarcar apresentação</button>
            @else
                <button name="acao" value="marcar" class="btn btn-sm btn-success w-100 fw-bold"><i class="fa-solid fa-check me-1"></i> Marcar como apresentado</button>
            @endif
        </form>
    @endif

</div>

{{-- Avaliadores da apresentação: só depois de aprovado na parte escrita --}}
@if($trabalho->status === 'aprovado')
    @if($trabalho->evento->trabalhoConfig?->avaliacao_apresentacao_ativa)
        @php
            $avsAp = $trabalho->avaliacoesApresentacao->load('avaliador');
            $livresAp = $avaliadoresAp->filter(fn ($u) => !$u->impedimento);
            $impedidosAp = $avaliadoresAp->filter(fn ($u) => $u->impedimento);
        @endphp
        <div class="glass-card bg-white mb-4" id="card-avaliadores-apresentacao">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-success m-0"><i class="fa-solid fa-star me-1"></i> Avaliadores da apresentação</h6>
                @if($trabalho->nota_apresentacao !== null) <span class="sb" style="background:#dcfce7;color:#15803d;border-color:#86efac;">Nota {{ number_format($trabalho->nota_apresentacao, 2, ',', '.') }}</span> @endif
            </div>
            @forelse($avsAp as $av)
                <div class="d-flex justify-content-between align-items-center small mb-2 pb-2 border-bottom">
                    <div>
                        <div class="fw-bold">{{ $av->avaliador->name }}</div>
                        <x-badge tipo="avaliacao" :valor="$av->status" :extra="$av->nota_final !== null ? number_format($av->nota_final, 2, ',', '.') : null" />
                        @if($av->prazo && $av->aberta()) <span class="text-muted">· prazo {{ $av->prazo->format('d/m/Y') }}</span> @endif
                    </div>
                    @if($av->status !== 'concluida')
                        <form action="{{ route('gestao.trabalhos.apresentacoes.remover', $av) }}" method="POST">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" data-confirmar="Remover {{ addslashes($av->avaliador->name) }} da avaliação desta apresentação?"><i class="fa-solid fa-user-minus"></i> Remover</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="small text-muted">Nenhum avaliador atribuído à apresentação.</p>
            @endforelse

            @if($avaliadoresAp->isEmpty())
                <p class="small text-muted mb-0">Não há avaliadores designados no evento. Designe em "Papéis".</p>
            @else
                <form action="{{ route('gestao.trabalhos.apresentacoes.atribuir', $trabalho) }}" method="POST" class="mt-2">
                    @csrf
                    <label class="small fw-bold text-secondary" for="selAvaliadoresAp">Atribuir avaliadores da apresentação</label>
                    <select name="avaliadores[]" id="selAvaliadoresAp" class="form-select js-select-avaliadores" multiple data-placeholder="Busque e selecione os avaliadores…" style="width:100%">
                        @if($livresAp->isNotEmpty())
                            <optgroup label="Disponíveis">
                                @foreach($livresAp as $u) <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->carga }} em aberto)</option> @endforeach
                            </optgroup>
                        @endif
                        @foreach(['atribuido' => 'Já atribuídos à apresentação', 'conflito' => 'Conflito de interesse (não pode avaliar)'] as $tipo => $rotuloGrupo)
                            @php $grupo = $impedidosAp->filter(fn ($u) => $u->impedimento['tipo'] === $tipo); @endphp
                            @if($grupo->isNotEmpty())
                                <optgroup label="{{ $rotuloGrupo }}">
                                    @foreach($grupo as $u) <option value="{{ $u->id }}" disabled data-tipo="{{ $tipo }}">{{ $u->name }} — {{ $tipo === 'atribuido' ? 'já atribuído' : $u->impedimento['motivo'] }}</option> @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                    @if($livresAp->isEmpty())
                        <div class="alert alert-warning small py-2 mt-2 mb-0"><i class="fa-solid fa-triangle-exclamation me-1"></i> Nenhum avaliador disponível: todos já atribuídos ou com conflito de interesse.</div>
                    @else
                        <label class="small fw-bold text-secondary mt-2" for="prazoAp">Prazo (opcional)</label>
                        <input type="date" name="prazo" id="prazoAp" class="form-control form-control-sm mb-2" min="{{ now()->addDay()->format('Y-m-d') }}">
                        <button class="btn btn-sm btn-outline-success w-100 fw-bold"><i class="fa-solid fa-user-plus me-1"></i> Atribuir à apresentação</button>
                    @endif
                    <div class="form-text">A nota da apresentação é independente de "apresentado": avaliar não marca presença, e marcar presença não dá nota.</div>
                </form>
            @endif
        </div>
    @else
        <div class="glass-card bg-white mb-4 small text-muted">
            <i class="fa-solid fa-star me-1"></i> A avaliação das apresentações não está ativada neste evento.
            <a href="{{ route('gestao.trabalhos.apresentacoes.index', $trabalho->evento_id) }}">Ativar e configurar</a> para atribuir avaliadores à apresentação.
        </div>
    @endif
@endif

<div class="glass-card bg-white mb-4">
    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-award me-1"></i> Certificados</h6>

    @forelse($trabalho->certificados as $c)
        <div class="small mb-2 pb-2 border-bottom">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div class="fw-bold">{{ $c->autor->nome_completo }}</div>
                    <div class="text-muted">{{ $c->funcaoRotulo() }} · {{ $c->hash_validacao }} · {{ $c->downloads }} download(s)</div>
                    @if($c->revogado()) <x-badge tipo="certificado" valor="revogado" :extra="$c->revogado_motivo" /> @endif
                </div>
                @unless($c->revogado())
                    <a href="{{ route('certificado.trabalho.download', $c->hash_validacao) }}" target="_blank" class="btn btn-sm btn-light" title="Baixar"><i class="fa-solid fa-download"></i></a>
                @endunless
            </div>
            @unless($c->revogado())
                <form action="{{ route('gestao.trabalhos.certificados.revogar', $c) }}" method="POST" class="d-flex gap-1 mt-1">
                    @csrf
                    <input type="text" name="motivo" class="form-control form-control-sm" placeholder="Motivo da revogação" maxlength="250" required>
                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Revogar este certificado?')"><i class="fa-solid fa-ban"></i> Revogar</button>
                </form>
            @endunless
        </div>
    @empty
        <p class="small text-muted">Nenhum certificado emitido.</p>
    @endforelse

    @if($certificacao['motivo'])
        <div class="alert alert-light border small py-2 mb-0">Ainda não elegível: {{ $certificacao['motivo'] }}.</div>
    @elseif($trabalho->certificados->count() < $trabalho->autores->count())
        <form action="{{ route('gestao.trabalhos.certificados.emitir', $trabalho) }}" method="POST">
            @csrf
            <button class="btn btn-sm btn-primary-custom w-100 fw-bold"><i class="fa-solid fa-paper-plane me-1"></i> Emitir certificados e avisar autores</button>
        </form>
    @endif
</div>
@endif

@once('assets-select2-avaliadores')
    @if(($podeAtribuir || $trabalho->status === 'aprovado') && $avaliadores->isNotEmpty())
        <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-5-theme/1.3.0/select2-bootstrap-5-theme.min.css" rel="stylesheet">
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/i18n/pt-BR.min.js"></script>
        <script>
            window.addEventListener('load', function () {
                if (!window.jQuery || !jQuery.fn.select2) return;
                jQuery('.js-select-avaliadores').each(function () {
                    jQuery(this).select2({
                        theme: 'bootstrap-5', language: 'pt-BR', width: '100%', closeOnSelect: false, placeholder: this.dataset.placeholder, allowClear: true,
                        // Impedidos aparecem em cor própria, com ícone e o motivo no próprio item
                        templateResult: function (o) {
                            const tipo = o.element && o.element.dataset ? o.element.dataset.tipo : null;
                            if (!tipo) return o.text;
                            const estilo = { conflito: ['#b91c1c', 'fa-user-slash'], atribuido: ['#4338ca', 'fa-user-check'], recusou: ['#b45309', 'fa-hand'] }[tipo] || ['#64748b', 'fa-circle'];
                            return jQuery('<span style="color:' + estilo[0] + ';"><i class="fa-solid ' + estilo[1] + ' me-1"></i></span>').append(document.createTextNode(o.text));
                        },
                    });
                });
            });
        </script>
    @endif
@endonce
