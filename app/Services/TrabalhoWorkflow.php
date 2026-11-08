<?php

namespace App\Services;

use App\Http\Requests\SalvarTrabalhoRequest;
use App\Mail\AvaliadorAtribuidoMail;
use App\Mail\TrabalhoReenviadoMail;
use App\Mail\TrabalhoStatusMail;
use App\Mail\TrabalhoSubmetidoMail;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Trabalho;
use App\Models\TrabalhoArquivo;
use App\Models\TrabalhoAvaliacao;
use App\Models\TrabalhoConfig;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Regras de persistência e de mudança de status dos trabalhos.
 * Toda transição grava histórico.
 */
class TrabalhoWorkflow
{
    /** Transições da triagem feitas pela gestão: de => [para => exige justificativa]. */
    public const TRANSICOES_GESTAO = [
        Trabalho::SUBMETIDO => ['em_triagem' => false],
        'resubmetido' => ['em_triagem' => false],
        'em_triagem' => [
            Trabalho::DEVOLVIDO_TRIAGEM => true,
            'rejeitado_triagem' => true,
            'em_avaliacao' => false,
        ],
        // Cancelar o envio (ex.: avaliador atribuído errado): volta à triagem, desde que ninguém tenha concluído
        'em_avaliacao' => ['em_triagem' => false],
    ];

    /** Status em que ainda é possível atribuir ou remover avaliadores. */
    public const STATUS_ATRIBUICAO = ['em_triagem', 'em_avaliacao', 'avaliado'];

    /** Cria ou atualiza o trabalho (rascunho ou reenvio), seus autores e o PDF. */
    public function salvar(Evento $evento, User $user, array $dados, ?Trabalho $trabalho = null, ?UploadedFile $arquivo = null): Trabalho
    {
        return DB::transaction(function () use ($evento, $user, $dados, $trabalho, $arquivo) {
            $campos = [
                'titulo' => $dados['titulo'],
                'resumo' => $dados['resumo'] ?? null,
                'abstract' => $dados['abstract'] ?? null,
                'palavras_chave' => SalvarTrabalhoRequest::palavras($dados['palavras_chave'] ?? null),
                'tipo_trabalho' => $dados['tipo_trabalho'] ?? null,
                'area_tematica_id' => $dados['area_tematica_id'] ?? null,
                'modalidade_apresentacao' => $dados['modalidade_apresentacao'] ?? null,
                'agencia_fomento' => $dados['agencia_fomento'] ?? null,
                'projeto_pesquisa' => $dados['projeto_pesquisa'] ?? null,
                'comite_etica' => $dados['comite_etica'] ?? null,
                'autoriza_publicacao' => (bool) ($dados['autoriza_publicacao'] ?? false),
            ];
            $campos['autorizou_em'] = $campos['autoriza_publicacao'] ? ($trabalho?->autorizou_em ?? now()) : null;

            if ($trabalho) {
                $trabalho->update($campos);
            } else {
                $trabalho = Trabalho::create($campos + [
                    'evento_id' => $evento->id,
                    'submetido_por' => $user->id,
                    'status' => Trabalho::RASCUNHO,
                ]);
                $this->registrar($trabalho, $user, null, Trabalho::RASCUNHO, 'Rascunho criado');
            }

            $this->sincronizarAutores($trabalho, $dados['autores'] ?? []);

            if ($arquivo) {
                $this->anexarArquivo($trabalho, $user, $arquivo);
            }

            return $trabalho->fresh(['autores', 'arquivoAtual']);
        });
    }

