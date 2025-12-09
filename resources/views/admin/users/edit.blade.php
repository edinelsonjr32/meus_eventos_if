<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <h3 class="fw-bold m-0 mb-4">Editar Usuário: {{ $user->name }}</h3>

            <div class="glass-card p-4">
                <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Nome Completo</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">E-mail</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <hr class="my-4">

                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary mb-3">Nível de Acesso</h6>
                        <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="padrao" {{ old('role', $user->role) == 'padrao' ? 'selected' : '' }}>Padrão (Usuário Comum)</option>
                            <option value="admin" {{ old('role', $user->role) == 'admin' ? 'selected' : '' }}>Administrador</option>
                        </select>
                        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <p class="text-muted small mt-1">Defina o nível de permissão do usuário.</p>
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-bold text-secondary mb-3">Alterar Senha (Opcional)</h6>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Nova Senha</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Deixe vazio para manter a senha atual">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Confirmar Nova Senha</label>
                        <input type="password" name="password_confirmation" class="form-control">
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary-custom fw-bold">
                            <i class="fa-solid fa-save me-1"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
