<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <h3 class="fw-bold mb-1">Meus Trabalhos</h3>
            <p class="text-secondary mb-4">Acompanhe as submissões em que você é o responsável pelo envio.</p>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

            @if($trabalhos->isEmpty())
                <div class="glass-card bg-white text-center py-5">
                    <i class="fa-regular fa-file-lines fs-1 text-secondary opacity-25 mb-3"></i>
                    <p class="text-muted m-0">Você ainda não submeteu trabalhos. Abra a página de um evento com chamada aberta para começar.</p>
                </div>
            @else
                <div class="glass-card bg-white p-0 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr><th class="ps-4">Trabalho</th><th>Evento</th><th>Status</th><th class="text-end pe-4">Ações</th></tr>
                            </thead>
                            <tbody>
                                @foreach($trabalhos as $t)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold">{{ $t->titulo }}</div>
                                            <small class="text-muted">{{ $t->codigo ?? 'Sem protocolo (rascunho)' }} · {{ $t->autores->count() }} autor(es)</small>
                                        </td>
                                        <td>{{ $t->evento->titulo }}</td>
                                        <td><span class="badge bg-secondary bg-opacity-10 text-dark">{{ $t->statusRotulo() }}</span></td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('trabalhos.show', $t) }}" class="btn btn-sm btn-light">Ver</a>
                                            @can('update', $t)
                                                <a href="{{ route('trabalhos.edit', $t) }}" class="btn btn-sm btn-outline-success">Editar</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