    /** Valida completude e conclui o envio: gera protocolo, muda status e avisa os autores. */
    public function submeter(Trabalho $trabalho, User $user): Trabalho
    {
        $deStatus = $trabalho->status;   // status antes do envio (para saber se é um reenvio)
        $reenvio = $trabalho->status === Trabalho::AGUARDANDO_CORRECAO;

        DB::transaction(function () use ($trabalho, $user, $reenvio) {
            $de = $trabalho->status;
            $trabalho->refresh();

            if (!$trabalho->codigo) {
                $trabalho->codigo = $this->gerarCodigo($trabalho->evento);
            }

            $trabalho->status = $reenvio ? 'resubmetido' : Trabalho::SUBMETIDO;
            $trabalho->submetido_em = now();
            $trabalho->save();

            $this->registrar($trabalho, $user, $de, $trabalho->status, $reenvio ? 'Versão corrigida enviada' : 'Trabalho submetido');
        });

        $trabalho->load(['autores', 'evento']);

        // Reenvio após devolução ou pedido de correção: avisa a gestão (antes só aparecia na fila)
        if (in_array($deStatus, [Trabalho::AGUARDANDO_CORRECAO, Trabalho::DEVOLVIDO_TRIAGEM], true)) {
            foreach ($trabalho->evento->usuariosDaGestaoDeTrabalhos() as $gestor) {
                Mail::to($gestor->email)->queue(new TrabalhoReenviadoMail($trabalho, $deStatus));
            }
        }

        $destinatarios = $trabalho->autores->pluck('email')->filter()->push($user->email)->map(fn ($e) => mb_strtolower($e))->unique();
        foreach ($destinatarios as $email) {
            Mail::to($email)->queue(new TrabalhoSubmetidoMail($trabalho));
        }

        return $trabalho;
    }

    public function retirar(Trabalho $trabalho, User $user, ?string $motivo = null): void
    {
        DB::transaction(function () use ($trabalho, $user, $motivo) {
            $de = $trabalho->status;
            $trabalho->update(['status' => Trabalho::RETIRADO]);
            $this->registrar($trabalho, $user, $de, Trabalho::RETIRADO, $motivo ?: 'Retirado pelo autor');
        });
    }

    public function registrar(Trabalho $trabalho, ?User $user, ?string $de, string $para, ?string $justificativa = null): void
    {
        $trabalho->historico()->create([
            'usuario_id' => $user?->id, 'de' => $de, 'para' => $para, 'justificativa' => $justificativa, 'created_at' => now(),
        ]);
    }

    /** Protocolo único por evento, com contador travado para evitar duplicidade. */
    private function gerarCodigo(Evento $evento): string
    {
        $config = TrabalhoConfig::where('evento_id', $evento->id)->lockForUpdate()->first();
        if (!$config) {
            $config = TrabalhoConfig::create(['evento_id' => $evento->id]);
            $config = TrabalhoConfig::where('id', $config->id)->lockForUpdate()->first();
        }

        $numero = $config->proximo_numero;
        $config->forceFill(['proximo_numero' => $numero + 1])->save();

        return sprintf('TRB-%d-%04d', $evento->id, $numero);
    }

    /**
     * Para cada autor enviado no formulário, o "id" do autor existente que ele representa (ou null = novo).
     * Casa pelo "id" do formulário; sem id (formulário antigo), pelo CPF.
     *
     * @return array<int, int|null>
     */
    private function casarAutores(Collection $existentes, array $autores): array
    {
        $usados = [];
        $ids = [];

        foreach (array_values($autores) as $i => $a) {
            $id = (int) ($a['id'] ?? 0);
            if (!$id && !empty($a['cpf'])) {
                $id = (int) $existentes->first(fn ($e) => $e->cpf === $a['cpf'] && !in_array($e->id, $usados, true))?->id;
            }

            if ($id && $existentes->has($id) && !in_array($id, $usados, true)) {
                $usados[] = $id;
                $ids[$i] = $id;
            } else {
                $ids[$i] = null;
            }
        }

        return $ids;
    }

    /**
     * Autores com certificado válido que SERIAM removidos por esta edição (a gestão precisa revogar antes).
     *
     * @return Collection<int, TrabalhoAutor>
     */
    public function autoresComCertificadoRemovidos(Trabalho $trabalho, array $autores): Collection
    {
        $existentes = $trabalho->autores()->with('certificado')->get()->keyBy('id');
        $mantidos = array_filter($this->casarAutores($existentes, $autores));

        return $existentes->except($mantidos)->filter(fn ($a) => $a->certificado && !$a->certificado->revogado())->values();
    }

