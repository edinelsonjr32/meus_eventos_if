@php
    $gestao = $gestao ?? false;
@endphp
<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-xl-10">

            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ $gestao ? route('gestao.trabalhos.index', $trabalho->evento_id) : route('trabalhos.index') }}" class="btn btn-light rounded-circle shadow-sm" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div>
                        <h4 class="fw-bold m-0">{{ $trabalho->titulo }}</h4>
                        <small class="text-secondary">{{ $trabalho->evento->titulo }} · {{ $trabalho->codigo ?? 'Rascunho (sem protocolo)' }}</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($gestao && !in_array($trabalho->status, ['rascunho', 'retirado']))
                        <a href="{{ route('gestao.trabalhos.editar', $trabalho) }}" class="btn btn-sm btn-outline-secondary fw-bold">
                            <i class="fa-solid fa-pen me-1"></i> Editar dados
                        </a>
                    @endif
                    <x-badge tipo="trabalho" :valor="$trabalho->status" style="font-size:.85rem; padding:.35rem .8rem;" />
                </div>
            </div>

            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="glass-card bg-white mb-4">
                        <h6 class="fw-bold text-success mb-3">Dados do trabalho</h6>
                        <dl class="row small mb-0">
                            <dt class="col-sm-4 text-secondary">Categoria</dt>
                            <dd class="col-sm-8">{{ \App\Models\TrabalhoConfig::TIPOS[$trabalho->tipo_trabalho] ?? '—' }}</dd>
                            <dt class="col-sm-4 text-secondary">Área temática</dt>
                            <dd class="col-sm-8">{{ $trabalho->area->nome ?? '—' }}</dd>
                            <dt class="col-sm-4 text-secondary">Modalidade</dt>
                            <dd class="col-sm-8">{{ \App\Models\Trabalho::MODALIDADES[$trabalho->modalidade_apresentacao] ?? '—' }}</dd>
                            <dt class="col-sm-4 text-secondary">Palavras-chave</dt>
                            <dd class="col-sm-8">{{ implode(', ', $trabalho->palavras_chave ?? []) ?: '—' }}</dd>
                            <dt class="col-sm-4 text-secondary">Fomento / Projeto</dt>
                            <dd class="col-sm-8">{{ $trabalho->agencia_fomento ?: '—' }} / {{ $trabalho->projeto_pesquisa ?: '—' }}</dd>
                            <dt class="col-sm-4 text-secondary">Comitê de ética</dt>
                            <dd class="col-sm-8">{{ $trabalho->comite_etica ?: '—' }}</dd>
                            <dt class="col-sm-4 text-secondary">Publicação nos Anais</dt>
                            <dd class="col-sm-8">{{ $trabalho->autoriza_publicacao ? 'Autorizada' : 'Não autorizada' }}</dd>
                        </dl>
                        <hr>
                        <h6 class="fw-bold">Resumo</h6>
                        <p class="small" style="white-space: pre-line;">{{ $trabalho->resumo ?: '—' }}</p>
                        @if($trabalho->abstract)
                            <h6 class="fw-bold">Abstract</h6>
                            <p class="small" style="white-space: pre-line;">{{ $trabalho->abstract }}</p>
                        @endif
                    </div>

                    <div class="glass-card bg-white p-0 overflow-hidden">
                        <div class="p-3 border-bottom bg-light fw-bold">Autores</div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 small">
                                <thead><tr><th class="ps-3">Nome</th><th>Tipo</th><th>Instituição</th><th>Contato</th><th></th></tr></thead>
                                <tbody>
                                @foreach($trabalho->autores as $a)
                                    <tr>
                                        <td class="ps-3 fw-bold">{{ $a->nome_completo }}<br><span class="text-muted fw-normal">{{ $a->cpf ? ($gestao ? $a->cpf : 'CPF ***.' . substr($a->cpf, 4, 3) . '.***-**') : 'CPF não informado' }}</span></td>
                                        <td>{{ $a->tipoRotulo() }}@if($a->categoriaRotulo())<br><span class="text-muted">{{ $a->categoriaRotulo() }}</span>@endif</td>
                                        <td>{{ $a->instituicao ?: '—' }}<br><span class="text-muted">{{ $a->titulacao }}</span></td>
                                        <td>{{ $a->email ?: '—' }}<br>{{ $a->telefone }}</td>
                                        <td>
                                            @if($a->apresentador) <x-badge tipo="autor" valor="apresentador" /> @endif
                                            @if($a->correspondente) <x-badge tipo="autor" valor="correspondente" /> @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    @if($gestao)
                        @include('gestao.trabalhos._painel')
                    @endif

                    @unless($gestao)
                        @if($trabalho->temDecisao() || $trabalho->status === 'aguardando_correcao')
                            @php
                                $cfg = $trabalho->evento->trabalhoConfig;
                                $pareceres = $trabalho->avaliacoes->where('status', 'concluida')->values();
                                $rotuloDecisao = ['aprovado' => ['Aprovado', 'success'], 'aprovado_com_ressalvas' => ['Aprovado com ressalvas', 'warning'], 'reprovado' => ['Não aprovado', 'danger']][$trabalho->decisao] ?? null;
                            @endphp
                            <div class="glass-card bg-white mb-4">
                                <h6 class="fw-bold text-success mb-3">Resultado da avaliação</h6>
                                @if($rotuloDecisao)
                                    <x-badge tipo="trabalho" :valor="$trabalho->decisao" :rotulo="$rotuloDecisao[0]" class="mb-2" />
                                @endif
                                @if(($cfg->autor_ve_notas ?? true) && $trabalho->nota_final !== null)
                                    <div class="small mb-2">Nota final: <strong>{{ number_format($trabalho->nota_final, 2, ',', '.') }}</strong></div>
                                @endif
                                @if($trabalho->status === 'aguardando_correcao' && $trabalho->prazo_correcao)
                                    <div class="alert alert-warning small py-2">Envie a versão corrigida até <strong>{{ $trabalho->prazo_correcao->format('d/m/Y') }}</strong>.</div>
                                @endif
                                @if($trabalho->decisao_justificativa)
                                    <div class="small fw-bold">Mensagem da gestão</div>
                                    <p class="small" style="white-space: pre-line;">{{ $trabalho->decisao_justificativa }}</p>
                                @endif
                                @if(($cfg->autor_ve_pareceres ?? true) && $pareceres->isNotEmpty())
                                    @foreach($pareceres as $i => $p)
                                        <div class="small fw-bold mt-2">Parecer {{ $i + 1 }}</div>
                                        <p class="small mb-1" style="white-space: pre-line;">{{ $p->parecer }}</p>
                                    @endforeach
                                @endif
                            </div>
                        @endif
                    @endunless

                    @unless($gestao)
                        @php
                            $cfgAp = $trabalho->evento->trabalhoConfig;
                            $paresAp = $trabalho->avaliacoesApresentacao()->where('status', 'concluida')->get();
                        @endphp
                        @if($cfgAp?->avaliacao_apresentacao_ativa && ($cfgAp->autor_ve_nota_apresentacao ?? true) && $trabalho->nota_apresentacao !== null)
                            <div class="glass-card bg-white mb-4">
                                <h6 class="fw-bold text-success mb-3">Avaliação da apresentação</h6>
                                <div class="small mb-1">Nota da apresentação: <strong>{{ number_format($trabalho->nota_apresentacao, 2, ',', '.') }}</strong></div>
                                @if($trabalho->nota_final !== null)
                                    <div class="small mb-2">Nota geral (texto + apresentação): <strong>{{ number_format($trabalho->notaGeral(), 2, ',', '.') }}</strong></div>
                                @endif
                                @foreach($paresAp->filter(fn ($p) => filled($p->parecer)) as $i => $p)
                                    <div class="small fw-bold mt-2">Parecer {{ $i + 1 }}</div>
                                    <p class="small mb-1" style="white-space: pre-line;">{{ $p->parecer }}</p>
                                @endforeach
                            </div>
                        @endif
                    @endunless

                    @unless($gestao)
                        @php
                            $certificadosAutor = $trabalho->certificados->whereNull('revogado_em');
                            // O responsável pela submissão vê todos; os demais autores, apenas o próprio certificado
                            if ($trabalho->submetido_por !== auth()->id()) {
                                $certificadosAutor = $certificadosAutor->filter(fn ($c) => $c->autor->pertenceA(auth()->user()));
                            }
                        @endphp
                        @if($certificadosAutor->isNotEmpty())
                            <div class="glass-card bg-white mb-4">
                                <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-award me-1"></i> Certificados</h6>
                                @foreach($certificadosAutor as $c)
                                    <div class="d-flex justify-content-between align-items-center small mb-2 pb-2 border-bottom">
                                        <div>
                                            <div class="fw-bold">{{ $c->autor->nome_completo }}</div>
                                            <div class="text-muted">{{ $c->funcaoRotulo() }}</div>
                                        </div>
                                        <a href="{{ route('certificado.trabalho.download', $c->hash_validacao) }}" target="_blank" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-download me-1"></i> PDF</a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endunless

                    <div class="glass-card bg-white mb-4">
                        <h6 class="fw-bold text-success mb-3">Arquivo</h6>
                        @if($trabalho->arquivoAtual)
                            <p class="small mb-2"><i class="fa-solid fa-file-pdf text-danger me-1"></i>
                                {{ $trabalho->arquivoAtual->nome_original }}<br>
                                <span class="text-muted">v{{ $trabalho->arquivoAtual->versao }} · {{ number_format($trabalho->arquivoAtual->tamanho / 1048576, 2, ',', '.') }} MB</span>
                            </p>
                            <a href="{{ route('trabalhos.arquivo', $trabalho) }}" target="_blank" class="btn btn-sm btn-outline-success w-100">
                                <i class="fa-solid fa-eye me-1"></i> Abrir PDF
                            </a>
                        @else
                            <p class="small text-muted m-0">Nenhum arquivo enviado.</p>
                        @endif

                        @unless($gestao)
                            @can('update', $trabalho)
                                <a href="{{ route('trabalhos.edit', $trabalho) }}" class="btn btn-sm btn-primary-custom w-100 mt-3">
                                    <i class="fa-solid fa-pen me-1"></i> Editar / continuar
                                </a>
                            @endcan
                            @can('retirar', $trabalho)
                                <form action="{{ route('trabalhos.retirar', $trabalho) }}" method="POST" class="mt-2" onsubmit="return confirm('Retirar este trabalho? Essa ação não pode ser desfeita.')">
                                    @csrf
                                    <button class="btn btn-sm btn-light text-danger w-100">Retirar trabalho</button>
                                </form>
                            @endcan
                        @endunless
                    </div>

                    <div class="glass-card bg-white">
                        <h6 class="fw-bold text-success mb-3">Histórico</h6>
                        {{-- Para o autor: só mudanças de status (registros internos, como atribuição de avaliador, não aparecem) e sem nomes --}}
                        @forelse($trabalho->historico->filter(fn ($h) => $gestao || $h->de !== $h->para) as $h)
                            <div class="small mb-2 pb-2 border-bottom">
                                <strong>{{ \App\Models\Trabalho::STATUS[$h->para] ?? $h->para }}</strong>
                                <div class="text-muted">{{ $h->created_at->format('d/m/Y H:i') }}{{ $gestao && $h->usuario ? ' · ' . $h->usuario->name : '' }}</div>
                                @if($h->justificativa) <div>{{ $h->justificativa }}</div> @endif
                            </div>
                        @empty
                            <p class="small text-muted m-0">Sem registros.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
