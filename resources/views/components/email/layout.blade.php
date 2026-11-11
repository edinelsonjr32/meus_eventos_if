@props(['titulo', 'preheader' => null, 'saudacao' => null])
{{--
  Moldura padrão dos e-mails do sistema (HTML em tabelas e estilos inline: funciona no Gmail, Outlook e celulares).
  Uso: <x-email.layout titulo="..." preheader="resumo que aparece na lista de e-mails"> ...conteúdo... </x-email.layout>
  Peças: <x-email.botao>, <x-email.dados>, <x-email.aviso>, <x-email.citacao>.
--}}
<!DOCTYPE html>
<html lang="pt-BR" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $titulo }}</title>
    <style>
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .pad { padding-left: 20px !important; padding-right: 20px !important; }
            .h1 { font-size: 21px !important; }
            .col-rotulo { display: block !important; width: auto !important; padding: 10px 18px 0 18px !important; border-bottom: 0 !important; }
            .col-valor { display: block !important; width: auto !important; padding: 2px 18px 10px 18px !important; }
            .btn a { display: block !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#eef2f6; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    @if($preheader)
        <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; font-size:1px; line-height:1px;">{{ $preheader }}&#8199;&zwnj;&#8199;&zwnj;&#8199;&zwnj;&#8199;&zwnj;</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2f6;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px;">

                    {{-- Cabeçalho da marca --}}
                    <tr>
                        <td style="background:#064e3b; border-radius:16px 16px 0 0; padding:22px 32px;" class="pad">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="44" valign="middle">
                                        <div style="width:40px; height:40px; line-height:40px; background:#10b981; border-radius:10px; text-align:center; font-size:20px; color:#ffffff;">&#127891;</div>
                                    </td>
                                    <td valign="middle" style="padding-left:12px; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                        <div style="font-size:18px; font-weight:700; color:#ffffff; letter-spacing:.2px;">{{ config('app.name', 'Meus Eventos IF') }}</div>
                                        <div style="font-size:12px; color:#a7f3d0;">Instituto Federal do Pará &middot; Campus Óbidos</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr><td style="height:4px; line-height:4px; font-size:0; background:#10b981;">&nbsp;</td></tr>

                    {{-- Cartão de conteúdo --}}
                    <tr>
                        <td class="pad" style="background:#ffffff; padding:32px 32px 28px; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1f2937; font-size:15px; line-height:1.6; border-left:1px solid #e2e8f0; border-right:1px solid #e2e8f0;">
                            <h1 class="h1" style="margin:0 0 18px; font-size:24px; line-height:1.3; color:#064e3b; font-weight:700;">{{ $titulo }}</h1>
                            @if($saudacao)
                                <p style="margin:0 0 16px;">{{ $saudacao }}</p>
                            @endif
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Rodapé --}}
                    <tr>
                        <td class="pad" style="background:#f8fafc; border:1px solid #e2e8f0; border-top:1px solid #e2e8f0; border-radius:0 0 16px 16px; padding:20px 32px; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:12px; line-height:1.6; color:#64748b;">
                            <div style="margin-bottom:6px;"><strong style="color:#334155;">{{ config('app.name', 'Meus Eventos IF') }}</strong> &middot; plataforma de eventos do IFPA Campus Óbidos</div>
                            <div>Esta é uma mensagem automática; por favor, não responda a este e-mail. Para tirar dúvidas, procure a organização do evento.</div>
                            <div style="margin-top:8px;">
                                <a href="{{ url('/') }}" style="color:#047857; text-decoration:none;">Acessar a plataforma</a>
                                &nbsp;&middot;&nbsp;
                                <a href="{{ route('privacidade') }}" style="color:#047857; text-decoration:none;">Política de privacidade</a>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
