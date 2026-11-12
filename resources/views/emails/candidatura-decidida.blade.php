@php
    $proposta = $tipo === 'proposta';
    $titulos = [
        'aprovada' => 'Proposta aprovada', 'aprovado' => 'Inscrição aprovada', 'lista_espera' => 'Você está na lista de espera',
        'ajustes' => 'Ajustes solicitados na proposta', 'reprovada' => 'Resultado da proposta', 'reprovado' => 'Resultado da inscrição',
    ];
    $tipoAviso = in_array($status, ['aprovada', 'aprovado'], true) ? 'ok' : (in_array($status, ['ajustes', 'lista_espera'], true) ? 'alerta' : 'erro');
@endphp
<x-email.layout :titulo="$titulos[$status] ?? 'Atualização da sua candidatura'" :preheader="$titulo . ' · ' . $evento">
    <x-email.dados :linhas="['Evento' => $evento, ($proposta ? 'Proposta' : 'Chamada') => $titulo, 'Situação' => $statusRotulo, 'Programação' => $programacao]" />

    @if($status === 'aprovada')
        <x-email.aviso tipo="ok"><strong>Parabéns!</strong> Sua proposta foi aprovada e já está na programação do evento.</x-email.aviso>
    @elseif($status === 'aprovado')
        <x-email.aviso tipo="ok"><strong>Obrigado por se voluntariar!</strong> Sua inscrição foi aprovada. Em breve você verá suas atividades e o servidor responsável na sua área.</x-email.aviso>
    @elseif($status === 'ajustes')
        <x-email.aviso tipo="alerta">A organização pediu <strong>ajustes</strong> na sua proposta. Edite e reenvie pela sua área.</x-email.aviso>
    @elseif($status === 'lista_espera')
        <x-email.aviso tipo="alerta">As vagas foram preenchidas, mas você ficou na <strong>lista de espera</strong> e será chamado(a) se surgir vaga.</x-email.aviso>
    @else
        <x-email.aviso tipo="erro">Infelizmente {{ $proposta ? 'sua proposta não foi aprovada' : 'você não foi selecionado(a)' }} desta vez.</x-email.aviso>
    @endif

    @if($parecer)
        <x-email.citacao titulo="Mensagem da organização">{{ $parecer }}</x-email.citacao>
    @endif

    <x-email.botao :href="$link">{{ $proposta ? 'Ver minhas propostas' : 'Ver meu voluntariado' }}</x-email.botao>
</x-email.layout>
