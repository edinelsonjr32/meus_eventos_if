<x-app-layout>
    <div class="row justify-content-center" x-data="{ selectedUsers: [], search: '{{ request('search') }}' }">
        <div class="col-lg-11">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold m-0">
                    <i class="fa-solid fa-users-gear me-2 text-primary-custom"></i>
                    Gestão de Usuários
                </h3>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-danger fw-bold"
                            :disabled="selectedUsers.length === 0"
                            @click="if(confirm(`Excluir ${selectedUsers.length} usuários selecionados?`)) { console.log('Implementar exclusão em massa aqui') }">
                        <i class="fa-solid fa-trash-alt me-1"></i> Excluir (<span x-text="selectedUsers.length">0</span>)
                    </button>
                    <a href="{{ route('admin.users.create') }}" class="btn btn-primary-custom fw-bold shadow-sm">
                        <i class="fa-solid fa-user-plus me-1"></i> Adicionar Usuário
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success small p-2 mb-3">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger small p-2 mb-3">{{ session('error') }}</div>
            @endif

            <div class="row g-4 mb-4">
                <div class="col-md-9">
                    <form action="{{ route('admin.users.index') }}" method="GET" class="d-flex">
                        <input type="text" name="search" x-model="search" class="form-control form-control-lg me-2" placeholder="Buscar por nome ou e-mail...">
                        <button type="submit" class="btn btn-secondary-custom px-4">
                            <i class="fa-solid fa-search"></i>
                        </button>
                    </form>
                </div>

                <div class="col-md-3">
                    <div class="card p-3 shadow-sm border-left-primary bg-white h-100">
                        <small class="text-primary-custom fw-bold mb-1">TOTAL DE USUÁRIOS</small>
                        <h4 class="fw-bold m-0">{{ $users->count() }}</h4>
                        <small class="text-muted">Admins: {{ $users->where('role', 'admin')->count() }}</small>
                    </div>
                </div>
            </div>

            <div class="glass-card p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3" style="width: 5%;">
                                    <input type="checkbox" @click="selectedUsers = selectedUsers.length === {{ $users->count() }} ? [] : {{ $users->pluck('id') }}">
                                </th>
                                <th style="width: 5%;">ID</th>
                                <th style="width: 35%;">Usuário / Contato</th>
                                <th style="width: 20%;">Acesso Rápido</th>
                                <th style="width: 15%;">Criado Em</th>
                                <th class="text-end pe-4" style="width: 20%;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                                <tr>
                                    <td class="ps-4">
                                        <input type="checkbox" :value="{{ $user->id }}" x-model="selectedUsers">
                                    </td>
                                    <td>
                                        <small class="text-muted fw-bold">#{{ $user->id }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $user->name }}</div>
                                        <div class="small text-muted">{{ $user->email }}</div>
                                        @if(auth()->id() == $user->id)
                                            <span class="badge bg-info text-white small mt-1">Você</span>
                                        @endif
                                    </td>

                                    <td>
                                        <div x-data="{ isAdmin: {{ $user->role === 'admin' ? 'true' : 'false' }} }">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" :id="'adminSwitch{{ $user->id }}'"
                                                       :checked="isAdmin" @change="console.log('Implementar rota AJAX para toggle de Admin para o usuário: {{ $user->id }}')">
                                                <label class="form-check-label" :for="'adminSwitch{{ $user->id }}'">
                                                    <span :class="isAdmin ? 'text-danger fw-bold' : 'text-secondary'">
                                                        <i class="fa-solid fa-user-shield me-1"></i>
                                                        <span x-text="isAdmin ? 'ADMIN' : 'PADRÃO'"></span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <small class="text-muted">{{ $user->created_at->format('d/m/Y') }}</small>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">

                                            <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-sm btn-outline-secondary" title="Editar">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>

                                            @if(auth()->id() != $user->id)
                                                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" onsubmit="return confirm('Tem certeza que deseja remover este usuário? Esta ação é irreversível.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover Usuário">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <button class="btn btn-sm btn-outline-secondary disabled" title="Não é possível auto-excluir">
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
