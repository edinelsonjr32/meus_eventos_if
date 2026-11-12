{{-- Voluntários vinculados a uma demanda: lista com remoção e inclusão de vários de uma vez. Variáveis: $d (demanda com voluntarios.usuario), $evento, $aprovados --}}
@php
    $disponiveis = $aprovados->whereNotIn('id', $d->voluntarios->pluck('id'));
    $restam = $d->vagas ? max(0, $d->vagas - $d->voluntarios->count()) : null;
@endphp
<div class="mt-2">
    <div class="small fw-bold text-secondary mb-1">Voluntários vinculados ({{ $d->voluntarios->count() }}{{ $d->vagas ? '/' . $d->vagas : '' }})</div>
    @forelse($d->voluntarios as $vol)
        @php $falta = $vol->faltaDisponibilidade($d); @endphp
        <div class="d-flex justify-content-between align-items-center gap-2 border rounded-3 px-2 py-1 mb-1 small bg-white">
            <span class="texto-quebra">{{ $vol->usuario->name }}@if($vol->telefone) <span class="text-muted">· {{ $vol->telefone }}</span>@endif @if($falta)<span class="text-warning-emphasis" title="Não marcou: {{ implode(', ', $falta) }}">⚠ fora da disponibilidade</span>@endif</span>
            <form action="{{ route('admin.voluntarios.desalocar', [$evento->id, $vol->id, $d->id]) }}" method="POST" onsubmit="return confirm('Remover {{ addslashes($vol->usuario->name) }} desta atividade?')">@csrf @method('DELETE')<input type="hidden" name="aba" value="atividades"><button class="btn btn-sm btn-light border py-0" title="Remover da atividade"><i class="fa-solid fa-user-minus"></i> Remover</button></form>
        </div>
    @empty
        <div class="small text-muted mb-1">Nenhum voluntário vinculado ainda.</div>
    @endforelse

    @if($disponiveis->isNotEmpty())
        <form action="{{ route('admin.voluntarios.demandas.alocar', [$evento->id, $d->id]) }}" method="POST" class="mt-2" x-data="{ aberto: false }">@csrf
            <button type="button" class="btn btn-sm btn-outline-success fw-bold" @click="aberto = !aberto"><i class="fa-solid fa-user-plus"></i> Adicionar voluntários</button>
            <div x-show="aberto" x-cloak class="border rounded-3 p-2 mt-2 bg-white" style="max-width:420px">
                <div class="small text-muted mb-1">Marque quantos quiser{{ $restam !== null ? ' (restam ' . $restam . ' vaga(s))' : '' }}:</div>
                @foreach($disponiveis as $ap)
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="voluntario_ids[]" value="{{ $ap->id }}" id="v{{ $d->id }}-{{ $ap->id }}"><label class="form-check-label small" for="v{{ $d->id }}-{{ $ap->id }}">{{ $ap->usuario->name }}{{ $ap->faltaDisponibilidade($d) ? ' ⚠ fora da disponibilidade' : '' }}</label></div>
                @endforeach
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <div class="form-check mb-0"><input class="form-check-input me-1" type="checkbox" name="ignorar_conflito" value="1" id="dc{{ $d->id }}"><label class="form-check-label small" for="dc{{ $d->id }}">ignorar conflito de horário/disponibilidade</label></div>
                    <button class="btn btn-sm btn-primary-custom ms-auto"><i class="fa-solid fa-link"></i> Vincular selecionados</button>
                </div>
            </div>
        </form>
    @elseif($d->voluntarios->isEmpty())
        <div class="small text-muted">Aprove voluntários na aba "Inscritos" para vinculá-los aqui.</div>
    @endif
</div>
