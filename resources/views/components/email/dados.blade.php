@props(['linhas' => []])
{{-- Tabela de dados "rótulo: valor". $linhas = ['Protocolo' => 'TRB-1-0001', ...]; valores vazios são omitidos. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
    @foreach(collect($linhas)->filter(fn ($v) => filled($v)) as $rotulo => $valor)
        <tr>
            <td class="col-rotulo" width="34%" valign="top" style="padding:11px 16px 11px 18px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; {{ !$loop->last ? 'border-bottom:1px solid #e8edf3;' : '' }}">{{ $rotulo }}</td>
            <td class="col-valor" valign="top" style="padding:11px 18px 11px 0; font-size:15px; color:#111827; {{ !$loop->last ? 'border-bottom:1px solid #e8edf3;' : '' }}">{{ $valor }}</td>
        </tr>
    @endforeach
</table>