    /**
     * Sincroniza os autores PRESERVANDO os já existentes: editar um nome não recria o autor,
     * então certificados já emitidos continuam válidos. Quem não veio mais no formulário é removido.
     */
    private function sincronizarAutores(Trabalho $trabalho, array $autores): void
    {
        $existentes = $trabalho->autores()->get()->keyBy('id');
        $casados = $this->casarAutores($existentes, $autores);
        $mantidos = [];

        $principal = collect($autores)->contains(fn ($a) => !empty($a['correspondente']));

        foreach (array_values($autores) as $i => $a) {
            $atual = $casados[$i] ? $existentes[$casados[$i]] : null;
            $preservar = fn (string $k) => array_key_exists($k, $a) ? ($a[$k] !== '' ? $a[$k] : null) : $atual?->{$k};

            // Apenas vincula (nunca altera) um participante já existente com o mesmo CPF (quando informado)
            $cpf = $preservar('cpf');
            $participante = !empty($cpf) ? Participante::where('cpf', $cpf)->first() : null;

            // Campos que o formulário do autor NÃO envia (CPF dos demais autores, telefone, vínculo, titulação, Lattes, ORCID)
            // são preservados: editar o trabalho nunca apaga o que veio de outro lugar. Se vierem (mesmo vazios), valem.
            $campos = [
                'ordem' => $i + 1,
                'participante_id' => $participante?->id,
                'nome_completo' => $a['nome_completo'],
                'cpf' => $cpf,
                'email' => $preservar('email'),
                'telefone' => $preservar('telefone'),
                'instituicao' => $a['instituicao'] ?? null,
                'vinculo' => $preservar('vinculo'),
                'titulacao' => $preservar('titulacao'),
                'lattes' => $preservar('lattes'),
                'orcid' => $preservar('orcid'),
                'tipo_autor' => $a['tipo_autor'],
                'categoria_autor' => $a['categoria_autor'] ?? null,
                'apresentador' => (bool) ($a['apresentador'] ?? false),
                // Sem correspondente definido, o autor principal assume
                'correspondente' => $principal ? (bool) ($a['correspondente'] ?? false) : $a['tipo_autor'] === 'autor_principal',
            ];

            if ($casados[$i]) {
                $existentes[$casados[$i]]->update($campos);
                $mantidos[] = $casados[$i];
            } else {
                $mantidos[] = $trabalho->autores()->create($campos)->id;
            }
        }

        $trabalho->autores()->whereNotIn('id', $mantidos)->delete();
    }

    /**
     * PDF final dos Anais (preparado pela gestão). Não substitui o arquivo em avaliação: fica inativo
     * e é usado apenas na publicação.
     */
    public function anexarArquivoFinal(Trabalho $trabalho, User $gestor, UploadedFile $arquivo): TrabalhoArquivo
    {
        $versao = ((int) $trabalho->arquivos()->max('versao')) + 1;
        $path = $arquivo->storeAs("{$trabalho->evento_id}/{$trabalho->id}", sprintf('anais-v%d-%s.pdf', $versao, Str::random(24)), 'trabalhos');

        if ($path === false) {
            throw ValidationException::withMessages(['arquivo' => 'Não foi possível gravar o arquivo. Tente novamente.']);
        }

        return DB::transaction(function () use ($trabalho, $gestor, $arquivo, $versao, $path) {
            $registro = $trabalho->arquivos()->create([
                'versao' => $versao, 'tipo' => 'final_anais', 'path' => $path,
                'nome_original' => Str::limit($arquivo->getClientOriginalName(), 200, ''), 'mime' => 'application/pdf',
                'tamanho' => $arquivo->getSize(), 'sha256' => hash_file('sha256', $arquivo->getRealPath()),
                'enviado_por' => $gestor->id, 'ativo' => false,
            ]);
            $this->registrar($trabalho, $gestor, $trabalho->status, $trabalho->status, 'PDF final dos Anais enviado');

            return $registro;
        });
    }

