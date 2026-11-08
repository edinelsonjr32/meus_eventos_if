{{-- Select2 com busca em tempo real de pessoas. Requer jQuery na página. --}}
@props(['evento', 'tipo', 'name', 'placeholder' => 'Digite para buscar…', 'selecionado' => null])

@once
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-5-theme/1.3.0/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/i18n/pt-BR.min.js"></script>
@endonce

<select name="{{ $name }}" class="form-select js-select-pessoa" data-url="{{ route('admin.eventos.buscar-pessoas', $evento->id) }}" data-tipo="{{ $tipo }}" data-placeholder="{{ $placeholder }}" style="width:100%">
    @if($selecionado)<option value="{{ $selecionado }}" selected>{{ $selecionado }}</option>@endif
</select>

@once
    <script>
        window.addEventListener('load', function () {
            if (!window.jQuery || !jQuery.fn.select2) return;
            jQuery('.js-select-pessoa').each(function () {
                const $s = jQuery(this);
                $s.select2({
                    theme: 'bootstrap-5', language: 'pt-BR', width: '100%', allowClear: true,
                    placeholder: $s.data('placeholder'), minimumInputLength: 0,
                    ajax: {
                        url: $s.data('url'), dataType: 'json', delay: 250, cache: true,
                        data: (p) => ({ q: p.term || '', page: p.page || 1, tipo: $s.data('tipo') }),
                        processResults: (d) => d,
                    },
                });
                // foco automático no campo de busca ao abrir
                $s.on('select2:open', () => setTimeout(() => document.querySelector('.select2-search__field')?.focus(), 0));
                // mensagem amigável quando o servidor falha
                $s.on('select2:open', () => {});
            });
            // formulário: exige uma pessoa escolhida
            document.querySelectorAll('form').forEach((f) => {
                const s = f.querySelector('.js-select-pessoa');
                if (!s) return;
                f.addEventListener('submit', (e) => {
                    if (!s.value) { e.preventDefault(); window.alerta && window.alerta('danger', 'Busque e selecione uma pessoa antes de continuar.', { titulo: 'Pessoa não selecionada' }); jQuery(s).select2('open'); }
                });
            });
        });
    </script>
@endonce
