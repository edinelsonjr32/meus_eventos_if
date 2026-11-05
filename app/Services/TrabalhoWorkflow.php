<?php

namespace App\Services;

use App\Http\Requests\SalvarTrabalhoRequest;
use App\Mail\AvaliadorAtribuidoMail;
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
    ];

    /** Status em que ainda é possível atribuir ou remover avaliadores. */
    public const STATUS_ATRIBUICAO = ['em_triagem', 'em_avaliacao'];

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
        $destinatarios = $trabalho->autores->pluck('email')->push($user->email)->map(fn ($e) => mb_strtolower($e))->unique();
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

    private function sincronizarAutores(Trabalho $trabalho, array $autores): void
    {
        $trabalho->autores()->delete();

        $principal = collect($autores)->contains(fn ($a) => !empty($a['correspondente']));

        foreach (array_values($autores) as $i => $a) {
            // Apenas vincula (nunca altera) um participante já existente com o mesmo CPF
            $participante = Participante::where('cpf', $a['cpf'])->first();

            $trabalho->autores()->create([
                'ordem' => $i + 1,
                'participante_id' => $participante?->id,
                'nome_completo' => $a['nome_completo'],
                'cpf' => $a['cpf'],
                'email' => $a['email'],
                'telefone' => $a['telefone'] ?? null,
                'instituicao' => $a['instituicao'] ?? null,
                'vinculo' => $a['vinculo'] ?? null,
                'titulacao' => $a['titulacao'] ?? null,
                'lattes' => $a['lattes'] ?? null,
                'orcid' => $a['orcid'] ?? null,
                'tipo_autor' => $a['tipo_autor'],
                'apresentador' => (bool) ($a['apresentador'] ?? false),
                // Sem correspondente definido, o autor principal assume
                'correspondente' => $principal ? (bool) ($a['correspondente'] ?? false) : $a['tipo_autor'] === 'autor_principal',
            ]);
        }
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

        if ($para === 'em_avaliacao' && $trabalho->avaliacoes()->where('status', '!=', 'recusada')->doesntExist()) {
            throw new \DomainException('Atribua ao menos um avaliador antes de encaminhar para avaliação.');
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

    private function avisarAutores(Trabalho $trabalho, string $assunto, string $justificativa, bool $podeEditar): void
    {
        $trabalho->loadMissing(['autores', 'evento', 'submetente']);

        $emails = $trabalho->autores->pluck('email')->push($trabalho->submetente->email)
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
            ->orderBy('carga')->orderBy('name')
            ->get();
    }

    /** Descreve o conflito de interesse entre o avaliador e o trabalho, ou null se não houver. */
    public function conflitoDeInteresse(Trabalho $trabalho, User $avaliador): ?string
    {
        $trabalho->loadMissing('autores');

        if ($trabalho->submetido_por === $avaliador->id) {
            return 'é o responsável pela submissão do trabalho';
        }

        $emails = $trabalho->autores->pluck('email')->map(fn ($e) => mb_strtolower($e));
        if ($emails->contains(mb_strtolower($avaliador->email))) {
            return 'consta como autor do trabalho (e-mail)';
        }

        $cpf = $avaliador->participante?->cpf;
        if ($cpf && $trabalho->autores->pluck('cpf')->contains($cpf)) {
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
            throw new \DomainException('Só é possível atribuir avaliadores a trabalhos em triagem ou em avaliação.');
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
        if ($avaliacao->status !== 'pendente') {
            throw new \DomainException('Só é possível remover avaliações ainda não iniciadas.');
        }

        $trabalho = $avaliacao->trabalho;
        $nome = $avaliacao->avaliador->name;

        DB::transaction(function () use ($avaliacao, $trabalho, $gestor, $nome) {
            $avaliacao->delete();
            $this->registrar($trabalho, $gestor, $trabalho->status, $trabalho->status, "Avaliador removido: {$nome}");
        });
    }
}