    private function anexarArquivo(Trabalho $trabalho, User $user, UploadedFile $arquivo): TrabalhoArquivo
    {
        $versao = ((int) $trabalho->arquivos()->max('versao')) + 1;
        $tipo = $trabalho->status === Trabalho::AGUARDANDO_CORRECAO ? 'corrigido' : 'original';
        $path = $arquivo->storeAs(
            "{$trabalho->evento_id}/{$trabalho->id}",
            sprintf('v%d-%s.pdf', $versao, Str::random(24)),
            'trabalhos'
        );

        if ($path === false) {
            throw ValidationException::withMessages(['arquivo' => 'Não foi possível gravar o arquivo. Tente novamente.']);
        }

        $trabalho->arquivos()->update(['ativo' => false]);

        return $trabalho->arquivos()->create([
            'versao' => $versao,
            'tipo' => $tipo,
            'path' => $path,
            'nome_original' => Str::limit($arquivo->getClientOriginalName(), 200, ''),
            'mime' => 'application/pdf',
            'tamanho' => $arquivo->getSize(),
            'sha256' => hash_file('sha256', $arquivo->getRealPath()),
            'enviado_por' => $user->id,
            'ativo' => true,
        ]);
    }

    // ------------------------------------------------------------------
    // Triagem (gestão)
    // ------------------------------------------------------------------

    public function podeTransicionar(Trabalho $trabalho, string $para): bool
    {
        return isset(self::TRANSICOES_GESTAO[$trabalho->status][$para]);
    }

    /**
     * Aplica uma transição da triagem, validando a matriz, a justificativa e (para a avaliação)
     * a existência de avaliadores atribuídos. Avisa os autores nas devoluções e rejeições.
     *
     * @throws \DomainException quando a transição não é permitida
     */
    public function transicionar(Trabalho $trabalho, User $gestor, string $para, ?string $justificativa = null): Trabalho
    {
        if (!$this->podeTransicionar($trabalho, $para)) {
            throw new \DomainException("Não é possível mudar o status de \"{$trabalho->statusRotulo()}\" para \"" . (Trabalho::STATUS[$para] ?? $para) . '".');
        }

        $justificativa = trim((string) $justificativa);
        if (self::TRANSICOES_GESTAO[$trabalho->status][$para] && $justificativa === '') {
            throw new \DomainException('Informe a justificativa para esta ação.');
        }

        if ($para === 'em_avaliacao' && $trabalho->evento->criterios()->where('ativo', true)->doesntExist()) {
            throw new \DomainException('Defina os critérios de avaliação do evento antes de encaminhar trabalhos para avaliação.');
        }

        if ($para === 'em_avaliacao') {
            [$atribuidos, $minimo] = $this->avaliadoresMinimos($trabalho);
            if ($atribuidos < $minimo) {
                $falta = $minimo - $atribuidos;
                throw new \DomainException("Atribua no mínimo {$minimo} avaliador(es) antes de encaminhar para avaliação (atribuídos: {$atribuidos}; faltam {$falta}).");
            }
        }

        if ($trabalho->status === 'em_avaliacao' && $para === 'em_triagem') {
            if ($trabalho->avaliacoes()->where('rodada', $trabalho->rodadaAtual())->where('status', 'concluida')->exists()) {
                throw new \DomainException('Já existe avaliação concluída: não é possível cancelar o envio. Use "Encerrar avaliação" ou aguarde os demais avaliadores.');
            }
            $justificativa = $justificativa !== '' ? $justificativa : 'Envio para avaliação cancelado pela gestão';
        }

        DB::transaction(function () use ($trabalho, $gestor, $para, $justificativa) {
            $de = $trabalho->status;
            $trabalho->update(['status' => $para]);
            $this->registrar($trabalho, $gestor, $de, $para, $justificativa !== '' ? $justificativa : null);
        });

        if (in_array($para, [Trabalho::DEVOLVIDO_TRIAGEM, 'rejeitado_triagem'], true)) {
            $this->avisarAutores($trabalho, $para === Trabalho::DEVOLVIDO_TRIAGEM
                ? 'Seu trabalho foi devolvido para ajustes'
                : 'Resultado da triagem do seu trabalho', $justificativa, $para === Trabalho::DEVOLVIDO_TRIAGEM);
        }

        return $trabalho->fresh();
    }

