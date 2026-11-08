{{--
  Ação em lote sobre trabalhos selecionados na tabela (mesmo fluxo nas duas telas).
  1) marque os trabalhos (caixas .sel-trabalho com data-atribuir / data-triagem)  2) escolha a ação  3) confira o resumo e confirme.
  Props: action (URL), avaliadores (coleção de User com ->carga), acoes (lista de ['valor','rotulo','icone','tom']),
         unidadeAtribuir (texto do resumo), id (id do formulário).
--}}
@props(['action', 'avaliadores', 'acoes' => [], 'id' => 'lote', 'restricoes' => []])

@php
    $acoes = $acoes ?: [['valor' => 'atribuir', 'rotulo' => 'Atribuir avaliadores', 'icone' => 'fa-user-plus', 'tom' => 'btn-outline-success']];
@endphp

<form id="{{ $id }}" action="{{ $action }}" method="POST" class="glass-card bg-white mb-3 lote-barra" data-lote
      data-restricoes="{{ json_encode($restricoes, JSON_UNESCAPED_UNICODE) }}"
      data-avaliadores="{{ json_encode($avaliadores->mapWithKeys(fn ($u) => [$u->id => $u->name]), JSON_UNESCAPED_UNICODE) }}">
    @csrf
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="fw-bold text-success"><i class="fa-solid fa-layer-group me-1"></i> Ação em lote</div>
        <div class="small">
            <span class="sb" style="background:#f1f5f9;color:#334155;border-color:#cbd5e1;" data-lote-contagem><i class="fa-regular fa-square-check"></i> 0 selecionado(s)</span>
            <a href="#" class="small ms-2 text-decoration-none d-none" data-lote-limpar><i class="fa-solid fa-xmark"></i> Limpar seleção</a>
        </div>
    </div>

    <ol class="small text-muted ps-3 mb-3">
        <li>Marque os trabalhos na tabela abaixo (a caixa do cabeçalho marca todos desta página).</li>
        <li>Escolha a ação e, se for atribuir, os avaliadores.</li>
        <li>Confira o resumo e confirme: o que não puder ser feito é ignorado e explicado no final.</li>
    </ol>

    <div class="d-flex flex-wrap gap-2 mb-3" role="radiogroup" aria-label="Ação em lote">
        @foreach($acoes as $i => $a)
            <input type="radio" class="btn-check" name="acao" id="{{ $id }}-a{{ $i }}" value="{{ $a['valor'] }}" autocomplete="off" @checked($i === 0)>
            <label class="btn {{ $a['tom'] ?? 'btn-outline-secondary' }}" for="{{ $id }}-a{{ $i }}"><i class="fa-solid {{ $a['icone'] }}"></i> {{ $a['rotulo'] }}</label>
        @endforeach
    </div>

    <div data-lote-painel="atribuir">
        <div class="row g-2">
            <div class="col-lg-8">
                <label class="small fw-bold text-secondary" for="{{ $id }}-avaliadores">Avaliadores</label>
                <select name="avaliadores[]" id="{{ $id }}-avaliadores" class="form-select js-select-avaliadores-lote" multiple data-placeholder="Busque e selecione um ou mais avaliadores…" style="width:100%">
                    @foreach($avaliadores as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->carga }} em aberto)</option>
                    @endforeach
                </select>
                @if($avaliadores->isEmpty()) <div class="form-text text-danger">Nenhum avaliador designado no evento (use "Papéis").</div> @endif
            </div>
            <div class="col-lg-4">
                <label class="small fw-bold text-secondary" for="{{ $id }}-prazo">Prazo (opcional)</label>
                <input type="date" name="prazo" id="{{ $id }}-prazo" class="form-control" min="{{ now()->addDay()->format('Y-m-d') }}">
            </div>
        </div>
    </div>

    <div class="alert alert-light border small mt-3 mb-2" data-lote-resumo>Selecione ao menos um trabalho.</div>
    <div class="small mb-2 d-none" data-lote-impedimentos></div>

    <div class="text-end">
        <button type="submit" class="btn btn-success" data-lote-enviar disabled><i class="fa-solid fa-check"></i> <span data-lote-rotulo-botao>Confirmar</span></button>
    </div>
</form>

