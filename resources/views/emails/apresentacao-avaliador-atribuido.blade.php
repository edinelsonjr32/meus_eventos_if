<!DOCTYPE html>
<html lang="pt-BR">
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #047857;">Apresentação para avaliar</h2>
    <p>Evento: <strong>{{ $trabalho->evento->titulo }}</strong></p>
    <p>Protocolo do trabalho: <strong>{{ $trabalho->codigo }}</strong></p>
    @if($trabalho->sessao)
        <p>Sessão: <strong>{{ $trabalho->sessao->titulo }}</strong> — {{ $trabalho->sessao->data_inicio->format('d/m/Y H:i') }}</p>
    @endif
    @if($avaliacao->prazo)
        <p>Prazo para lançar a avaliação: <strong>{{ $avaliacao->prazo->format('d/m/Y') }}</strong></p>
    @endif
    <p>Você foi designado(a) para avaliar a <strong>apresentação</strong> deste trabalho. Acesse: <a href="{{ route('avaliador.apresentacoes.index') }}">Apresentações a avaliar</a>.</p>
</body>
</html>