    /**
     * Avaliadores atribuídos na rodada atual (sem contar recusas) e o mínimo exigido pela chamada.
     *
     * @return array{0:int,1:int}
     */
    public function avaliadoresMinimos(Trabalho $trabalho): array
    {
        $rodada = max(1, (int) $trabalho->avaliacoes()->max('rodada'));
        $atribuidos = $trabalho->avaliacoes()->where('rodada', $rodada)->where('status', '!=', 'recusada')->count();
        $minimo = max(1, (int) ($trabalho->evento->trabalhoConfig?->avaliadores_por_trabalho ?? 2));

        return [$atribuidos, $minimo];
    }

    private function avisarAutores(Trabalho $trabalho, string $assunto, string $justificativa, bool $podeEditar): void
    {
        $trabalho->loadMissing(['autores', 'evento', 'submetente']);

        $emails = $trabalho->autores->pluck('email')->filter()->push($trabalho->submetente->email)
            ->map(fn ($e) => mb_strtolower($e))->unique();

        foreach ($emails as $email) {
            Mail::to($email)->queue(new TrabalhoStatusMail($trabalho, $assunto, $justificativa, $podeEditar));
        }
    }

    // ------------------------------------------------------------------
    // Avaliadores
    // ------------------------------------------------------------------

    /** Avaliadores designados ao evento, com a carga atual de avaliações abertas (menor carga primeiro). */
    public function avaliadoresDoEvento(Evento $evento): Collection
    {
        return User::query()
            ->whereIn('users.id', DB::table('evento_user')->where('evento_id', $evento->id)->where('role', Evento::PAPEL_AVALIADOR)->select('user_id'))
            ->withCount(['avaliacoesAtribuidas as carga' => fn ($q) => $q->whereIn('status', ['pendente', 'em_andamento'])
                ->whereHas('trabalho', fn ($t) => $t->where('evento_id', $evento->id))])
            ->with('participante')   // usado na checagem de conflito por CPF (sem consulta extra por avaliador)
            ->orderBy('carga')->orderBy('name')
            ->get();
    }

    /**
     * Por que cada avaliador NÃO pode ser atribuído a cada trabalho: já atribuído, recusou ou conflito de interesse
     * (autor/responsável do trabalho: ninguém avalia o próprio trabalho). Só informa os impedimentos.
     *
     * @param  Collection<int,Trabalho>  $trabalhos  com autores e avaliacoes (ou avaliacoesApresentacao) carregados
     * @param  Collection<int,User>  $avaliadores  com participante carregado
     * @param  string  $fase  'texto' (avaliação do trabalho escrito) | 'apresentacao'
     * @return array<int,array<int,array{tipo:string,motivo:string}>>  trabalho_id => [avaliador_id => impedimento]
     */
    public function restricoesAvaliadores(Collection $trabalhos, Collection $avaliadores, string $fase = 'texto'): array
    {
        $mapa = [];
        foreach ($trabalhos as $t) {
            $atribuicoes = $fase === 'apresentacao' ? $t->avaliacoesApresentacao : $t->avaliacoes;
            $rodada = $fase === 'apresentacao' ? null : max(1, (int) $atribuicoes->max('rodada'));
            $porAvaliador = $atribuicoes
                ->when($rodada, fn ($c) => $c->where('rodada', $rodada))
                ->keyBy('avaliador_id');

            foreach ($avaliadores as $u) {
                $existente = $porAvaliador->get($u->id);

                if ($existente && $existente->status !== 'recusada') {
                    $mapa[$t->id][$u->id] = ['tipo' => 'atribuido', 'motivo' => 'já está atribuído a este trabalho'];
                } elseif ($existente && $fase === 'texto') {
                    $mapa[$t->id][$u->id] = ['tipo' => 'recusou', 'motivo' => 'recusou a avaliação deste trabalho'];
                } elseif ($motivo = $this->conflitoDeInteresse($t, $u)) {
                    $mapa[$t->id][$u->id] = ['tipo' => 'conflito', 'motivo' => $motivo];
                }
            }
        }

        return $mapa;
    }

