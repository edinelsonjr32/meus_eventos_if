{{--
    Proteção de formulários longos: mantém o login vivo enquanto a pessoa preenche e guarda um rascunho no navegador
    (localStorage), para nada se perder se a página recarregar, a sessão expirar ou o servidor responder com erro.
    Uso: FormSeguro.sessaoViva(urlDoPing) e FormSeguro.rascunho(chave) -> { ler(), salvar(dados), apagar() }.
--}}
@once
<script>
window.FormSeguro = window.FormSeguro || {
    sessaoViva(url) {
        if (!url) return;
        const ping = () => fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).catch(() => {});
        setInterval(ping, 5 * 60 * 1000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) ping(); });
    },
    rascunho(chave) {
        return {
            ler() { try { return chave ? JSON.parse(localStorage.getItem(chave) || 'null') : null; } catch (e) { return null; } },
            salvar(dados) { try { if (chave) localStorage.setItem(chave, JSON.stringify(dados)); } catch (e) {} },
            apagar() { try { if (chave) localStorage.removeItem(chave); } catch (e) {} },
        };
    },
};
</script>
@endonce
