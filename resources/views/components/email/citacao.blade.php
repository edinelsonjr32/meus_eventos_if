@props(['titulo' => null])
{{-- Mensagem escrita por uma pessoa (ex.: gestão): preserva as quebras de linha. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0;">
    <tr>
        <td style="background:#f8fafc; border-left:4px solid #94a3b8; border-radius:8px; padding:14px 18px; font-size:15px; line-height:1.65; color:#1f2937;">
            @if($titulo)<div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; margin-bottom:6px;">{{ $titulo }}</div>@endif
            <div style="white-space:pre-line;">{{ $slot }}</div>
        </td>
    </tr>
</table>
