<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <h3 class="fw-bold m-0 mb-4">Cadastrar Novo Usuário</h3>

            <div class="glass-card p-4">
                <form action="{{ route('admin.users.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Nome Completo</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">E-mail</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-bold text-secondary mb-3">Acesso e Senha</h6>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Nível de Acesso</label>
                        <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="padrao" {{ old('role') == 'padrao' ? 'selected' : '' }}>Padrão (Usuário Comum)</option>
                            <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Administrador</option>
                        </select>
                        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Senha</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Confirmar Senha</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary-custom fw-bold">
                            <i class="fa-solid fa-save me-1"></i> Criar Usuário
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
