<!DOCTYPE html>
<html lang="pt-BR">
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #047857;">Autorize a publicação do seu trabalho nos Anais</h2>
    <p>Evento: <strong>{{ $trabalho->evento->titulo }}</strong></p>
    <p>Trabalho: <strong>{{ $trabalho->titulo }}</strong> (protocolo {{ $trabalho->codigo }})</p>
    <p>Seu trabalho foi aprovado e apresentado, mas ainda <strong>não consta nos Anais</strong> porque a publicação não foi autorizada.
       Se você concorda, entre na plataforma e autorize na página do trabalho (apenas título, resumo, nome e instituição dos autores e o PDF são publicados; CPF e e-mail nunca são divulgados):</p>
    <p><a href="{{ route('trabalhos.show', $trabalho) }}">Abrir meu trabalho e autorizar a publicação</a></p>
</body>
</html>
