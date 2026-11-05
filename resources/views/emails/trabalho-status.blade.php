<!DOCTYPE html>
<html lang="pt-BR">
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #047857;">{{ $assunto }}</h2>
    <p>Evento: <strong>{{ $trabalho->evento->titulo }}</strong><br>
       Trabalho: <strong>{{ $trabalho->titulo }}</strong> ({{ $trabalho->codigo }})</p>

    <p><strong>Mensagem da gestão:</strong></p>
    <blockquote style="border-left: 4px solid #d1d5db; margin: 0; padding: 4px 12px; white-space: pre-line;">{{ $justificativa }}</blockquote>

    @if($podeEditar)
        <p>Faça os ajustes e reenvie em <a href="{{ route('trabalhos.edit', $trabalho) }}">Meus Trabalhos</a>.</p>
    @else
        <p>Detalhes em <a href="{{ route('trabalhos.show', $trabalho) }}">Meus Trabalhos</a>.</p>
    @endif
</body>
</html>
