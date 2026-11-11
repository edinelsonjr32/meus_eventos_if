<x-email.layout titulo="Trabalho reenviado pelo autor" :preheader="$trabalho->titulo . ' · ' . $trabalho->codigo">
    <x-email.dados :linhas="['Evento' => $trabalho->evento->titulo, 'Trabalho' => $trabalho->titulo, 'Protocolo' => $trabalho->codigo]" />

    <x-email.aviso tipo="info">{{ match ($statusAnterior) {
        \App\Models\Trabalho::DEVOLVIDO_TRIAGEM => 'O autor fez os ajustes pedidos na triagem e reenviou o trabalho.',
        'aprovado' => 'O autor enviou a versão final corrigida (trabalho aprovado com ressalvas). Não é preciso aprovar de novo: o PDF já substituiu o anterior.',
        default => 'O autor enviou a versão corrigida solicitada na decisão da avaliação.',
    } }}</x-email.aviso>

    <x-email.botao :href="route('gestao.trabalhos.show', $trabalho)">Abrir na gestão de trabalhos</x-email.botao>
</x-email.layout>
