@props(['name' => 'descricao', 'value' => '', 'uploadUrl' => null, 'altura' => 340, 'placeholder' => 'Escreva aqui. Use a barra acima para formatar o texto e inserir imagens, tabelas e links.'])
{{--
  Editor de texto rico (WYSIWYG) completo: títulos, negrito/itálico/sublinhado, cores, alinhamento, listas, recuo, citação,
  links, tabelas, linha horizontal e imagens (envio para o servidor, arrastar/colar). O HTML é limpo no servidor ao salvar.
--}}
@php $id = 'editor-' . $name; @endphp
<div class="editor-rico" data-upload="{{ $uploadUrl }}" data-altura="{{ $altura }}">
    <textarea name="{{ $name }}" id="{{ $id }}" class="form-control" rows="10" placeholder="{{ $placeholder }}">{{ $value }}</textarea>
    <div class="editor-aviso small text-muted mt-2"><i class="fa-regular fa-lightbulb me-1"></i> Dica: arraste ou cole uma imagem direto no texto. Imagens até 4 MB (JPG, PNG, WebP ou GIF).</div>
    <div class="alert alert-warning small mt-2 d-none editor-falha">Não foi possível carregar o editor de texto. Você ainda pode escrever no campo acima (texto simples).</div>
</div>

@once
    <script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/super-build/ckeditor.js"></script>
    <style>
        .editor-rico .ck-editor__editable_inline { min-height: var(--alt-editor, 340px); border-radius: 0 0 12px 12px !important; font-size: .98rem; line-height: 1.7; }
        .editor-rico .ck.ck-toolbar { border-radius: 12px 12px 0 0 !important; background: #f6f8fa; }
        .editor-rico .ck.ck-editor__main > .ck-editor__editable:not(.ck-focused) { border-color: #d5dce5; }
        .editor-rico .ck.ck-editor__main > .ck-editor__editable.ck-focused { border-color: #10b981; box-shadow: 0 0 0 4px rgba(16,185,129,.14); }
        .editor-rico .ck-content img { max-width: 100%; height: auto; }
        .editor-rico .ck-content figure.table table { width: 100%; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.editor-rico').forEach(function (caixa) {
                var area = caixa.querySelector('textarea');
                var falha = function () { caixa.querySelector('.editor-falha').classList.remove('d-none'); };
                if (!window.CKEDITOR || !CKEDITOR.ClassicEditor) { falha(); return; }
                caixa.style.setProperty('--alt-editor', (caixa.dataset.altura || 340) + 'px');

                var token = document.querySelector('meta[name="csrf-token"]');
                var config = {
                    toolbar: {
                        items: ['undo', 'redo', '|', 'heading', '|', 'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript', '|',
                                'fontSize', 'fontColor', 'fontBackgroundColor', '|', 'alignment', '|', 'bulletedList', 'numberedList', 'outdent', 'indent', '|',
                                'link', 'insertImage', 'insertTable', 'blockQuote', 'horizontalLine', '|', 'removeFormat', 'sourceEditing'],
                        shouldNotGroupWhenFull: true
                    },
                    heading: { options: [
                        { model: 'paragraph', title: 'Parágrafo', class: 'ck-heading_paragraph' },
                        { model: 'heading2', view: 'h2', title: 'Título', class: 'ck-heading_heading2' },
                        { model: 'heading3', view: 'h3', title: 'Subtítulo', class: 'ck-heading_heading3' },
                        { model: 'heading4', view: 'h4', title: 'Título menor', class: 'ck-heading_heading4' }
                    ] },
                    fontSize: { options: ['tiny', 'small', 'default', 'big', 'huge'] },
                    image: { toolbar: ['imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|', 'toggleImageCaption', 'imageTextAlternative'] },
                    table: { contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'] },
                    link: { addTargetToExternalLinks: true, defaultProtocol: 'https://' },
                    placeholder: area.getAttribute('placeholder') || '',
                    language: 'pt-br',
                    removePlugins: ['AIAssistant', 'MultiLevelList', 'CKBox', 'CKFinder', 'EasyImage', 'MediaEmbed', 'Base64UploadAdapter', 'RealTimeCollaborativeComments', 'RealTimeCollaborativeTrackChanges',
                        'RealTimeCollaborativeRevisionHistory', 'PresenceList', 'Comments', 'TrackChanges', 'TrackChangesData', 'RevisionHistory', 'Pagination', 'WProofreader',
                        'MathType', 'SlashCommand', 'Template', 'DocumentOutline', 'FormatPainter', 'TableOfContents', 'PasteFromOfficeEnhanced', 'CaseChange', 'ExportPdf', 'ExportWord', 'ImportWord']
                };
                // Envio de imagens: arquivo -> nosso servidor -> endereço público (o pacote do editor não traz adaptador de upload)
                if (caixa.dataset.upload) {
                    config.extraPlugins = [function MeuUploadAdapter(editor) {
                        editor.plugins.get('FileRepository').createUploadAdapter = function (loader) {
                            return {
                                upload: function () {
                                    return loader.file.then(function (arquivo) {
                                        var dados = new FormData();
                                        dados.append('upload', arquivo);
                                        return fetch(caixa.dataset.upload, {
                                            method: 'POST', body: dados, credentials: 'same-origin',
                                            headers: { 'X-CSRF-TOKEN': token ? token.content : '', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                                        }).then(function (r) {
                                            return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, j: j, status: r.status }; });
                                        }).then(function (res) {
                                            if (!res.ok || !res.j.url) {
                                                throw (res.j && res.j.error && res.j.error.message) ? res.j.error.message : (res.status === 419 ? 'Sua sessão expirou. Recarregue a página.' : 'Não foi possível enviar a imagem.');
                                            }
                                            return { default: res.j.url };
                                        }, function () { throw 'Falha de conexão ao enviar a imagem.'; });
                                    });
                                },
                                abort: function () {}
                            };
                        };
                    }];
                }

                CKEDITOR.ClassicEditor.create(area, config).then(function (editor) {
                    // O formulário envia o conteúdo atual do editor
                    var form = area.closest('form');
                    if (form) form.addEventListener('submit', function () { area.value = editor.getData(); });
                    caixa._editor = editor;
                }).catch(function (e) { console.error(e); falha(); });
            });
        });
    </script>
@endonce
