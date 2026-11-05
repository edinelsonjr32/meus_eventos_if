<!DOCTYPE html>
<html lang="pt-BR">
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #047857;">{{ \App\Mail\TrabalhoDecisaoMail::ROTULOS[$decisao] ?? 'Resultado da avaliação' }}</h2>
    <p>Evento: <strong>{{ $trabalho->evento->titulo }}</strong><br>
       Trabalho: <strong>{{ $trabalho->titulo }}</strong> ({{ $trabalho->codigo }})</p>

    @if($decisao === 'aprovado')
        <p>Parabéns! Seu trabalho foi <strong>aprovado</strong>.</p>
    @elseif($decisao === 'aprovado_com_ressalvas')
        <p>Seu trabalho foi <strong>aprovado com ressalvas</strong>. É necessário enviar uma versão corrigida
           @if($trabalho->prazo_correcao) até <strong>{{ $trabalho->prazo_correcao->format('d/m/Y') }}</strong>@endif.</p>
    @else
        <p>Infelizmente seu trabalho <strong>não foi aprovado</strong> nesta avaliação.</p>
    @endif

    @if($trabalho->decisao_justificativa)
        <p><strong>Mensagem da gestão:</strong></p>
        <blockquote style="border-left: 4px solid #d1d5db; margin: 0; padding: 4px 12px; white-space: pre-line;">{{ $trabalho->decisao_justificativa }}</blockquote>
    @endif

    <p>Veja o parecer e os próximos passos em
        <a href="{{ $decisao === 'aprovado_com_ressalvas' ? route('trabalhos.edit', $trabalho) : route('trabalhos.show', $trabalho) }}">Meus Trabalhos</a>.</p>
</body>
</html>
