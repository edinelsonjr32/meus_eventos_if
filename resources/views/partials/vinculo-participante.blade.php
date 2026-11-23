@php
    $contas = app(\App\Services\ParticipanteDaConta::class);
    $p = $participanteLogado ?? null;
    $pendente = $contas->pendente($p);
    $ehExterno = $contas->pendenteEhExternoNaoConfirmado($p);
    $rotulos = ['aluno' => 'Aluno', 'servidor' => 'Servidor', 'externo' => 'Comunidade externa'];
@endphp

@if(!$p)
    {{-- Conta ainda sem cadastro de participante: escolhe o tipo (aluno por padrão) --}}
    <x-campos-vinculo tipo="aluno" />

@elseif($ehExterno)
    {{-- Cadastro antigo como externo, nunca confirmado: pergunta uma única vez --}}
    <div class="border border-warning rounded-3 p-3 mb-3 bg-warning-subtle" x-data="{ decisao: @js(old('confirmacao_vinculo', '')) }">
        <div class="fw-bold mb-1"><i class="fa-solid fa-circle-question me-1"></i> Confirme seu tipo de cadastro</div>
        <p class="small mb-2">Seu cadastro consta como <strong>Comunidade externa</strong>. Isso está correto? (Pedimos uma única vez: os relatórios dos professores usam essa informação.)</p>
        <div class="form-check"><input class="form-check-input" type="radio" name="confirmacao_vinculo" value="externo" id="cf-ext" x-model="decisao" required><label class="form-check-label" for="cf-ext">Sim, sou da comunidade externa</label></div>
        <div class="form-check mb-2"><input class="form-check-input" type="radio" name="confirmacao_vinculo" value="corrigir" id="cf-cor" x-model="decisao"><label class="form-check-label" for="cf-cor">Não, sou aluno ou servidor do IFPA</label></div>
        <template x-if="decisao === 'corrigir'"><div class="mt-3"><x-campos-vinculo tipo="aluno" /></div></template>
        @error('confirmacao_vinculo') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
        <div class="small text-muted mt-2"><i class="fa-solid fa-circle-info me-1"></i> Depois de responder, para mudar o tipo de cadastro será preciso ir em <a href="{{ route('profile.edit') }}" target="_blank">Minha Conta › Editar dados do perfil</a>.</div>
    </div>

@elseif($pendente)
    {{-- Aluno/servidor com matrícula ou turma faltando: completa os dados --}}
    <div class="border border-warning rounded-3 p-3 mb-3 bg-warning-subtle">
        <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Complete seu cadastro</div>
        <p class="small mb-2">Seu cadastro de <strong>{{ $rotulos[$p->tipo_vinculo] ?? $p->tipo_vinculo }}</strong> está sem {{ $p->tipo_vinculo === 'aluno' ? 'matrícula ou turma' : 'a matrícula' }}. Informe abaixo (uma única vez).</p>
        <input type="hidden" name="confirmacao_vinculo" value="corrigir">
        <x-campos-vinculo :tipo="$p->tipo_vinculo" :matricula="$p->matricula" :turma-id="$p->turma_id" />
        <div class="small text-muted"><i class="fa-solid fa-circle-info me-1"></i> Para mudar o tipo de cadastro depois, use <a href="{{ route('profile.edit') }}" target="_blank">Minha Conta › Editar dados do perfil</a>.</div>
    </div>

@else
    {{-- Cadastro em dia: só mostra; a mudança é feita no perfil --}}
    <div class="mb-3">
        <label class="fw-bold small text-secondary text-uppercase mb-1">Tipo de cadastro</label>
        <div class="form-control bg-light" style="height:auto;">
            <strong>{{ $rotulos[$p->tipo_vinculo] ?? $p->tipo_vinculo }}</strong>
            @if($p->matricula) · matrícula {{ $p->matricula }} @endif
            @if($p->turma) · {{ $p->turma->nome_completo }} @endif
        </div>
        <div class="form-text">Para alterar o tipo de cadastro, vá em <a href="{{ route('profile.edit') }}" target="_blank">Minha Conta › Editar dados do perfil</a>.</div>
    </div>
@endif
