{{--
  Feedback de formulários: alertas flutuantes (toasts), validação no navegador com mensagens por campo,
  máscaras e proteção contra duplo clique. Incluído uma vez no layout.

  Uso nos formulários:
    <form data-validar>                       validação própria (desliga a bolha nativa do navegador)
    <input data-mascara="cpf|telefone|inteiro|decimal|cep">
    <input data-nome="Nome do critério">      nome amigável usado nas mensagens
    <form data-regras="minhaFuncao">          função global extra: (form, erro) => erro(campo, 'mensagem')
--}}
@php
    $mensagens = array_values(array_filter([
        session('success') ? ['tipo' => 'success', 'texto' => session('success')] : null,
        session('warning') ? ['tipo' => 'warning', 'texto' => session('warning')] : null,
        session('error') ? ['tipo' => 'danger', 'texto' => session('error')] : null,
        session('status') && is_string(session('status')) && !str_contains(session('status'), '-') ? ['tipo' => 'success', 'texto' => session('status')] : null,
    ]));
    $errosServidor = $errors->getBag('default')->toArray();
@endphp

<style>
    .fb-toasts { position: fixed; top: 16px; right: 16px; z-index: 2000; display: flex; flex-direction: column; gap: 10px; width: min(420px, calc(100vw - 32px)); }
    .fb-toast { display: flex; gap: 12px; align-items: flex-start; background: #fff; border-radius: 12px; padding: 14px 16px; box-shadow: 0 10px 30px rgba(0,0,0,.18); border-left: 5px solid var(--fb-cor); animation: fb-in .25s ease-out; }
    .fb-toast i.fb-ico { color: var(--fb-cor); font-size: 1.25rem; margin-top: 2px; }
    .fb-toast .fb-titulo { font-weight: 700; font-size: .9rem; }
    .fb-toast .fb-texto { font-size: .85rem; color: #495057; }
    .fb-toast ul { margin: 4px 0 0; padding-left: 18px; font-size: .8rem; color: #495057; }
    .fb-toast button.fb-x { margin-left: auto; border: 0; background: transparent; color: #adb5bd; font-size: 1.1rem; line-height: 1; }
    .fb-toast.success { --fb-cor: #198754; } .fb-toast.danger { --fb-cor: #dc3545; } .fb-toast.warning { --fb-cor: #f59f00; } .fb-toast.info { --fb-cor: #0d6efd; }
    .fb-toast.saindo { opacity: 0; transform: translateX(20px); transition: all .25s; }
    .fb-erro-campo { color: #dc3545; font-size: .78rem; margin-top: 4px; display: block; }
    .fb-invalido { border-color: #dc3545 !important; box-shadow: 0 0 0 .2rem rgba(220,53,69,.15) !important; }
    @keyframes fb-in { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: none; } }
</style>
<div class="fb-toasts" id="fb-toasts" role="status" aria-live="polite"></div>

<script>
(function () {
    const ICONES = { success: 'fa-circle-check', danger: 'fa-circle-exclamation', warning: 'fa-triangle-exclamation', info: 'fa-circle-info' };
    const TITULOS = { success: 'Tudo certo', danger: 'Atenção', warning: 'Aviso', info: 'Informação' };
    const mensagens = @json($mensagens);
    const errosServidor = @json($errosServidor);

    function el(tag, cls, html) { const e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    // ---------- Toasts ----------
    window.alerta = function (tipo, texto, opcoes = {}) {
        const box = document.getElementById('fb-toasts');
        const t = el('div', 'fb-toast ' + tipo);
        const lista = (opcoes.lista || []).map((i) => '<li>' + esc(i) + '</li>').join('');
        t.innerHTML = '<i class="fa-solid ' + ICONES[tipo] + ' fb-ico"></i><div><div class="fb-titulo">' + esc(opcoes.titulo || TITULOS[tipo]) + '</div>'
            + (texto ? '<div class="fb-texto">' + esc(texto) + '</div>' : '') + (lista ? '<ul>' + lista + '</ul>' : '') + '</div>';
        const x = el('button', 'fb-x', '&times;'); x.type = 'button'; x.setAttribute('aria-label', 'Fechar');
        const fechar = () => { t.classList.add('saindo'); setTimeout(() => t.remove(), 260); };
        x.onclick = fechar; t.appendChild(x); box.appendChild(t);
        const ms = opcoes.ms ?? (tipo === 'danger' ? 12000 : 6000);
        if (ms) setTimeout(fechar, ms);
        return t;
    };

    // ---------- Campos: mensagens ----------
    function rotulo(campo) {
        if (campo.dataset.nome) return campo.dataset.nome;
        const id = campo.id && document.querySelector('label[for="' + campo.id + '"]');
        if (id) return id.textContent.trim().replace(/\s*\*$/, '');
        const bloco = campo.closest('.col-md-1,.col-md-2,.col-md-3,.col-md-4,.col-md-6,.col-md-8,.mb-3,div');
        const l = bloco && bloco.querySelector('label');
        return (l ? l.textContent.trim() : (campo.placeholder || campo.name || 'Campo')).replace(/\s*\*$/, '').replace(/\s*\(.*?\)\s*$/, '');
    }
    function mensagemNativa(c) {
        const v = c.validity, n = rotulo(c);
        if (v.valueMissing) return c.type === 'checkbox' || c.type === 'radio' ? 'Selecione uma opção.' : 'Preencha o campo "' + n + '".';
        if (v.typeMismatch) return c.type === 'url' ? 'Informe um link completo, começando com https://' : c.type === 'email' ? 'Informe um e-mail válido.' : 'Valor inválido em "' + n + '".';
        if (v.rangeUnderflow) return '"' + n + '" deve ser no mínimo ' + c.min + '.';
        if (v.rangeOverflow) return '"' + n + '" deve ser no máximo ' + c.max + '.';
        if (v.tooShort) return '"' + n + '" precisa ter ao menos ' + c.minLength + ' caracteres.';
        if (v.tooLong) return '"' + n + '" aceita no máximo ' + c.maxLength + ' caracteres.';
        if (v.patternMismatch) return c.title || 'Formato inválido em "' + n + '".';
        if (v.stepMismatch) return 'Valor inválido em "' + n + '".';
        if (v.badInput) return 'Valor inválido em "' + n + '".';
        return 'Campo inválido.';
    }
    function limpar(c) {
        c.classList.remove('fb-invalido');
        const m = c.parentElement && c.parentElement.querySelector(':scope > .fb-erro-campo[data-de="' + (c.name || c.id) + '"]');
        if (m) m.remove();
        if (c.dataset.fbGrupo) { document.querySelectorAll('.fb-erro-campo[data-grupo="' + c.dataset.fbGrupo + '"]').forEach((e) => e.remove()); }
    }
    function marcar(c, msg) {
        limpar(c);
        c.classList.add('fb-invalido');
        const m = el('span', 'fb-erro-campo'); m.dataset.de = c.name || c.id; m.textContent = msg;
        const alvo = (c.type === 'checkbox' || c.type === 'radio') ? (c.closest('.mt-3,.mb-3,.form-check') || c) : c;
        if (alvo === c) { c.insertAdjacentElement('afterend', m); } else { m.dataset.grupo = c.name; c.dataset.fbGrupo = c.name; alvo.appendChild(m); }
    }
    function nomeSelector(nome) { return '[name="' + nome.replace(/"/g, '\\"') + '"]'; }
    function campoDoErro(form, chave) {
        // "criterios.0.nome" -> criterios[0][nome]; "tipos_permitidos" -> tipos_permitidos[]
        const partes = chave.split('.');
        const colchetes = partes[0] + partes.slice(1).map((p) => '[' + p + ']').join('');
        return form.querySelector(nomeSelector(colchetes)) || form.querySelector(nomeSelector(colchetes + '[]')) || form.querySelector(nomeSelector(partes[0] + '[]'));
    }

    // ---------- Máscaras ----------
    const MASCARAS = {
        cpf: (v) => v.replace(/\D/g, '').slice(0, 11).replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2'),
        telefone: (v) => { const d = v.replace(/\D/g, '').slice(0, 11); return d.length > 10 ? d.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3') : d.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3').replace(/-$/, ''); },
        cep: (v) => v.replace(/\D/g, '').slice(0, 8).replace(/(\d{5})(\d)/, '$1-$2'),
        inteiro: (v) => v.replace(/\D/g, ''),
        decimal: (v) => v.replace(',', '.').replace(/[^\d.]/g, '').replace(/^(\d*\.\d*).*$/, '$1'),
    };
    // Nota: só dígitos e UM separador decimal (vírgula ou ponto), até 2 casas, limitada a 0..data-max.
    // Acima do máximo o valor volta ao máximo e o campo avisa; letras, sinais e valores negativos nem entram.
    function aplicarNota(c) {
        const max = parseFloat(c.dataset.max || '10');
        const rotuloMax = String(max).replace('.', ',');
        const avisar = (msg) => { marcar(c, msg); clearTimeout(c._fbT); c._fbT = setTimeout(() => limpar(c), 3500); };
        const limparTexto = (v) => {
            let t = v.replace(/[^\d.,]/g, '').replace(',', '.');
            const i = t.indexOf('.');
            if (i !== -1) t = t.slice(0, i + 1) + t.slice(i + 1).replace(/\./g, '').slice(0, 2);
            return t;
        };
        c.setAttribute('maxlength', '6'); c.setAttribute('autocomplete', 'off'); c.setAttribute('inputmode', 'decimal');
        c.title = 'Nota de 0 a ' + rotuloMax;
        c.addEventListener('input', () => {
            const antes = c.value;
            let t = limparTexto(antes);
            if (t !== '' && t !== '.' && parseFloat(t) > max) { t = String(max); avisar('A nota máxima deste critério é ' + rotuloMax + '.'); }
            const mostrado = t.replace('.', ',');
            if (mostrado !== antes) { c.value = mostrado; c.dispatchEvent(new Event('input', { bubbles: true })); }
        });
        c.addEventListener('blur', () => {
            if (c.value === '' || c.value === ',') { c.value = ''; return; }
            const n = Math.min(Math.max(parseFloat(c.value.replace(',', '.')) || 0, 0), max);
            const f = String(Math.round(n * 100) / 100).replace('.', ',');
            if (f !== c.value) { c.value = f; c.dispatchEvent(new Event('input', { bubbles: true })); }
        });
        c.addEventListener('paste', (e) => { e.preventDefault(); const t = (e.clipboardData || window.clipboardData).getData('text'); c.value = limparTexto(t).replace('.', ','); c.dispatchEvent(new Event('input', { bubbles: true })); });
    }
    function aplicarMascara(c) {
        if (c.dataset.mascara === 'nota' && !c.dataset.fbMask) { c.dataset.fbMask = '1'; aplicarNota(c); return; }
        const f = MASCARAS[c.dataset.mascara];
        if (!f || c.dataset.fbMask) return;
        c.dataset.fbMask = '1';
        c.addEventListener('input', () => { const antes = c.value; const novo = f(antes); if (novo !== antes) c.value = novo; });
        if (c.value) c.value = f(c.value);
    }
    function preparar(raiz) { raiz.querySelectorAll('[data-mascara]').forEach(aplicarMascara); }

    // ---------- Validação do formulário ----------
    function validar(form) {
        const problemas = [];
        const erro = (campo, msg) => { if (!campo) return; marcar(campo, msg); problemas.push({ campo, msg }); };
        form.querySelectorAll('.fb-invalido').forEach(limpar);
        form.querySelectorAll('.fb-erro-campo').forEach((e) => e.remove());

        const vistos = new Set();
        Array.from(form.elements).forEach((c) => {
            if (!c.name || c.disabled || c.type === 'hidden' || c.type === 'submit' || c.type === 'button') return;
            if (c.type === 'checkbox' || c.type === 'radio') {
                if (!c.required) return;
                if (vistos.has(c.name)) return; vistos.add(c.name);
                if (!form.querySelector(nomeSelector(c.name) + ':checked')) erro(c, 'Selecione uma opção.');
                return;
            }
            if (typeof c.value === 'string' && c.type !== 'file' && c.type !== 'password' && c.type !== 'datetime-local') { const t = c.value.trim(); if (t !== c.value && c.type !== 'textarea') c.value = t; }
            if (!c.checkValidity()) erro(c, mensagemNativa(c));
        });

        if (form.dataset.regras && typeof window[form.dataset.regras] === 'function') window[form.dataset.regras](form, erro);
        return problemas;
    }
    function aoEnviar(ev) {
        const form = ev.currentTarget;
        form.dataset.acao = ev.submitter ? ev.submitter.value : '';
        const problemas = validar(form);
        if (problemas.length) {
            ev.preventDefault();
            const primeiro = problemas[0].campo;
            primeiro.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => primeiro.focus({ preventScroll: true }), 350);
            window.alerta('danger', 'Revise o formulário antes de salvar:', { titulo: problemas.length + (problemas.length === 1 ? ' problema encontrado' : ' problemas encontrados'), lista: problemas.slice(0, 6).map((p) => p.msg).concat(problemas.length > 6 ? ['…e mais ' + (problemas.length - 6)] : []) });
            return;
        }
        // Confirmação só depois de o formulário estar válido
        if (ev.submitter && ev.submitter.dataset.confirmar && !confirm(ev.submitter.dataset.confirmar)) { ev.preventDefault(); return; }
        // Evita duplo clique; o botão voltará ao normal se a navegação for cancelada (confirm)
        const botao = ev.submitter;
        if (botao && !form.dataset.enviando) {
            form.dataset.enviando = '1';
            const html = botao.innerHTML;
            // "disabled" no botão descartaria o name/value (ação) — usa um campo oculto
            if (botao.name) { const h = document.createElement('input'); h.type = 'hidden'; h.name = botao.name; h.value = botao.value; form.appendChild(h); }
            setTimeout(() => { botao.disabled = true; botao.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando…'; }, 0);
            setTimeout(() => { delete form.dataset.enviando; botao.disabled = false; botao.innerHTML = html; }, 15000);
        }
    }

    // Confirmação para qualquer botão de envio com data-confirmar (os formulários com data-validar confirmam depois de validar)
    document.addEventListener('submit', (ev) => {
        const botao = ev.submitter;
        if (!botao || !botao.dataset || !botao.dataset.confirmar || ev.defaultPrevented) return;
        if (ev.target.hasAttribute && ev.target.hasAttribute('data-validar')) return;
        if (!confirm(botao.dataset.confirmar)) ev.preventDefault();
    }, true);

    document.addEventListener('DOMContentLoaded', () => {
        preparar(document);
        document.querySelectorAll('form[data-validar]').forEach((form) => {
            form.noValidate = true;
            form.addEventListener('submit', aoEnviar);
            form.addEventListener('input', (e) => { if (e.target.classList && e.target.classList.contains('fb-invalido')) limpar(e.target); });
            form.addEventListener('change', (e) => { if (e.target.classList && e.target.classList.contains('fb-invalido')) limpar(e.target); });
            new MutationObserver(() => preparar(form)).observe(form, { childList: true, subtree: true });

            // Erros devolvidos pelo servidor: marca cada campo e rola até o primeiro
            const chaves = Object.keys(errosServidor);
            let primeiro = null;
            chaves.forEach((k) => { const c = campoDoErro(form, k); if (c) { marcar(c, errosServidor[k][0]); primeiro = primeiro || c; } });
            if (primeiro) setTimeout(() => primeiro.scrollIntoView({ behavior: 'smooth', block: 'center' }), 200);
        });

        // Erros do servidor sem formulário com data-validar (ou sem campo correspondente) viram alerta
        const lista = Object.values(errosServidor).map((m) => m[0]);
        if (lista.length) window.alerta('danger', 'O servidor não aceitou os dados enviados:', { titulo: 'Não foi possível salvar', lista: lista.slice(0, 8), ms: 15000 });

        // Mensagens de sessão (evita repetir o que a página já mostra em um .alert)
        const jaNaPagina = Array.from(document.querySelectorAll('.alert')).map((a) => a.textContent);
        mensagens.forEach((m) => { if (!jaNaPagina.some((t) => t.includes(m.texto))) window.alerta(m.tipo, m.texto); });
    });
})();
</script>
