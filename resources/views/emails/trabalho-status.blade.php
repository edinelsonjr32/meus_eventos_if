<x-email.layout :titulo="$assunto" :preheader="$trabalho->titulo . ' · ' . $trabalho->codigo">
    <x-email.dados :linhas="['Evento' => $trabalho->evento->titulo, 'Trabalho' => $trabalho->titulo, 'Protocolo' => $trabalho->codigo]" />

    <x-email.citacao titulo="Mensagem da gestão">{{ $justificativa }}</x-email.citacao>

    @if($podeEditar)
        <p style="margin:0;">Faça os ajustes solicitados e reenvie o trabalho.</p>
        <x-email.botao :href="route('trabalhos.edit', $trabalho)" cor="ambar">Ajustar e reenviar</x-email.botao>
    @else
        <x-email.botao :href="route('trabalhos.show', $trabalho)">Ver detalhes do trabalho</x-email.botao>
    @endif
</x-email.layout>