@once('assets-lote-avaliadores')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-5-theme/1.3.0/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/i18n/pt-BR.min.js"></script>
    <script>
        window.addEventListener('load', function () {
            document.querySelectorAll('form[data-lote]').forEach(function (form) {
                const sel = () => Array.from(document.querySelectorAll('.sel-trabalho:checked'));
                const $av = window.jQuery && jQuery(form).find('.js-select-avaliadores-lote');
                if ($av && jQuery.fn.select2) {
                    $av.select2({ theme: 'bootstrap-5', language: 'pt-BR', width: '100%', closeOnSelect: false, placeholder: $av.data('placeholder'), allowClear: true });
                    $av.on('change', atualizar);
                }
                const acao = () => (form.querySelector('input[name=acao]:checked') || {}).value;
                const nAval = () => form.querySelectorAll('select[name="avaliadores[]"] option:checked').length;
                const rotuloAcao = { atribuir: 'Atribuir avaliadores', triagem: 'Iniciar triagem', avaliacao: 'Encaminhar para avaliação', certificados: 'Emitir certificados' };
                const restricoes = JSON.parse(form.dataset.restricoes || '{}');
                const nomes = JSON.parse(form.dataset.avaliadores || '{}');
                const esc = (t) => String(t).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

                // Impedimentos por avaliador escolhido nos trabalhos aptos selecionados
                function impedimentos(aptos) {
                    const ids = Array.from(form.querySelectorAll('select[name="avaliadores[]"] option:checked')).map(o => o.value);
                    const porAval = {}; let bloqueados = 0, total = 0;
                    aptos.forEach(c => {
                        ids.forEach(id => {
                            total++;
                            const r = (restricoes[c.value] || {})[id];
                            if (r) { bloqueados++; (porAval[id] = porAval[id] || []).push({ codigo: c.dataset.codigo, r }); }
                        });
                    });
                    return { ids, porAval, bloqueados, total };
                }
                const cores = { conflito: ['#fee2e2', '#b91c1c', 'fa-user-slash', 'conflito de interesse'], atribuido: ['#e0e7ff', '#4338ca', 'fa-user-check', 'já atribuído'], recusou: ['#ffedd5', '#c2410c', 'fa-hand', 'recusou'] };

                function atualizar() {
                    const marcados = sel();
                    const a = acao();
                    const campo = (a === 'triagem' || a === 'avaliacao' || a === 'certificados') ? a : 'atribuir';
                    const aptos = marcados.filter(c => c.dataset[campo] === '1');
                    const ignorados = marcados.length - aptos.length;
                    const painel = form.querySelector('[data-lote-painel="atribuir"]');
                    if (painel) painel.classList.toggle('d-none', a !== 'atribuir');

                    form.querySelector('[data-lote-contagem]').innerHTML = '<i class="fa-regular fa-square-check"></i> ' + marcados.length + ' selecionado(s)';
                    form.querySelector('[data-lote-limpar]').classList.toggle('d-none', marcados.length === 0);

                    const resumo = form.querySelector('[data-lote-resumo]');
                    const botao = form.querySelector('[data-lote-enviar]');
                    let ok = false, texto = 'Selecione ao menos um trabalho.';
                    if (marcados.length) {
                        if (a === 'atribuir') {
                            const n = nAval();
                            if (!n) texto = '<strong>' + aptos.length + '</strong> trabalho(s) apto(s). Escolha ao menos um avaliador.';
                            else if (!aptos.length) texto = 'Nenhum dos trabalhos selecionados pode receber avaliadores neste status.';
                            else {
                                const imp = impedimentos(aptos);
                                const previstas = imp.total - imp.bloqueados;
                                if (previstas === 0) texto = '<strong class="text-danger">Nenhuma atribuição possível:</strong> todos os pares avaliador × trabalho selecionados têm impedimento (veja abaixo).';
                                else { ok = true; texto = 'Serão feitas <strong>' + previstas + '</strong> atribuição(ões) (' + aptos.length + ' trabalho(s) × ' + n + ' avaliador(es))' + (imp.bloqueados ? ', <strong class="text-warning">' + imp.bloqueados + ' impedida(s)</strong>' : '') + '.'; }
                            }
                        } else if (a === 'certificados') {
                            if (!aptos.length) texto = '<strong class="text-danger">Nenhum trabalho está aguardando emissão de certificado</strong> (aprovado nas duas partes e apresentado).';
                            else { ok = true; texto = '<strong>' + aptos.length + '</strong> trabalho(s) terão os certificados emitidos para todos os autores (e-mail enviado) e passarão a <em>Concluído</em>.'; }
                        } else if (a === 'avaliacao') {
                            if (!aptos.length) texto = '<strong class="text-danger">Nenhum trabalho pode ser encaminhado:</strong> é preciso estar em triagem e já ter o mínimo de avaliadores da chamada.';
                            else { ok = true; texto = '<strong>' + aptos.length + '</strong> trabalho(s) serão encaminhados para avaliação (os avaliadores já atribuídos passam a poder avaliar).'; }
                        } else {
                            if (!aptos.length) texto = 'Nenhum dos trabalhos selecionados está aguardando triagem (só submetidos ou resubmetidos).';
                            else { ok = true; texto = '<strong>' + aptos.length + '</strong> trabalho(s) irão para a triagem.'; }
                        }
                        if (ignorados && aptos.length) texto += ' <span class="text-warning fw-bold"><i class="fa-solid fa-triangle-exclamation"></i> ' + ignorados + ' selecionado(s) será(ão) ignorado(s) por status.</span>';
                    }
                    resumo.innerHTML = texto;
                    botao.disabled = !ok;

                    // Lista dos impedimentos: por avaliador, em quais trabalhos e por quê
                    const caixa = form.querySelector('[data-lote-impedimentos]');
                    let html = '';
                    if (a === 'atribuir' && aptos.length) {
                        const imp = impedimentos(aptos);
                        Object.keys(imp.porAval).forEach(id => {
                            const itens = imp.porAval[id];
                            const grupos = {};
                            itens.forEach(i => { (grupos[i.r.tipo] = grupos[i.r.tipo] || []).push(i.codigo); });
                            Object.keys(grupos).forEach(tipo => {
                                const c = cores[tipo] || ['#f1f5f9', '#334155', 'fa-circle', tipo];
                                const motivo = (itens.find(i => i.r.tipo === tipo) || {}).r.motivo;
                                html += '<div class="mb-1"><span class="sb" style="background:' + c[0] + ';color:' + c[1] + ';border-color:' + c[1] + '55;"><i class="fa-solid ' + c[2] + '"></i> ' + esc(nomes[id] || id) + ' — ' + c[3] + '</span> <span class="text-muted">em ' + esc(grupos[tipo].join(', ')) + (tipo === 'conflito' ? ' (' + esc(motivo) + ')' : '') + '</span></div>';
                            });
                        });
                    }
                    if (a === 'avaliacao' || a === 'certificados') {
                        const attr = a === 'avaliacao' ? 'avaliacao' : 'certificados';
                        const barrados = marcados.filter(c => c.dataset[attr] !== '1');
                        const grupos = {};
                        barrados.forEach(c => { const m = (a === 'avaliacao' ? c.dataset.motivoAvaliacao : c.dataset.motivoCertificados) || 'não pode ser processado'; (grupos[m] = grupos[m] || []).push(c.dataset.codigo); });
                        Object.keys(grupos).forEach(m => {
                            html += '<div class="mb-1"><span class="sb" style="background:#fef3c7;color:#b45309;border-color:#fcd34d;"><i class="fa-solid fa-triangle-exclamation"></i> ' + esc(m) + '</span> <span class="text-muted">em ' + esc(grupos[m].join(', ')) + '</span></div>';
                        });
                    }
                    caixa.innerHTML = html;
                    caixa.classList.toggle('d-none', html === '');
                    form.querySelector('[data-lote-rotulo-botao]').textContent = ok ? (rotuloAcao[a] || 'Confirmar') + ' (' + aptos.length + ')' : (rotuloAcao[a] || 'Confirmar');
                    form._aptos = aptos.length;
                }

                document.addEventListener('change', function (e) {
                    if (e.target.classList && e.target.classList.contains('sel-trabalho')) atualizar();
                    if (e.target.classList && e.target.classList.contains('sel-todos')) {
                        document.querySelectorAll('.sel-trabalho').forEach(c => { c.checked = e.target.checked; });
                        atualizar();
                    }
                });
                form.addEventListener('change', atualizar);
                form.querySelector('[data-lote-limpar]').addEventListener('click', function (e) {
                    e.preventDefault();
                    document.querySelectorAll('.sel-trabalho, .sel-todos').forEach(c => { c.checked = false; });
                    atualizar();
                });
                form.addEventListener('submit', function (e) {
                    atualizar();
                    if (form.querySelector('[data-lote-enviar]').disabled) { e.preventDefault(); return; }
                    const msg = form.querySelector('[data-lote-resumo]').innerText.replace(/\s+/g, ' ');
                    if (!confirm('Confirmar ' + (rotuloAcao[acao()] || 'ação').toLowerCase() + ' em lote?\n\n' + msg)) e.preventDefault();
                });
                atualizar();
            });
        });
    </script>
@endonce
