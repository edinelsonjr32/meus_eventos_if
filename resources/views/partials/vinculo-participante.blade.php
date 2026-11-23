<style>
    .vp-card { position: relative; border: 1px solid #d1fae5; border-radius: 16px; background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 70%); padding: 1rem 1rem 1rem 1.15rem; margin-bottom: 1rem; box-shadow: 0 6px 18px -10px rgba(5, 150, 105, .45); overflow: hidden; }
    .vp-card::before { content: ""; position: absolute; inset: 0 auto 0 0; width: 5px; background: linear-gradient(180deg, #10b981, #047857); }
    .vp-topo { display: flex; align-items: center; justify-content: center; gap: .8rem; text-align: left; }
    .vp-ico { flex: 0 0 auto; width: 46px; height: 46px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: #fff; background: linear-gradient(135deg, #10b981, #047857); box-shadow: 0 6px 12px -6px rgba(4, 120, 87, .8); }
    .vp-rot { font-size: .68rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #64748b; }
    .vp-tipo { font-weight: 800; font-size: 1.05rem; color: #065f46; line-height: 1.2; }
    .vp-chips { display: flex; flex-wrap: wrap; justify-content: center; gap: .4rem; margin: .8rem 0 .2rem; }
    .vp-chip { display: inline-flex; align-items: center; gap: .35rem; background: #fff; border: 1px solid #d1fae5; color: #334155; border-radius: 999px; padding: .25rem .7rem; font-size: .8rem; font-weight: 600; }
    .vp-chip i { color: #059669; }
    .vp-acao { display: flex; flex-direction: column; align-items: center; gap: .65rem; margin-top: .9rem; padding-top: .9rem; border-top: 1px dashed #bbf7d0; text-align: center; }
    .vp-botoes { display: flex; flex-wrap: wrap; justify-content: center; gap: .6rem; width: 100%; }
    .vp-btn, .vp-btn-sec { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; padding: .6rem 1.2rem; border-radius: 10px; font-weight: 700; font-size: .88rem; text-decoration: none !important; cursor: pointer; transition: transform .15s, box-shadow .15s, background .15s; min-width: 190px; }
    .vp-btn { color: #fff !important; border: 0; background: linear-gradient(135deg, #10b981, #047857); box-shadow: 0 8px 16px -8px rgba(4, 120, 87, .9); }
    .vp-btn:hover { transform: translateY(-1px); box-shadow: 0 12px 20px -8px rgba(4, 120, 87, .95); }
    .vp-btn-sec { color: #047857 !important; background: #fff; border: 2px solid #34d399; }
    .vp-btn-sec:hover { background: #ecfdf5; transform: translateY(-1px); }
    .vp-btn-sec i { transition: transform .4s; } .vp-btn-sec:hover i { transform: rotate(180deg); }
    .vp-btn:focus-visible, .vp-btn-sec:focus-visible { outline: 3px solid #a7f3d0; outline-offset: 2px; }
    .vp-dica { font-size: .78rem; color: #64748b; max-width: 340px; }
    .vp-alerta { border-radius: 16px; border: 1px solid #fcd34d; background: #fffbeb; padding: 1rem; margin-bottom: 1rem; }
</style>
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
    <div class="vp-alerta" x-data="{ decisao: @js(old('confirmacao_vinculo', '')) }">
        <div class="fw-bold mb-1"><i class="fa-solid fa-circle-question me-1"></i> Confirme seu tipo de cadastro</div>
        <p class="small mb-2">Seu cadastro consta como <strong>Comunidade externa</strong>. Isso está correto? (Pedimos uma única vez: os relatórios dos professores usam essa informação.)</p>
        <div class="form-check"><input class="form-check-input" type="radio" name="confirmacao_vinculo" value="externo" id="cf-ext" x-model="decisao" required><label class="form-check-label" for="cf-ext">Sim, sou da comunidade externa</label></div>
        <div class="form-check mb-2"><input class="form-check-input" type="radio" name="confirmacao_vinculo" value="corrigir" id="cf-cor" x-model="decisao"><label class="form-check-label" for="cf-cor">Não, sou aluno ou servidor do IFPA</label></div>
        <template x-if="decisao === 'corrigir'"><div class="mt-3"><x-campos-vinculo tipo="aluno" /></div></template>
        @error('confirmacao_vinculo') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
        <div class="vp-acao"><div class="vp-botoes"><a href="{{ route('profile.edit') }}" target="_blank" rel="noopener" class="vp-btn"><i class="fa-solid fa-user-pen"></i> Editar dados do perfil</a></div><span class="vp-dica">Depois de responder, o tipo só muda em <strong>Minha Conta</strong>.</span></div>
    </div>

@elseif($pendente)
    {{-- Aluno/servidor com matrícula ou turma faltando: completa os dados --}}
    <div class="vp-alerta">
        <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Complete seu cadastro</div>
        <p class="small mb-2">Seu cadastro de <strong>{{ $rotulos[$p->tipo_vinculo] ?? $p->tipo_vinculo }}</strong> está sem {{ $p->tipo_vinculo === 'aluno' ? 'matrícula ou turma' : 'a matrícula' }}. Informe abaixo (uma única vez).</p>
        <input type="hidden" name="confirmacao_vinculo" value="corrigir">
        <x-campos-vinculo :tipo="$p->tipo_vinculo" :matricula="$p->matricula" :turma-id="$p->turma_id" />
        <div class="vp-acao"><div class="vp-botoes"><a href="{{ route('profile.edit') }}" target="_blank" rel="noopener" class="vp-btn"><i class="fa-solid fa-user-pen"></i> Editar dados do perfil</a></div><span class="vp-dica">Para mudar o tipo de cadastro depois, use <strong>Minha Conta</strong>.</span></div>
    </div>

@else
    {{-- Cadastro em dia: só mostra; a mudança é feita no perfil --}}
    @php $icone = ['aluno' => 'fa-graduation-cap', 'servidor' => 'fa-id-badge', 'externo' => 'fa-user'][$p->tipo_vinculo] ?? 'fa-user'; @endphp
    <div class="vp-card">
        <div class="vp-topo">
            <div class="vp-ico"><i class="fa-solid {{ $icone }}"></i></div>
            <div>
                <div class="vp-rot">Seu cadastro</div>
                <div class="vp-tipo">{{ $rotulos[$p->tipo_vinculo] ?? $p->tipo_vinculo }}</div>
            </div>
        </div>
        @if($p->matricula || $p->turma)
            <div class="vp-chips">
                @if($p->matricula)<span class="vp-chip"><i class="fa-solid fa-hashtag"></i> Matrícula {{ $p->matricula }}</span>@endif
                @if($p->turma?->curso)<span class="vp-chip"><i class="fa-solid fa-book-open"></i> {{ $p->turma->curso->nome }}</span>@endif
                @if($p->turma)<span class="vp-chip"><i class="fa-solid fa-users"></i> Turma {{ $p->turma->ano }}</span>@endif
            </div>
        @endif
        <div class="vp-acao">
            <div class="vp-botoes">
                <a href="{{ route('profile.edit') }}" target="_blank" rel="noopener" class="vp-btn"><i class="fa-solid fa-user-pen"></i> Editar dados do perfil</a>
                <button type="button" class="vp-btn-sec" onclick="location.reload()" title="Recarrega a tela com os dados que você acabou de corrigir"><i class="fa-solid fa-rotate"></i> Já corrigi, atualizar</button>
            </div>
            <span class="vp-dica">Dados errados? Corrija em <strong>Minha Conta</strong> e volte aqui.</span>
        </div>
    </div>
@endif
