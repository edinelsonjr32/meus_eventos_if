<x-app-layout>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <div class="row justify-content-center"
         x-data="kioskApp()">
        <div class="col-lg-10">

            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('admin.eventos.show', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div>
                        <h3 class="fw-bold m-0">Recepção & Credenciamento</h3>
                        <small class="text-secondary">{{ $evento->titulo }}</small>
                    </div>
                </div>

                <div class="d-flex gap-3 text-end">
                    <div class="bg-white px-3 py-2 rounded shadow-sm border">
                        <small class="text-muted d-block" style="font-size: 10px;">CREDENCIADOS</small>
                        <span class="fw-bold text-success fs-5">
                            <span x-text="stats.credenciados">{{ $totalCredenciados }}</span> / {{ $totalInscritos }}
                        </span>
                    </div>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success small p-2 mb-3">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger small p-2 mb-3">{{ session('error') }}</div>
            @endif

            <div class="glass-card p-4 mb-4 bg-primary text-white" style="background: linear-gradient(135deg, #064e3b 0%, #047857 100%);">
                <label class="fw-bold mb-2 text-white text-opacity-75">BUSCAR PARTICIPANTE</label>
                <div class="position-relative">
                    <i class="fa-solid fa-magnifying-glass position-absolute text-secondary" style="top: 18px; left: 20px; font-size: 1.2rem;"></i>
                    <input type="text" x-model="query" @input.debounce.300ms="search()"
                           class="form-control form-control-lg ps-5 border-0 shadow-lg py-3 fs-5"
                           placeholder="Digite o nome, CPF ou E-mail..." autofocus>
                </div>
            </div>

            <div class="glass-card p-0 overflow-hidden" x-show="results.length > 0 || query !== ''">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3">Participante</th>
                                <th>Vínculo</th>
                                <th>Curso / Turma</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="item in results" :key="item.participante.id">
                                <tr :class="{'bg-success bg-opacity-10': item.checkin_at}">
                                    <td class="ps-4 py-3">
                                        <div class="fw-bold text-dark" x-text="item.participante.nome_completo"></div>
                                        <div class="text-secondary small">
                                            <i class="fa-regular fa-id-card me-1"></i> <span x-text="item.participante.cpf"></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border fw-normal" x-text="item.participante.tipo_vinculo.toUpperCase()"></span>
                                    </td>

                                    <td class="small text-secondary">
                                        <template x-if="item.participante.turma && item.participante.turma.curso">
                                            <div class="text-dark fw-bold" x-text="item.participante.turma.curso.nome"></div>
                                            <div class="small" x-text="item.participante.turma.nome_completo"></div>
                                        </template>
                                        <template x-if="item.participante.tipo_vinculo === 'aluno' && !item.participante.turma">
                                            <span class="text-warning fst-italic">Aluno sem turma</span>
                                        </template>
                                        <template x-if="item.participante.tipo_vinculo !== 'aluno'">
                                            <span>---</span>
                                        </template>
                                    </td>

                                    <td>
                                        <template x-if="item.checkin_at">
                                            <span class="badge bg-success text-white shadow-sm">
                                                <i class="fa-solid fa-check me-1"></i> CHEGOU
                                            </span>
                                        </template>
                                        <template x-if="!item.checkin_at">
                                            <span class="badge bg-secondary bg-opacity-25 text-secondary">PENDENTE</span>
                                        </template>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button x-show="!item.checkin_at" @click="doCheckin(item)"
                                                    class="btn btn-success btn-sm fw-bold shadow-sm d-flex align-items-center gap-2">
                                                Check-in
                                            </button>
                                            <button @click="printBadge(item)"
                                                    class="btn btn-dark btn-sm fw-bold shadow-sm d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-print"></i> Etiqueta
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="results.length === 0 && query.length > 2">
                                <td colspan="5" class="text-center py-5">
                                    <div class="mb-3 opacity-50"><i class="fa-solid fa-user-plus fs-1"></i></div>
                                    <h5 class="fw-bold text-dark">Ninguém encontrado</h5>
                                    <p class="text-muted small">O participante não está pré-inscrito no evento.</p>

                                    <a href="{{ route('admin.eventos.credenciamento.create', $evento->id) }}" class="btn btn-primary-custom px-4 py-2 fw-bold shadow-sm">
                                        <i class="fa-solid fa-plus me-2"></i> Cadastrar Novo Participante
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="query === ''" class="text-center py-5">
                <i class="fa-solid fa-id-badge fs-1 text-secondary opacity-25 mb-3"></i>
                <h5 class="text-secondary fw-bold">Aguardando pesquisa...</h5>
                <p class="text-muted small">Comece a digitar para encontrar inscritos.</p>
            </div>


        </div>
    </div>

    <script>
        function kioskApp() {
            return {
                query: '',
                results: [],
                stats: { credenciados: {{ $totalCredenciados }} },

                init() {
                    // Inicializa o contador de credenciados
                },

                async search() {
                    if (this.query.length < 2) { this.results = []; return; }
                    try {
                        const res = await fetch(`{{ route('admin.eventos.credenciamento.search', $evento->id) }}?q=${this.query}`);
                        this.results = await res.json();
                    } catch (e) { console.error(e); }
                },

                async doCheckin(item) {
                    try {
                        const res = await fetch(`/admin/eventos/{{ $evento->id }}/credenciamento/checkin/${item.participante_id}`, {
                            method: 'POST',
                            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
                        });
                        const data = await res.json();
                        if (data.success) {
                            // Atualiza o item localmente
                            item.checkin_at = data.checkin_at;
                            this.stats.credenciados++;
                        }
                    } catch (e) { alert('Erro no check-in.'); }
                },

                printBadge(item) {
                    const pId = item.participante_id || item.participante.id;
                    const url = `/admin/eventos/{{ $evento->id }}/credenciamento/etiqueta/${pId}`;
                    const win = window.open(url, '_blank', 'width=400,height=600');
                    // Garante que o check-in é marcado antes de imprimir
                    if(!item.checkin_at) {
                        item.checkin_at = new Date();
                        this.stats.credenciados++;
                    }
                }
            }
        }
    </script>
</x-app-layout>
