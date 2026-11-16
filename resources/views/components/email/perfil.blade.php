@props(['area', 'href'])
{{-- Lembrete de onde acompanhar o status: na conta da pessoa. --}}
<p style="margin:0; font-size:13px; line-height:1.6; color:#64748b;">
    Você pode conferir o status a qualquer momento no seu perfil: entre na sua conta e abra
    <a href="{{ $href }}" target="_blank" style="color:#047857; font-weight:700; text-decoration:underline;">{{ $area }}</a>.
</p>
