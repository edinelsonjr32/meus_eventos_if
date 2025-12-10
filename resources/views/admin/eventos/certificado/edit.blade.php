<x-app-layout>

    <div class="row justify-content-center">
        <div class="col-lg-10">

            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('admin.eventos.edit', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div>
                        <h3 class="fw-bold m-0"><i class="fa-solid fa-award me-2"></i> Configurar Certificado</h3>
                        <small class="text-secondary">Evento: {{ $evento->titulo }}</small>
                    </div>
                </div>
            </div>

            <form action="{{ route('admin.eventos.certificado.update', $evento->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @if(session('success'))
                    <div class="alert alert-success small p-2 mb-3">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger small p-2 mb-3">{{ session('error') }}</div>
                @endif


                <ul class="nav nav-pills mb-4 gap-2" id="cert-pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold border" id="cert-layout-tab" data-bs-toggle="pill" data-bs-target="#cert-layout" type="button">
                            <i class="fa-solid fa-paintbrush me-2"></i> Layout & Texto
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold border" id="cert-ass-tab" data-bs-toggle="pill" data-bs-target="#cert-ass" type="button">
                            <i class="fa-solid fa-signature me-2"></i> Assinaturas
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="cert-pills-tabContent">

                    <div class="tab-pane fade show active" id="cert-layout">
                        <div class="glass-card">
                            <h6 class="text-uppercase text-secondary fw-bold mb-4 small">Personalização Visual</h6>

                            <div class="row g-4 mb-5">

                                <div class="col-md-6" x-data="{ showPreview: {{ $evento->caminho_fundo ? 'true' : 'false' }}, markForRemoval: false }">
                                    <label class="form-label fw-bold text-secondary small">IMAGEM DE FUNDO (A4 Paisagem)</label>

                                    <input type="file" name="imagem_fundo" class="form-control mb-2 @error('imagem_fundo') is-invalid @enderror" accept="image/*" @change="markForRemoval = false">

                                    @error('imagem_fundo')
                                        <div class="alert alert-danger py-2 mt-2 small border-0">{{ $message }}</div>
                                    @enderror

                                    <small class="text-muted d-block mb-2">Recomendado: 3508x2480 px (JPG/PNG).</small>

                                    <input type="hidden" name="remover_fundo" :value="markForRemoval ? 1 : 0">

                                    <div x-show="showPreview && !markForRemoval" class="border p-2 rounded bg-light mt-2 position-relative" style="{{ $evento->caminho_fundo ? '' : 'display: none;' }}">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <p class="small fw-bold mb-0 text-success"><i class="fa-solid fa-check me-1"></i> Imagem Atual</p>
                                            <button type="button" class="btn btn-danger btn-sm py-0 px-2" style="font-size: 11px;" @click="markForRemoval = true" title="Remover imagem e usar padrão">
                                                <i class="fa-solid fa-trash me-1"></i> Remover
                                            </button>
                                        </div>
                                        @if($evento->caminho_fundo)
                                            <img src="{{ asset('storage/' . $evento->caminho_fundo) }}" class="img-fluid rounded shadow-sm" style="max-height: 120px;">
                                        @endif
                                    </div>
                                    <div x-show="markForRemoval" class="alert alert-warning py-2 mt-2 small border-0" style="display: none;">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> A imagem será removida ao salvar.
                                        <button type="button" class="btn btn-link p-0 text-dark fw-bold ms-2" style="font-size: 11px; text-decoration: none;" @click="markForRemoval = false">Desfazer</button>
                                    </div>
                                </div>

                                <div class="col-md-6" x-data="{ showPreview: {{ $evento->caminho_brasao ? 'true' : 'false' }}, markForRemoval: false }">
                                    <label class="form-label fw-bold text-secondary small">BRASÃO / LOGO (Topo)</label>

                                    <input type="file" name="imagem_brasao" class="form-control mb-2 @error('imagem_brasao') is-invalid @enderror" accept="image/*" @change="markForRemoval = false">

                                    @error('imagem_brasao')
                                        <div class="alert alert-danger py-2 mt-2 small border-0">{{ $message }}</div>
                                    @enderror

                                    <small class="text-muted d-block mb-2">Fundo transparente recomendado.</small>

                                    <input type="hidden" name="remover_brasao" :value="markForRemoval ? 1 : 0">

                                    <div x-show="showPreview && !markForRemoval" class="border p-2 rounded bg-light mt-2 position-relative" style="{{ $evento->caminho_brasao ? '' : 'display: none;' }}">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <p class="small fw-bold mb-0 text-success"><i class="fa-solid fa-check me-1"></i> Imagem Atual</p>
                                            <button type="button" class="btn btn-danger btn-sm py-0 px-2" style="font-size: 11px;" @click="markForRemoval = true" title="Remover e usar Brasão da República">
                                                <i class="fa-solid fa-trash me-1"></i> Remover
                                            </button>
                                        </div>
                                        @if($evento->caminho_brasao)
                                            <img src="{{ asset('storage/' . $evento->caminho_brasao) }}" class="img-fluid" style="max-height: 80px;">
                                        @endif
                                    </div>
                                    <div x-show="markForRemoval" class="alert alert-warning py-2 mt-2 small border-0" style="display: none;">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Será usado o Brasão padrão.
                                        <button type="button" class="btn btn-link p-0 text-dark fw-bold ms-2" style="font-size: 11px; text-decoration: none;" @click="markForRemoval = false">Desfazer</button>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4 text-secondary opacity-25">
                            <h6 class="text-uppercase text-secondary fw-bold mb-3 small">Textos do Documento</h6>

                            <div class="alert alert-light border border-secondary border-opacity-25 mb-4">
                                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-code me-2"></i> Variáveis Dinâmicas</h6>
                                <p class="small text-secondary mb-2">Copie e cole estes códigos no texto abaixo. Eles serão substituídos pelos dados reais de cada aluno.</p>
                                <div class="d-flex flex-wrap gap-2 font-monospace" style="font-size: 0.75rem;">
                                    <span class="badge bg-white text-dark border">{PARTICIPANTE}</span>
                                    <span class="badge bg-white text-dark border">{CPF}</span>
                                    <span class="badge bg-white text-dark border">{ATIVIDADE}</span>
                                    <span class="badge bg-white text-dark border">{EVENTO}</span>
                                    <span class="badge bg-white text-dark border">{CARGA_HORARIA}</span>
                                    <span class="badge bg-white text-dark border">{DATA_EVENTO}</span>
                                    <span class="badge bg-white text-dark border">{DATA_EMISSAO}</span>
                                    <span class="badge bg-white text-dark border">{TIPO_PARTICIPACAO}</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary small">CABEÇALHO (Institucional)</label>
                                <textarea name="cert_cabecalho" class="form-control" rows="3" placeholder="Ministério da Educação...">{{ $evento->cert_cabecalho ?? "Ministério da Educação\nSecretaria de Educação Profissional e Tecnológica\nInstituto Federal do Pará - Campus Óbidos" }}</textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary small">CORPO DO TEXTO</label>
                                <textarea name="cert_corpo" class="form-control font-monospace text-secondary" rows="6">{{ $evento->cert_corpo ?? "Certificamos que <b>{PARTICIPANTE}</b>, inscrito(a) sob o CPF nº <b>{CPF}</b>, participou da atividade <b>{ATIVIDADE}</b>, integrante do evento <b>{EVENTO}</b>, realizado em {DATA_EVENTO}, atuando como <b>{TIPO_PARTICIPACAO}</b>, perfazendo uma carga horária total de <b>{CARGA_HORARIA} horas</b>." }}</textarea>
                                <div class="form-text small">Tags HTML permitidas: &lt;b&gt;, &lt;br&gt;, &lt;strong&gt;, &lt;em&gt;.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary small">RODAPÉ (Opcional)</label>
                                <textarea name="cert_rodape" class="form-control" rows="2" placeholder="Ex: Portaria nº 123/2025">{{ $evento->cert_rodape }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="cert-ass">
                        <div class="glass-card" x-data="signatureManager()">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h6 class="text-uppercase text-secondary fw-bold m-0 small">Assinaturas do Certificado</h6>
                                    <small class="text-secondary">Adicione quantas assinaturas forem necessárias.</small>
                                </div>
                                <button type="button" @click="addSignature()" class="btn btn-dark btn-sm fw-bold">
                                    <i class="fa-solid fa-plus me-1"></i> Adicionar Assinatura
                                </button>
                            </div>

                            <input type="hidden" name="assinaturas_para_remover" :value="removedIds.join(',')">

                            <div class="row g-3">
                                <template x-for="(sig, index) in signatures" :key="sig.tempId">
                                    <div class="col-md-6">
                                        <div class="card h-100 border shadow-sm">
                                            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                                                <span class="fw-bold small text-secondary">Assinatura #<span x-text="index + 1"></span></span>
                                                <button type="button" @click="removeSignature(index)" class="btn btn-sm text-danger btn-link text-decoration-none" title="Remover">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                            <div class="card-body bg-light p-3">

                                                <input type="hidden" :name="'assinaturas['+index+'][id]'" :value="sig.id">

                                                <div class="mb-2">
                                                    <label class="small fw-bold text-muted mb-1">Nome</label>
                                                    <input type="text" :name="'assinaturas['+index+'][nome]'" x-model="sig.nome" class="form-control form-control-sm @error('assinaturas.*.nome') is-invalid @enderror" placeholder="Ex: Prof. João" required>
                                                    @error('assinaturas.*.nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>

                                                <div class="mb-2">
                                                    <label class="small fw-bold text-muted mb-1">Cargo</label>
                                                    <input type="text" :name="'assinaturas['+index+'][cargo]'" x-model="sig.cargo" class="form-control form-control-sm @error('assinaturas.*.cargo') is-invalid @enderror" placeholder="Ex: Diretor" required>
                                                    @error('assinaturas.*.cargo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>

                                                <div class="mb-2">
                                                    <label class="small fw-bold text-muted mb-1">Arquivo Digitalizado (PNG)</label>
                                                    <input type="file" :name="'assinaturas['+index+'][arquivo]'" class="form-control form-control-sm @error('assinaturas.*.arquivo') is-invalid @enderror" accept="image/*">

                                                    @error('assinaturas.*.arquivo')
                                                        <div class="alert alert-danger py-1 mt-1 small border-0" style="font-size: 10px;">{{ $message }}</div>
                                                    @enderror

                                                    <template x-if="sig.arquivo_atual">
                                                        <div class="mt-2 p-2 border rounded bg-white text-center">
                                                            <small class="d-block text-muted mb-1" style="font-size: 10px;">IMAGEM ATUAL</small>
                                                            <img :src="'/storage/' + sig.arquivo_atual" style="height: 40px; width: auto; object-fit: contain;">
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div x-show="signatures.length === 0" class="text-center py-5 text-muted border border-dashed rounded bg-light mt-2">
                                <i class="fa-solid fa-signature fs-2 mb-2 opacity-25"></i>
                                <p class="m-0">Nenhuma assinatura cadastrada.</p>
                                <small>O certificado será gerado sem assinaturas.</small>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="d-flex justify-content-end pt-4 border-top mt-4 mb-5">
                    <a href="{{ route('admin.eventos.edit', $evento->id) }}" class="btn btn-light me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary-custom px-5 py-3 shadow fw-bold">
                        <i class="fa-solid fa-save me-2"></i> Salvar Configurações
                    </button>
                </div>

            </form>
        </div>
    </div>

    @php
        // Transforma o relacionamento Eloquent em JSON para o Alpine.js
        // Assume-se que a variável $dadosAssinaturas está sendo passada para a view (ou calculada)
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
        // Lógica AlpineJS para Assinaturas
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
