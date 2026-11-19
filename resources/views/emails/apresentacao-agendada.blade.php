@php
    $titulo = match ($tipo) {
        'atualizacao' => 'O horário da apresentação foi atualizado',
        'lembrete' => 'A apresentação começa em breve',
        default => $papel === 'avaliador' ? 'Sala e horário da apresentação que você vai avaliar' : 'Sala e horário da sua apresentação oral',
    };
    $dia = $inicio->translatedFormat('l, d \d\e F');
    $horas = $inicio->format('H:i') . ($fim ? ' às ' . $fim->format('H:i') : '');
    $rota = route('apresentacao.sala', $trabalho);
@endphp
<x-email.layout :titulo="$titulo" :preheader="$trabalho->codigo . ' · ' . $dia . ', ' . $horas">
    <x-email.dados :linhas="array_filter([
        'Evento' => $trabalho->evento->titulo,
        'Protocolo' => $trabalho->codigo,
        'Trabalho' => $trabalho->titulo,
        'Dia' => ucfirst($dia),
        'Horário' => $horas,
        'Sala' => $sala->nome,
    ])" />

    @if($tipo === 'atualizacao')
        <x-email.aviso tipo="alerta">O dia, o horário ou a sala mudaram. Considere esta mensagem no lugar das anteriores.</x-email.aviso>
    @elseif($tipo === 'lembrete')
        <x-email.aviso tipo="info">Entre na sala alguns minutos antes para testar áudio e vídeo.</x-email.aviso>
    @elseif($papel === 'avaliador')
        <x-email.aviso tipo="info">Você é avaliador(a) desta apresentação. A ficha de avaliação fica em "Apresentações a avaliar" na plataforma.</x-email.aviso>
    @else
        <x-email.aviso tipo="info">A apresentação é por videoconferência. Entre na sala no horário indicado e mantenha o material pronto para compartilhar.</x-email.aviso>
    @endif

    @if($sala->instrucoes)
        <x-email.citacao titulo="Instruções da sala">{{ $sala->instrucoes }}</x-email.citacao>
    @endif

    @if($sala->linkSeguro())
        <x-email.botao :href="$sala->linkSeguro()">Entrar na sala</x-email.botao>
        <p style="margin:0 0 12px;font-size:12px;color:#64748b;">Se o botão não abrir, copie o endereço: {{ $sala->linkSeguro() }}</p>
    @endif

    <p style="margin:0 0 4px;font-size:13px;">Você também encontra o botão <strong>Entrar na sala</strong> na plataforma: <a href="{{ $rota }}" style="color:#047857;">{{ $rota }}</a></p>

    <x-email.perfil :area="$papel === 'avaliador' ? 'Apresentações a avaliar' : 'Meus Trabalhos'" :href="$papel === 'avaliador' ? route('avaliador.apresentacoes.index') : route('trabalhos.index')" />
</x-email.layout>
