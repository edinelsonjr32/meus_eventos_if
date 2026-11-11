@php
    $titulos = ['aprovado' => 'Seu trabalho foi aprovado', 'aprovado_com_ressalvas' => 'Trabalho aprovado com ressalvas', 'reprovado' => 'Resultado da avaliação do seu trabalho'];
@endphp
<x-email.layout :titulo="$titulos[$decisao] ?? (\App\Mail\TrabalhoDecisaoMail::ROTULOS[$decisao] ?? 'Resultado da avaliação')" :preheader="$trabalho->titulo . ' · ' . $trabalho->codigo">
    <x-email.dados :linhas="['Evento' => $trabalho->evento->titulo, 'Trabalho' => $trabalho->titulo, 'Protocolo' => $trabalho->codigo]" />

    @if($decisao === 'aprovado')
        <x-email.aviso tipo="ok"><strong>Parabéns!</strong> Seu trabalho foi <strong>aprovado</strong>.</x-email.aviso>
    @elseif($decisao === 'aprovado_com_ressalvas')
        <x-email.aviso tipo="alerta">
            Seu trabalho foi <strong>aprovado com ressalvas</strong> e segue normalmente para a apresentação.
            Ao final, envie a <strong>versão final corrigida</strong> (PDF){!! $trabalho->prazo_correcao ? ' até <strong>' . e($trabalho->prazo_correcao->format('d/m/Y')) . '</strong>' : '' !!} para constar nos Anais.
        </x-email.aviso>
    @else
        <x-email.aviso tipo="erro">Infelizmente seu trabalho <strong>não foi aprovado</strong> nesta avaliação.</x-email.aviso>
    @endif

    @if($trabalho->decisao_justificativa)
        <x-email.citacao titulo="Mensagem da gestão">{{ $trabalho->decisao_justificativa }}</x-email.citacao>
    @endif

    <p style="margin:0;">Veja os pareceres e os próximos passos na página do seu trabalho.</p>
    <x-email.botao :href="route('trabalhos.show', $trabalho)">Ver resultado completo</x-email.botao>
</x-email.layout>
