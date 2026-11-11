@props([
    'name' => 'avaliadores[]',
    'items',                 // usuários com ->carga e ->impedimento (null | ['tipo' => atribuido|recusou|conflito, 'motivo' => ...])
    'single' => false,       // true: escolhe uma pessoa só (radio)
    'id' => 'sel-' . \Illuminate\Support\Str::random(5),
])
{{-- Seletor de avaliadores: busca por nome + lista com caixas de seleção. HTML renderizado no servidor (não depende de
     bibliotecas externas e funciona no celular); o Alpine só filtra e conta os marcados. --}}
<div x-data="{ busca: '', marcados: 0, nada: false,
        filtrar() { const b = this.busca.trim().toLowerCase(); let n = 0;
            this.$root.querySelectorAll('[data-nome]').forEach(el => { const ok = !b || el.dataset.nome.includes(b); el.style.display = ok ? '' : 'none'; if (ok) n++; });
            this.nada = n === 0; },
        contar() { this.marcados = this.$root.querySelectorAll('input:checked').length; } }"
     class="seletor-avaliadores" id="{{ $id }}">
    <div class="input-group input-group-sm mb-2">
        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
        <input type="search" class="form-control" placeholder="Buscar avaliador pelo nome…" x-model="busca" @input="filtrar()" @keydown.enter.prevent autocomplete="off" aria-label="Buscar avaliador">
    </div>

    <div class="seletor-avaliadores-lista border rounded-3 bg-white" @change="contar()">
        @foreach($items as $u)
            @php
                $imp = $u->impedimento ?? null;
                $tipo = $imp['tipo'] ?? null;
                $motivo = match ($tipo) {
                    'atribuido' => 'já atribuído',
                    'recusou' => 'recusou a avaliação',
                    'conflito' => $imp['motivo'] ?? 'conflito de interesse',
                    default => null,
                };
            @endphp
            <label class="seletor-avaliadores-item d-flex align-items-start gap-2 px-3 py-2 mb-0 {{ $tipo ? 'is-impedido' : '' }}" data-nome="{{ mb_strtolower($u->name) }}">
                <input type="{{ $single ? 'radio' : 'checkbox' }}" class="form-check-input mt-1 flex-shrink-0" name="{{ $name }}" value="{{ $u->id }}" @disabled($tipo) @if($tipo) data-tipo="{{ $tipo }}" @endif>
                <span class="min-w-0">
                    <span class="d-block fw-semibold texto-quebra">{{ $u->name }}</span>
                    @if($tipo)
                        <span class="d-block small" style="color:#b91c1c"><i class="fa-solid fa-ban me-1"></i>{{ $motivo }}</span>
                    @else
                        <span class="d-block small" style="color:#64748b">{{ (int) ($u->carga ?? 0) }} avaliação(ões) em aberto</span>
                    @endif
                </span>
            </label>
        @endforeach
        <div class="px-3 py-3 small text-muted" x-show="nada" x-cloak>Nenhum avaliador encontrado.</div>
    </div>

    @unless($single)
        <div class="small mt-1" style="color:#64748b"><span x-text="marcados">0</span> selecionado(s)</div>
    @endunless
</div>

@once('estilo-seletor-avaliadores')
    <style>
        .seletor-avaliadores-lista { max-height: 240px; overflow-y: auto; -webkit-overflow-scrolling: touch; }
        .seletor-avaliadores-item { cursor: pointer; border-bottom: 1px solid #f1f5f9; }
        .seletor-avaliadores-item:last-child { border-bottom: 0; }
        .seletor-avaliadores-item:hover { background: #f0fdf4; }
        .seletor-avaliadores-item:has(input:checked) { background: #ecfdf5; }
        .seletor-avaliadores-item.is-impedido { cursor: not-allowed; background: #fef2f2; opacity: .9; }
        .min-w-0 { min-width: 0; }
    </style>
@endonce
