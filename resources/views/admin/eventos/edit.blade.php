<x-app-layout>
    <script src="https://cdn.ckeditor.com/ckeditor5/41.0.0/classic/ckeditor.js"></script>

    <div class="row justify-content-center">
        <div class="col-lg-10">

            <form action="{{ route('admin.eventos.update', $evento->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="tab-content" id="pills-tabContent">

                    <div class="tab-pane fade show active" id="pills-dados">
                        <div class="glass-card">
                            <h6 class="text-uppercase text-secondary fw-bold mb-4 small">Informações Básicas</h6>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-secondary small">TÍTULO DO EVENTO</label>
                                <input type="text" name="titulo" value="{{ $evento->titulo }}" class="form-control form-control-lg" required>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-secondary small">DATA INÍCIO</label>
                                    <input type="datetime-local" name="data_inicio" value="{{ $evento->data_inicio->format('Y-m-d\TH:i') }}" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-secondary small">DATA FIM</label>
                                    <input type="datetime-local" name="data_fim" value="{{ $evento->data_fim->format('Y-m-d\TH:i') }}" class="form-control" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-secondary small">LOCAL (Texto)</label>
                                <input type="text" name="local" value="{{ $evento->local }}" class="form-control" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-secondary small">DESCRIÇÃO (Editor Visual CKEditor)</label>
                                <textarea name="descricao" id="editor_descricao" class="form-control" rows="8">{{ $evento->descricao }}</textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-secondary small">COR DO TEMA (Site)</label>
                                <input type="color" name="cor_fundo" class="form-control form-control-color w-100" value="{{ $evento->configuracoes['cor_fundo'] ?? '#10b981' }}">
                            </div>

                            <hr class="my-4 text-secondary opacity-25">
                            <h6 class="text-uppercase text-secondary fw-bold mb-3 small">
                                <i class="fa-solid fa-location-crosshairs me-2"></i> Controle de Presença (GPS)
                            </h6>

                            <div class="alert alert-info border-0 d-flex gap-3 align-items-center mb-3">
                                <i class="fa-solid fa-info-circle fs-4"></i>
                                <small>Se preenchido, o aluno só poderá confirmar presença se estiver dentro do raio permitido.</small>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-secondary small">LATITUDE</label>
                                    <input type="text" name="latitude" class="form-control" placeholder="Ex: -1.9234" value="{{ $evento->latitude }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-secondary small">LONGITUDE</label>
                                    <input type="text" name="longitude" class="form-control" placeholder="Ex: -55.4321" value="{{ $evento->longitude }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-secondary small">RAIO (Metros)</label>
                                    <input type="number" name="raio_permitido" class="form-control" value="{{ $evento->raio_permitido ?? 300 }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    </div>

                <div class="d-flex justify-content-end pt-4 border-top mt-4 mb-5">
                    <a href="{{ route('admin.eventos.index') }}" class="btn btn-light me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary-custom px-5 py-3 shadow fw-bold">
                        <i class="fa-solid fa-save me-2"></i> Salvar Todas as Alterações
                    </button>
                </div>

            </form>
        </div>
    </div>

    @php
        // ... (dados assinaturas)
        $dadosAssinaturas = $evento->assinaturas->map(function($s){
            return [
                'id' => $s->id,
                'nome' => $s->nome,
                'cargo' => $s->cargo,
                'arquivo_atual' => $s->arquivo_assinatura,
                'tempId' => $s->id
            ];
        });
    @endphp

    <script>
        // Inicialização do CKEDITOR 5
        document.addEventListener('DOMContentLoaded', function() {
            ClassicEditor
                .create( document.querySelector( '#editor_descricao' ), {
                    // Configurações básicas (opcional)
                    toolbar: {
                        items: [
                            'heading', '|', 'bold', 'italic', '|',
                            'bulletedList', 'numberedList', '|',
                            'link', 'blockquote', 'undo', 'redo'
                        ]
                    }
                })
                .catch( error => {
                    console.error( error );
                });
        });

        // Lógica AlpineJS para Assinaturas (Mantida)
        function signatureManager() {
            return {
                signatures: @json($dadosAssinaturas),
                removedIds: [],

                addSignature() {
                    this.signatures.push({
                        id: null,
                        nome: '',
                        cargo: '',
                        arquivo_atual: null,
                        tempId: Date.now()
                    });
                },

                removeSignature(index) {
                    let sig = this.signatures[index];
                    if (sig.id) {
                        this.removedIds.push(sig.id);
                    }
                    this.signatures.splice(index, 1);
                }
            }
        }
    </script>
</x-app-layout>
