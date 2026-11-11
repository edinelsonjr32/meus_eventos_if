@props(['name' => 'password', 'label' => 'Senha', 'placeholder' => 'Sua senha', 'autocomplete' => 'current-password', 'required' => true, 'autofocus' => false, 'dica' => null])
{{-- Campo de senha com botão "mostrar/ocultar" (sem dependências; funciona por teclado e leitor de tela). --}}
@php($id = 'senha-' . $name . '-' . substr(md5($name . $label), 0, 4))
<div class="mb-3">
    <label for="{{ $id }}" class="form-label small fw-bold text-uppercase text-secondary">{{ $label }}</label>
    <div class="input-group campo-senha">
        <span class="input-group-text bg-light border-end-0 text-secondary"><i class="fa-solid fa-lock"></i></span>
        <input id="{{ $id }}" type="password" name="{{ $name }}" class="form-control border-start-0 border-end-0 ps-0 @error($name) is-invalid @enderror"
               placeholder="{{ $placeholder }}" autocomplete="{{ $autocomplete }}" @required($required) @if($autofocus) autofocus @endif>
        <button type="button" class="btn btn-ver-senha border" data-alvo="{{ $id }}" aria-label="Mostrar senha" aria-pressed="false" title="Mostrar senha">
            <i class="fa-regular fa-eye"></i>
        </button>
    </div>
    @if($dica)<div class="form-text small">{{ $dica }}</div>@endif
    @error($name)
        <small class="text-danger d-block mt-1">{{ $message }}</small>
    @enderror
</div>

@once
    <style>
        .campo-senha .btn-ver-senha { background: #f8fafc; border-color: #e2e8f0 !important; border-left: 0 !important; color: #64748b; border-radius: 0 10px 10px 0; padding: 0 14px; }
        .campo-senha .btn-ver-senha:hover, .campo-senha .btn-ver-senha:focus-visible { color: #047857; background: #ecfdf5; }
        .campo-senha .input-group-text { border-radius: 10px 0 0 10px; border-color: #e2e8f0; }
        .campo-senha .form-control { border-radius: 0; }
        .campo-senha:focus-within .input-group-text, .campo-senha:focus-within .btn-ver-senha, .campo-senha:focus-within .form-control { border-color: #10b981 !important; }
    </style>
    <script>
        document.addEventListener('click', function (e) {
            var b = e.target.closest('.btn-ver-senha');
            if (!b) return;
            var campo = document.getElementById(b.dataset.alvo);
            if (!campo) return;
            var mostrar = campo.type === 'password';
            campo.type = mostrar ? 'text' : 'password';
            b.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
            b.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
            b.title = mostrar ? 'Ocultar senha' : 'Mostrar senha';
            b.querySelector('i').className = mostrar ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
            campo.focus();
        });
    </script>
@endonce
