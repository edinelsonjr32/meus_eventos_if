@props(['href', 'cor' => 'verde'])
@php
    $fundo = ['verde' => '#047857', 'azul' => '#1d4ed8', 'ambar' => '#b45309'][$cor] ?? '#047857';
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="btn" style="margin:22px 0;">
    <tr>
        <td align="center" style="border-radius:10px; background:{{ $fundo }};">
            <a href="{{ $href }}" target="_blank" style="display:inline-block; padding:13px 28px; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:10px;">{{ $slot }}</a>
        </td>
    </tr>
</table>
