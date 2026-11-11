@props(['tipo' => 'info'])
@php
    [$fundo, $borda, $cor] = ['info' => ['#eff6ff', '#3b82f6', '#1e3a8a'], 'ok' => ['#ecfdf5', '#10b981', '#065f46'], 'alerta' => ['#fffbeb', '#f59e0b', '#78350f'], 'erro' => ['#fef2f2', '#ef4444', '#7f1d1d']][$tipo] ?? ['#eff6ff', '#3b82f6', '#1e3a8a'];
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0;">
    <tr>
        <td style="background:{{ $fundo }}; border-left:4px solid {{ $borda }}; border-radius:8px; padding:14px 16px; font-size:14px; line-height:1.6; color:{{ $cor }};">{{ $slot }}</td>
    </tr>
</table>