    /** Descreve o conflito de interesse entre o avaliador e o trabalho, ou null se não houver. */
    public function conflitoDeInteresse(Trabalho $trabalho, User $avaliador): ?string
    {
        $trabalho->loadMissing('autores');

        if ($trabalho->submetido_por === $avaliador->id) {
            return 'é o responsável pela submissão do trabalho';
        }

        $emails = $trabalho->autores->pluck('email')->filter()->map(fn ($e) => mb_strtolower($e));
        if ($emails->contains(mb_strtolower($avaliador->email))) {
            return 'consta como autor do trabalho (e-mail)';
        }

        $cpf = $avaliador->participante?->cpf;
        if ($cpf && $trabalho->autores->pluck('cpf')->filter()->contains($cpf)) {
            return 'consta como autor do trabalho (CPF)';
        }

        return null;
    }

    /**
     * @throws \DomainException
     */
    public function atribuirAvaliador(Trabalho $trabalho, User $avaliador, User $gestor, ?Carbon $prazo = null): TrabalhoAvaliacao
    {
        if (!in_array($trabalho->status, self::STATUS_ATRIBUICAO, true)) {
            throw new \DomainException('Só é possível atribuir avaliadores a trabalhos em triagem, em avaliação ou já avaliados.');
        }

        $designado = DB::table('evento_user')->where([
            'evento_id' => $trabalho->evento_id, 'user_id' => $avaliador->id, 'role' => Evento::PAPEL_AVALIADOR,
        ])->exists();
        if (!$designado) {
            throw new \DomainException("{$avaliador->name} não é avaliador deste evento.");
        }

        if ($motivo = $this->conflitoDeInteresse($trabalho, $avaliador)) {
            throw new \DomainException("Conflito de interesse: {$avaliador->name} {$motivo}.");
        }

        $rodada = max(1, (int) $trabalho->avaliacoes()->max('rodada'));

        if ($trabalho->avaliacoes()->where('avaliador_id', $avaliador->id)->where('rodada', $rodada)->exists()) {
            throw new \DomainException("{$avaliador->name} já está atribuído a este trabalho.");
        }

        $prazo ??= $trabalho->evento->trabalhoConfig?->avaliacao_fim ?? now()->addDays(14);

        $avaliacao = DB::transaction(function () use ($trabalho, $avaliador, $gestor, $prazo, $rodada) {
            $avaliacao = $trabalho->avaliacoes()->create([
                'avaliador_id' => $avaliador->id, 'rodada' => $rodada, 'status' => 'pendente',
                'atribuido_por' => $gestor->id, 'prazo' => $prazo,
            ]);
            $this->registrar($trabalho, $gestor, $trabalho->status, $trabalho->status, "Avaliador atribuído: {$avaliador->name}");

            // Avaliador adicional após a conclusão (ex.: divergência de notas): volta a "em avaliação"
            if ($trabalho->status === 'avaliado') {
                $trabalho->update(['status' => 'em_avaliacao']);
                $this->registrar($trabalho, $gestor, 'avaliado', 'em_avaliacao', 'Avaliação reaberta para novo avaliador');
            }

            return $avaliacao;
        });

        Mail::to($avaliador->email)->queue(new AvaliadorAtribuidoMail($trabalho->loadMissing('evento'), $avaliacao));

        return $avaliacao;
    }

    /**
     * @throws \DomainException
     */
    public function removerAvaliacao(TrabalhoAvaliacao $avaliacao, User $gestor): void
    {
        if (!in_array($avaliacao->status, ['pendente', 'em_andamento'], true)) {
            throw new \DomainException('Só é possível remover avaliações que ainda não foram concluídas.');
        }

        $trabalho = $avaliacao->trabalho;
        $nome = $avaliacao->avaliador->name;

        DB::transaction(function () use ($avaliacao, $trabalho, $gestor, $nome) {
            $avaliacao->delete();
            $this->registrar($trabalho, $gestor, $trabalho->status, $trabalho->status, "Avaliador removido: {$nome}");
        });
    }
}
