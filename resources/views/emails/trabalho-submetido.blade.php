<!DOCTYPE html>
<html lang="pt-BR">
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #047857;">Trabalho recebido com sucesso</h2>
    <p>O trabalho abaixo foi submetido ao evento <strong>{{ $trabalho->evento->titulo }}</strong>.</p>

    <table cellpadding="6" style="border-collapse: collapse;">
        <tr><td><strong>Protocolo</strong></td><td>{{ $trabalho->codigo }}</td></tr>
        <tr><td><strong>Título</strong></td><td>{{ $trabalho->titulo }}</td></tr>
        <tr><td><strong>Categoria</strong></td><td>{{ \App\Models\TrabalhoConfig::TIPOS[$trabalho->tipo_trabalho] ?? $trabalho->tipo_trabalho }}</td></tr>
        <tr><td><strong>Data do envio</strong></td><td>{{ $trabalho->submetido_em?->format('d/m/Y H:i') }}</td></tr>
    </table>

    <p><strong>Autores</strong></p>
    <ul>
        @foreach($trabalho->autores as $autor)
            <li>{{ $autor->nome_completo }} — {{ $autor->tipoRotulo() }}{{ $autor->apresentador ? ' (apresentador)' : '' }}</li>
        @endforeach
    </ul>

    <p>Acompanhe o andamento em <a href="{{ route('trabalhos.show', $trabalho) }}">Meus Trabalhos</a>. Guarde o número de protocolo.</p>
</body>
</html>
