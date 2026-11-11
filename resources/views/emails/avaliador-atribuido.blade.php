<x-email.layout titulo="Você foi designado(a) como avaliador(a)" :preheader="'Trabalho ' . $trabalho->codigo . ' · ' . $trabalho->evento->titulo">
    <p style="margin:0 0 4px;">Um novo trabalho aguarda a sua avaliação.</p>

    <x-email.dados :linhas="[
        'Evento' => $trabalho->evento->titulo,
        'Protocolo' => $trabalho->codigo,
        'Prazo' => $avaliacao->prazo?->format('d/m/Y'),
    ]" />

    <x-email.botao :href="route('avaliador.avaliacoes.index')">Ver minhas avaliações</x-email.botao>
</x-email.layout>
