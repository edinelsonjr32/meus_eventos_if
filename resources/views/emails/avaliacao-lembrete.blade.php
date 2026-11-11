<x-email.layout :titulo="$avaliacao->atrasada() ? 'Avaliação em atraso' : 'Avaliação com prazo próximo'" :preheader="'Trabalho ' . $avaliacao->trabalho->codigo . ' · prazo ' . $avaliacao->prazo->format('d/m/Y')">
    @if($avaliacao->atrasada())
        <x-email.aviso tipo="erro">O prazo desta avaliação <strong>já passou</strong>. Se possível, conclua-a o quanto antes.</x-email.aviso>
    @else
        <x-email.aviso tipo="alerta">O prazo desta avaliação está <strong>próximo</strong>.</x-email.aviso>
    @endif

    <x-email.dados :linhas="[
        'Evento' => $avaliacao->trabalho->evento->titulo,
        'Protocolo' => $avaliacao->trabalho->codigo,
        'Prazo' => $avaliacao->prazo->format('d/m/Y'),
    ]" />

    <x-email.botao :href="route('avaliador.avaliacoes.show', $avaliacao)" :cor="$avaliacao->atrasada() ? 'ambar' : 'verde'">Abrir avaliação</x-email.botao>
</x-email.layout>
