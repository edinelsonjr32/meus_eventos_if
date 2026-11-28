{{-- Busca e vincula pessoas cadastradas como responsáveis pela atividade; o campo de texto cobre quem não tem cadastro. --}}
@props(['evento', 'id' => 'resp', 'selecionados' => collect(), 'outros' => ''])

<div x-data="{
        escolhidos: @js(collect($selecionados)->values()),
        termo: '', achados: [], buscando: false, falhou: false, pedido: 0,
        async buscar() {
            const q = this.termo.trim(), n = ++this.pedido;
            if (q.length < 2) { this.achados = []; this.buscando = false; return; }
            this.buscando = true; this.falhou = false;
            try {
                const r = await fetch(@js(route('admin.eventos.buscar-responsaveis', $evento->id)) + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                if (!r.ok) throw new Error();
                const d = await r.json();
                if (n === this.pedido) this.achados = d.results.filter(p => !this.escolhidos.some(e => e.id === p.id));
            } catch (e) { if (n === this.pedido) { this.achados = []; this.falhou = true; } }
            if (n === this.pedido) this.buscando = false;
        },
        escolher(p) { if (!p) return; this.escolhidos.push(p); this.termo = ''; this.achados = []; }
    }">
    <input type="hidden" name="responsaveis_enviado" value="1">
    <template x-for="p in escolhidos" :key="p.id"><input type="hidden" name="responsaveis[]" :value="p.id"></template>

    <label class="small fw-bold text-secondary" for="{{ $id }}-busca">Responsáveis com cadastro</label>
    <div class="d-flex flex-wrap gap-1 mb-1" x-show="escolhidos.length" x-cloak>
        <template x-for="(p, i) in escolhidos" :key="p.id">
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 d-inline-flex align-items-center gap-1 text-wrap text-start">
                <span x-text="p.nome"></span>
                <button type="button" class="btn-close" style="font-size:.55rem" :aria-label="'Remover ' + p.nome" @click="escolhidos.splice(i, 1)"></button>
            </span>
        </template>
    </div>
    <input id="{{ $id }}-busca" type="search" class="form-control" autocomplete="off" placeholder="Busque por nome, e-mail ou matrícula"
           x-model="termo" @input.debounce.300ms="buscar()" @keydown.enter.prevent="escolher(achados[0])" @keydown.escape="achados = []">
    <div class="list-group mt-1" x-show="achados.length" x-cloak style="max-height:220px;overflow:auto">
        <template x-for="p in achados" :key="p.id">
            <button type="button" class="list-group-item list-group-item-action py-1" @click="escolher(p)">
                <span class="d-block small fw-bold" x-text="p.nome"></span>
                <span class="d-block small text-muted texto-quebra" x-text="p.detalhe"></span>
            </button>
        </template>
    </div>
    <div class="form-text small" aria-live="polite">
        <span x-show="buscando" x-cloak>Buscando…</span>
        <span x-show="falhou" x-cloak class="text-danger">Não foi possível buscar agora. Tente novamente.</span>
        <span x-show="!buscando && !falhou && termo.trim().length > 1 && !achados.length" x-cloak>Ninguém encontrado: use o campo abaixo.</span>
    </div>

    <label class="small fw-bold text-secondary mt-2" for="{{ $id }}">Outros responsáveis / palestrantes (sem cadastro)</label>
    <input id="{{ $id }}" name="responsavel" class="form-control" maxlength="255" value="{{ $outros }}" placeholder="Ex.: Profa. Maria Silva, João Souza" {{ $attributes }}>
</div>
