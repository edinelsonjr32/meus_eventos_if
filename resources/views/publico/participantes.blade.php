<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Lista de Presença: {{ $atividade->titulo }}
            </h2>
            <a href="{{ route('admin.eventos.show', $atividade->evento_id) }}" class="text-sm text-gray-500 hover:text-gray-700 underline">
                &larr; Voltar ao Evento
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="{ showModal: false, search: '' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="flex flex-col md:flex-row justify-between gap-4">
                <input x-model="search" type="text" placeholder="Buscar por nome ou CPF..." class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-full md:w-1/3">

                <button @click="showModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded shadow">
                    + Credenciamento Manual
                </button>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Participante</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vínculo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registro</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($frequencias as $freq)
                                <tr x-show="$el.innerText.toLowerCase().includes(search.toLowerCase())">
                                    <td class="px-6 py-4">
                                        <div class="font-bold">{{ $freq->participante->nome_completo }}</div>
                                        <div class="text-xs text-gray-500">{{ $freq->participante->cpf }}</div>
                                        <div class="text-xs text-indigo-600">{{ $freq->participante->email }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            {{ $freq->participante->tipo_vinculo === 'aluno' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ ucfirst($freq->participante->tipo_vinculo) }}
                                        </span>
                                        @if($freq->participante->tipo_vinculo === 'aluno' && $freq->participante->turma)
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ $freq->participante->turma->nome_completo }}
                                            </div>
                                        @endif
                                        @if($freq->participante->matricula)
                                            <div class="text-xs text-gray-400">Mat: {{ $freq->participante->matricula }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $freq->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <form action="{{ route('admin.frequencias.destroy', $freq->id) }}" method="POST" onsubmit="return confirm('Remover presença deste aluno?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if($frequencias->isEmpty())
                            <div class="text-center py-8 text-gray-500">Nenhum registro de presença ainda.</div>
                        @endif
                    </div>

                    <div class="mt-4 text-sm text-gray-500">
                        Total: {{ $frequencias->count() }} presentes.
                    </div>

                </div>
            </div>
        </div>

        <div x-show="showModal" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">

                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showModal = false"></div>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                    <form action="{{ route('admin.atividades.participantes.store', $atividade->id) }}" method="POST">
                        @csrf
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Credenciamento Manual</h3>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">CPF</label>
                                    <input type="text" name="cpf" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required placeholder="000.000.000-00">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nome Completo</label>
                                    <input type="text" name="nome_completo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" name="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                                </div>

                                <div x-data="{ tipo: 'aluno' }">
                                    <label class="block text-sm font-medium text-gray-700">Vínculo</label>
                                    <select name="tipo_vinculo" x-model="tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                        <option value="aluno">Aluno</option>
                                        <option value="servidor">Servidor</option>
                                        <option value="externo">Externo</option>
                                    </select>

                                    <div x-show="['aluno', 'servidor'].includes(tipo)" class="mt-3">
                                        <label class="block text-sm font-medium text-gray-700">Matrícula</label>
                                        <input type="text" name="matricula" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    </div>
                                    <div x-show="tipo === 'aluno'" class="mt-3">
                                        <label class="block text-sm font-medium text-gray-700">Turma</label>
                                        <select name="turma_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                            <option value="">Selecione...</option>
                                            @foreach($turmas as $turma)
                                                <option value="{{ $turma->id }}">{{ $turma->nome_completo }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">
                                Confirmar Presença
                            </button>
                            <button type="button" @click="showModal = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </div>
</x-app-layout>
