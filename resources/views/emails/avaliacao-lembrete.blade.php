<!DOCTYPE html>
<html lang="pt-BR">
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #047857;">{{ $avaliacao->atrasada() ? 'Avaliação em atraso' : 'Avaliação com prazo próximo' }}</h2>
    <p>Evento: <strong>{{ $avaliacao->trabalho->evento->titulo }}</strong><br>
       Trabalho: <strong>{{ $avaliacao->trabalho->codigo }}</strong></p>
    <p>Prazo: <strong>{{ $avaliacao->prazo->format('d/m/Y') }}</strong></p>
    <p><a href="{{ route('avaliador.avaliacoes.show', $avaliacao) }}">Abrir avaliação</a></p>
</body>
</html>
