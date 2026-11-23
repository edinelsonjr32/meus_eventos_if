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
    .vp-modal-fundo { position: fixed; inset: 0; z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(15, 23, 42, .55); backdrop-filter: blur(2px); }
    .vp-modal { width: 100%; max-width: 520px; max-height: 92vh; overflow-y: auto; background: #fff; border-radius: 16px; padding: 1.25rem; box-shadow: 0 30px 60px -20px rgba(15, 23, 42, .6); }
    .vp-modal-topo { display: flex; align-items: center; justify-content: space-between; margin-bottom: .25rem; }
    .vp-modal-topo h5 { margin: 0; font-weight: 800; color: #065f46; }
    .vp-x { border: 0; background: #f1f5f9; width: 34px; height: 34px; border-radius: 10px; font-size: 1.3rem; line-height: 1; color: #475569; }
    .vp-x:hover { background: #e2e8f0; }
    .vp-modal-rodape { display: flex; flex-wrap: wrap; justify-content: center; gap: .6rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed #bbf7d0; }
    .vp-btn[disabled] { opacity: .7; cursor: wait; transform: none; }
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
        <div class="vp-acao"><div class="vp-botoes"><button type="button" class="vp-btn" onclick="window.dispatchEvent(new CustomEvent('abrir-vinculo'))"><i class="fa-solid fa-user-pen"></i> Editar dados do perfil</button></div><span class="vp-dica">Depois de responder, o tipo só muda pelo botão acima.</span></div>
    </div>

@elseif($pendente)
    {{-- Aluno/servidor com matrícula ou turma faltando: completa os dados --}}
    <div class="vp-alerta">
        <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Complete seu cadastro</div>
        <p class="small mb-2">Seu cadastro de <strong>{{ $rotulos[$p->tipo_vinculo] ?? $p->tipo_vinculo }}</strong> está sem {{ $p->tipo_vinculo === 'aluno' ? 'matrícula ou turma' : 'a matrícula' }}. Informe abaixo (uma única vez).</p>
        <input type="hidden" name="confirmacao_vinculo" value="corrigir">
        <x-campos-vinculo :tipo="$p->tipo_vinculo" :matricula="$p->matricula" :turma-id="$p->turma_id" />
        <div class="vp-acao"><div class="vp-botoes"><button type="button" class="vp-btn" onclick="window.dispatchEvent(new CustomEvent('abrir-vinculo'))"><i class="fa-solid fa-user-pen"></i> Editar dados do perfil</button></div><span class="vp-dica">Para mudar o tipo de cadastro depois, use o botão acima.</span></div>
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
                <button type="button" class="vp-btn" onclick="window.dispatchEvent(new CustomEvent('abrir-vinculo'))"><i class="fa-solid fa-user-pen"></i> Editar dados do perfil</button>
            </div>
            <span class="vp-dica">Dados errados? Corrija aqui mesmo, sem sair da tela.</span>
        </div>
    </div>
@endif

@if($p)
    @if(session('vinculo_atualizado'))
        <div class="alert alert-success small py-2 mb-3" role="status"><i class="fa-solid fa-circle-check me-1"></i> Dados atualizados! Seu cadastro já está em dia.</div>
    @endif

    {{-- Modal de edição: renderizado no <body> para não ficar dentro do formulário de presença/inscrição --}}
    <div x-data="{
            aberto: false, enviando: false, erros: [],
            async enviar(ev) {
                this.erros = []; this.enviando = true;
                try {
                    const r = await fetch(ev.target.action, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(ev.target) });
                    if (r.ok) { window.location.reload(); return; }
                    const d = await r.json().catch(() => ({}));
                    if (r.status === 422) this.erros = d.errors ? Object.values(d.errors).flat() : [d.message || 'Confira os dados informados.'];
                    else if (r.status === 419) this.erros = ['Sua sessão expirou. Atualize a página e tente de novo.'];
                    else this.erros = ['Não foi possível salvar agora. Tente novamente.'];
                } catch (e) { this.erros = ['Sem conexão. Verifique a internet e tente de novo.']; }
                this.enviando = false;
            }
        }"
        @abrir-vinculo.window="aberto = true; erros = []" x-effect="document.body.style.overflow = aberto ? 'hidden' : ''">
        <template x-teleport="body">
            <div x-show="aberto" x-cloak class="vp-modal-fundo" @click.self="aberto = false" @keydown.escape.window="aberto = false" role="dialog" aria-modal="true" aria-labelledby="vp-modal-titulo">
                <div class="vp-modal">
                    <div class="vp-modal-topo">
                        <h5 id="vp-modal-titulo"><i class="fa-solid fa-user-pen me-2"></i>Editar dados do perfil</h5>
                        <button type="button" class="vp-x" @click="aberto = false" aria-label="Fechar">&times;</button>
                    </div>
                    <p class="small text-muted mb-3">Corrija o tipo de cadastro, a matrícula, o curso e a turma. Ao salvar, esta tela é atualizada. Nome, CPF e e-mail são alterados em <a href="{{ route('profile.edit') }}" target="_blank" rel="noopener">Minha Conta</a>.</p>

                    <form action="{{ route('profile.vinculo') }}" method="POST" @submit.prevent="enviar($event)">
                        @csrf
                        <div class="alert alert-danger small py-2" x-show="erros.length" x-cloak role="alert"><ul class="mb-0 ps-3"><template x-for="e in erros" :key="e"><li x-text="e"></li></template></ul></div>
                        <x-campos-vinculo idp="vm" :usar-old="false" :tipo="$p->tipo_vinculo" :matricula="$p->matricula" :turma-id="$p->turma_id" />
                        <div class="vp-modal-rodape">
                            <button type="button" class="vp-btn-sec" @click="aberto = false">Cancelar</button>
                            <button type="submit" class="vp-btn" :disabled="enviando"><i class="fa-solid" :class="enviando ? 'fa-spinner fa-spin' : 'fa-floppy-disk'"></i> <span x-text="enviando ? 'Salvando...' : 'Salvar alterações'"></span></button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
@endif
