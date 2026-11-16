<x-email.layout :titulo="$d['titulo']" :preheader="($d['item'] ?? '') . ' · ' . ($d['evento'] ?? '')">
    <x-email.dados :linhas="array_filter(['Evento' => $d['evento'] ?? null, ($d['item_rotulo'] ?? 'Item') => $d['item'] ?? null, 'Situação' => $d['situacao'] ?? null] + ($d['detalhes'] ?? []))" />

    @if(!empty($d['mensagem']))
        <x-email.aviso :tipo="$d['tipo'] ?? 'info'">{{ $d['mensagem'] }}</x-email.aviso>
    @endif

    @if(!empty($d['citacao']))
        <x-email.citacao :titulo="$d['citacao_titulo'] ?? 'Mensagem'">{{ $d['citacao'] }}</x-email.citacao>
    @endif

    <x-email.botao :href="$d['href']">{{ $d['botao'] }}</x-email.botao>

    <x-email.perfil :area="$d['area']" :href="$d['area_href']" />
</x-email.layout>
