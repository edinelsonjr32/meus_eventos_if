<x-email.layout titulo="Redefinição de senha" :saudacao="'Olá, ' . $nome . '!'" preheader="Use o link para criar uma nova senha. Ele expira em breve.">
    <p style="margin:0 0 4px;">Recebemos um pedido para redefinir a senha da sua conta. Clique no botão abaixo para criar uma nova senha.</p>

    <x-email.botao :href="$link">Criar nova senha</x-email.botao>

    <x-email.aviso tipo="alerta">Por segurança, este link expira em <strong>{{ $minutos }} minutos</strong> e só pode ser usado uma vez.</x-email.aviso>

    <p style="margin:0 0 12px;"><strong>Não pediu a redefinição?</strong> Ignore este e-mail: sua senha continua a mesma e nenhuma alteração foi feita.</p>

    <p style="margin:0; font-size:12px; color:#64748b; word-break:break-all;">Se o botão não funcionar, copie e cole este endereço no navegador:<br>{{ $link }}</p>
</x-email.layout>
