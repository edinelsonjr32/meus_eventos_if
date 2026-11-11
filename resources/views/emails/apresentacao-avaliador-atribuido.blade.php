<x-email.layout titulo="Apresentação para avaliar" :preheader="'Trabalho ' . $trabalho->codigo . ' · ' . $trabalho->evento->titulo">
    <p style="margin:0 0 4px;">Você foi designado(a) para avaliar a <strong>apresentação</strong> do trabalho abaixo.</p>

    <x-email.dados :linhas="[
        'Evento' => $trabalho->evento->titulo,
        'Protocolo' => $trabalho->codigo,
        'Sessão' => $trabalho->sessao ? $trabalho->sessao->titulo . ' — ' . $trabalho->sessao->data_inicio->format('d/m/Y H:i') : null,
        'Prazo' => $avaliacao->prazo?->format('d/m/Y'),
    ]" />

    <x-email.botao :href="route('avaliador.apresentacoes.index')">Ver apresentações a avaliar</x-email.botao>
</x-email.layout>
