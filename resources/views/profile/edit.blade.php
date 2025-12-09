<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-800 leading-tight">
            Minha Conta
        </h2>
    </x-slot>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="glass-card p-6 flex items-center gap-6 bg-white border border-gray-100">
                <div class="h-24 w-24 rounded-full bg-emerald-600 flex items-center justify-center text-white text-4xl font-bold shadow-lg">
                    {{ substr($user->name, 0, 1) }}
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">{{ $user->name }}</h3>
                    <p class="text-gray-500">{{ $user->email }}</p>

                    <div class="mt-2 flex gap-2">
                        <span class="inline-block px-3 py-1 bg-emerald-50 text-emerald-700 text-xs font-bold uppercase rounded-full border border-emerald-100">
                            {{ ucfirst($user->role === 'admin' ? 'Administrador' : 'Participante') }}
                        </span>
                        @if($user->participante)
                            <span class="inline-block px-3 py-1 bg-blue-50 text-blue-700 text-xs font-bold uppercase rounded-full border border-blue-100">
                                CPF Vinculado
                            </span>
                        @else
                            <span class="inline-block px-3 py-1 bg-red-50 text-red-700 text-xs font-bold uppercase rounded-full border border-red-100">
                                Cadastro Incompleto
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div class="space-y-6">
                    <div class="glass-card p-6 bg-white">
                        <h4 class="text-lg font-bold text-gray-800 mb-1">Informações Pessoais</h4>
                        <p class="text-sm text-gray-500 mb-6">Mantenha seus dados atualizados para emissão correta de certificados.</p>

                        <form method="post" action="{{ route('profile.update') }}" class="space-y-4">
                            @csrf
                            @method('patch')

                            <div>
                                <label class="form-label small fw-bold text-secondary text-uppercase">Nome Completo</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required autofocus>
                                @error('name') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="form-label small fw-bold text-secondary text-uppercase">CPF (Obrigatório)</label>
                                <input type="text" name="cpf" class="form-control cpf-mask"
                                       value="{{ old('cpf', $user->participante?->cpf) }}"
                                       placeholder="000.000.000-00" required>
                                <div class="form-text small">Usado para validar sua presença e emitir certificados.</div>
                                @error('cpf') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="form-label small fw-bold text-secondary text-uppercase">Email</label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                                @error('email') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="flex items-center gap-4 pt-2">
                                <button type="submit" class="btn btn-dark px-4 font-bold">Salvar Alterações</button>
                                @if (session('status') === 'profile-updated')
                                    <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-emerald-600 font-bold">
                                        <i class="fa-solid fa-check me-1"></i> Salvo!
                                    </p>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>

                <div class="space-y-6">

                    <div class="glass-card p-6 bg-white">
                        <h4 class="text-lg font-bold text-gray-800 mb-1">Segurança</h4>
                        <p class="text-sm text-gray-500 mb-6">Atualize sua senha periodicamente.</p>

                        <form method="post" action="{{ route('password.update') }}" class="space-y-4">
                            @csrf
                            @method('put')

                            <div>
                                <label class="form-label small fw-bold text-secondary text-uppercase">Senha Atual</label>
                                <input type="password" name="current_password" class="form-control">
                                @error('current_password') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="form-label small fw-bold text-secondary text-uppercase">Nova Senha</label>
                                <input type="password" name="password" class="form-control">
                                @error('password') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="form-label small fw-bold text-secondary text-uppercase">Confirmar Nova Senha</label>
                                <input type="password" name="password_confirmation" class="form-control">
                            </div>

                            <div class="flex items-center gap-4 pt-2">
                                <button type="submit" class="btn btn-outline-dark px-4 font-bold">Atualizar Senha</button>
                                @if (session('status') === 'password-updated')
                                    <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-emerald-600 font-bold">
                                        <i class="fa-solid fa-check me-1"></i> Atualizado!
                                    </p>
                                @endif
                            </div>
                        </form>
                    </div>

                    <div class="glass-card p-6 bg-red-50 border border-red-100">
                        <h4 class="text-lg font-bold text-red-700 mb-1">Excluir Conta</h4>
                        <p class="text-sm text-red-600/70 mb-4">Esta ação é irreversível. Todos os seus dados e certificados serão apagados.</p>

                        <button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')" class="btn btn-danger w-100 font-bold">
                            <i class="fa-solid fa-trash me-2"></i> Encerrar Minha Conta
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div x-data="{ show: false }"
         x-on:open-modal.window="if ($event.detail === 'confirm-user-deletion') show = true"
         x-show="show"
         style="display: none;"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center"
         aria-modal="true" role="dialog">

        <div class="absolute inset-0 bg-gray-900 bg-opacity-75 transition-opacity" @click="show = false"></div>

        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 relative z-10 transform transition-all">
            <h2 class="text-xl font-bold text-gray-900 mb-2">Tem certeza absoluta?</h2>
            <p class="text-gray-500 text-sm mb-6">
                Digite sua senha para confirmar a exclusão definitiva da sua conta.
            </p>

            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <div class="mb-4">
                    <input type="password" name="password" class="form-control" placeholder="Sua senha atual" required>
                    @error('password', 'userDeletion') <span class="text-danger small d-block mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" @click="show = false" class="btn btn-light">Cancelar</button>
                    <button type="submit" class="btn btn-danger font-bold">Sim, Excluir</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function(){
            $('.cpf-mask').mask('000.000.000-00');
        });
    </script>

</x-app-layout>
