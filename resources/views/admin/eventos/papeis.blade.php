<x-app-layout>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <div class="d-flex align-items-center gap-3 mb-4">
                <a href="{{ route('admin.eventos.show', $evento->id) }}" class="btn btn-light rounded-circle shadow-sm" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <h3 class="fw-bold m-0">Papéis do Evento</h3>
                    <small class="text-secondary">{{ $evento->titulo }}</small>
                </div>
            </div>

            @if(session('error'))   <div class="alert alert-danger small p-2 mb-3">{{ session('error') }}</div> @endif
            @if(session('warning')) <div class="alert alert-warning small p-2 mb-3">{{ session('warning') }}</div> @endif
            @if(session('success')) <div class="alert alert-success small p-2 mb-3">{{ session('success') }}</div> @endif
            @if($errors->any())
                <div class="alert alert-danger small p-2 mb-3">{{ $errors->first() }}</div>
            @endif

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="glass-card bg-white position-sticky" style="top: 20px;">
                        <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-user-shield me-2"></i> Designar Usuário</h6>

                        <form action="{{ route('admin.eventos.papeis.store', $evento->id) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary">Pessoa (nome ou e-mail)</label>
                                <x-select-pessoa :evento="$evento" tipo="usuario" name="email" :selecionado="old('email')" placeholder="Digite o nome ou e-mail…" />
                                <div class="form-text small">A pessoa precisa ter uma conta na plataforma.</div>
                            </div>

                            <div class="mb-4">
                                <label class="small fw-bold text-secondary">Papel</label>
                                <select name="papel" class="form-select" required>
                                    @foreach($papeis as $valor => $rotulo)
                                        @if($valor !== 'editor' || $ehDono)
                                            <option value="{{ $valor }}" @selected(old('papel') === $valor)>{{ $rotulo }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary-custom w-100 shadow-sm fw-bold">
                                <i class="fa-solid fa-plus me-1"></i> Designar
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="glass-card p-0 overflow-hidden">
                        <div class="p-4 border-bottom bg-light d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold m-0 text-dark">Designações</h6>
                            <span class="badge bg-secondary">{{ $designacoes->count() }}</span>
                        </div>

                        @if($designacoes->isEmpty())
                            <div class="text-center py-5">
                                <p class="text-muted small m-0">Nenhum papel designado ainda.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-white">
                                        <tr><th class="ps-4">Usuário</th><th>Papel</th><th class="text-end pe-4">Ação</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($designacoes as $d)
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="fw-bold">{{ $d->name }}</div>
                                                    <small class="text-muted">{{ $d->email }}</small>
                                                </td>
                                                <td><span class="badge bg-success bg-opacity-10 text-success">{{ $papeis[$d->papel] ?? $d->papel }}</span></td>
                                                <td class="text-end pe-4">
                                                    @if($d->papel !== 'editor' || $ehDono)
                                                        <form action="{{ route('admin.eventos.papeis.destroy', [$evento->id, $d->user_id, $d->papel]) }}" method="POST" onsubmit="return confirm('Remover este papel?')">
                                                            @csrf @method('DELETE')
                                                            <button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
