{{--
    Máscaras automáticas de formulário (sem dependências), valem para campos fixos e para os criados depois (Alpine):
      CPF (name="cpf" / "...[cpf]"), telefone/celular/whatsapp, ORCID e CEP. Também aceita data-mascara-auto="cpf|telefone|orcid|cep".
    Campos com data-mascara (componente de feedback) e .cpf-mask (jQuery Mask das telas antigas) continuam com o tratamento deles.
--}}
<script>
(function () {
    if (window.__mascarasAutomaticas) return; window.__mascarasAutomaticas = true;

    const digitos = (v) => v.replace(/\D/g, '');
    const REGRAS = {
        cpf: { max: 14, ph: '000.000.000-00', mode: 'numeric', f: (v) => digitos(v).slice(0, 11).replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2') },
        telefone: { max: 15, ph: '(00) 00000-0000', mode: 'tel', f: (v) => {
            const d = digitos(v).slice(0, 11);
            if (d.length <= 2) return d.length ? '(' + d : '';
            if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
            if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
            return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
        } },
        orcid: { max: 19, ph: '0000-0000-0000-0000', mode: 'text', f: (v) => {
            const t = v.toUpperCase().replace(/[^0-9X]/g, '').slice(0, 16);
            return t.replace(/(.{4})(?=.)/g, '$1-');
        } },
        cep: { max: 9, ph: '00000-000', mode: 'numeric', f: (v) => digitos(v).slice(0, 8).replace(/(\d{5})(\d)/, '$1-$2') },
    };

    function tipoDe(c) {
        if (!(c instanceof HTMLInputElement) || !['text', 'tel', ''].includes(c.type)) return null;
        if (c.hasAttribute('data-mascara') || c.classList.contains('cpf-mask') || c.dataset.mascaraOff !== undefined) return null;
        if (c.dataset.mascaraAuto && REGRAS[c.dataset.mascaraAuto]) return c.dataset.mascaraAuto;
        const n = (c.getAttribute('name') || '').toLowerCase();
        if (/(^|\[)cpf(\]|$)/.test(n)) return 'cpf';
        if (/(^|\[)(telefone|celular|whatsapp)(\]|$)/.test(n)) return 'telefone';
        if (/(^|\[)orcid(\]|$)/.test(n)) return 'orcid';
        if (/(^|\[)cep(\]|$)/.test(n)) return 'cep';
        return null;
    }

    function preparar(c) {
        const tipo = tipoDe(c); if (!tipo) return null;
        const r = REGRAS[tipo];
        c.setAttribute('maxlength', r.max);
        if (!c.getAttribute('inputmode')) c.setAttribute('inputmode', r.mode);
        if (!c.getAttribute('placeholder')) c.setAttribute('placeholder', r.ph);
        if (!c.getAttribute('autocomplete')) c.setAttribute('autocomplete', tipo === 'telefone' ? 'tel' : 'off');
        return r;
    }

    function aplicar(c, dispara) {
        const r = preparar(c); if (!r) return;
        const novo = r.f(c.value);
        if (novo !== c.value) { c.value = novo; if (dispara) c.dispatchEvent(new Event('input', { bubbles: true })); }
    }

    // digitação e colagem (campos presentes ou criados depois)
    document.addEventListener('input', (e) => aplicar(e.target, true));
    document.addEventListener('focusin', (e) => preparar(e.target));

    // valores já preenchidos (old(), dados salvos) e campos inseridos depois
    const varrer = (raiz) => (raiz.querySelectorAll ? raiz.querySelectorAll('input') : []).forEach((c) => aplicar(c, true));
    const iniciar = () => {
        varrer(document);
        new MutationObserver((ms) => ms.forEach((m) => m.addedNodes.forEach((n) => { if (n.nodeType === 1) { if (n.tagName === 'INPUT') aplicar(n, true); else varrer(n); } }))).observe(document.body, { childList: true, subtree: true });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();
</script>
