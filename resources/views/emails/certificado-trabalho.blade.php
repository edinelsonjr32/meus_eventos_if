<!DOCTYPE html>
<html lang="pt-BR">
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #047857;">Seu certificado está disponível</h2>
    <p>Olá, <strong>{{ $certificado->autor->nome_completo }}</strong>!</p>
    <p>O certificado ({{ mb_strtolower($certificado->funcaoRotulo()) }}) do trabalho
       <strong>{{ $certificado->trabalho->titulo }}</strong> no evento
       <strong>{{ $certificado->trabalho->evento->titulo }}</strong> já pode ser baixado.</p>
    <p><a href="{{ route('certificado.trabalho.download', $certificado->hash_validacao) }}">Baixar certificado (PDF)</a></p>
    <p style="font-size: 12px; color: #6b7280;">Código de autenticidade: {{ $certificado->hash_validacao }}<br>
       Verificação pública: {{ route('certificado.trabalho.validar', $certificado->hash_validacao) }}</p>
</body>
</html>
