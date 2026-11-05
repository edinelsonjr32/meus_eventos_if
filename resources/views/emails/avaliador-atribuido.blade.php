<!DOCTYPE html>
<html lang="pt-BR">
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #047857;">Você foi designado(a) como avaliador(a)</h2>
    <p>Evento: <strong>{{ $trabalho->evento->titulo }}</strong></p>
    <p>Protocolo do trabalho: <strong>{{ $trabalho->codigo }}</strong></p>
    @if($avaliacao->prazo)
        <p>Prazo para concluir a avaliação: <strong>{{ $avaliacao->prazo->format('d/m/Y') }}</strong></p>
    @endif
    <p>Acesse a plataforma para ver os trabalhos atribuídos a você: <a href="{{ route('avaliador.avaliacoes.index') }}">Minhas avaliações</a>.</p>
</body>
</html>
