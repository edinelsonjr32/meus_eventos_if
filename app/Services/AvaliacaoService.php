<?php

namespace App\Services;

use App\Mail\AvaliadorAtribuidoMail;
use App\Mail\TrabalhoDecisaoMail;
use App\Models\CriterioAvaliacao;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\TrabalhoAvaliacao;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Avaliação dos trabalhos: notas por critério, conclusão, nota final, decisão da gestão e reavaliação.
 * Escala fixa de 0 a 10 para as notas finais.
 */
class AvaliacaoService
{
    public const ESCALA = 10;

    /** Decisão => status resultante. */
    public const DECISOES = [
        'aprovado' => 'aprovado',
        'aprovado_com_ressalvas' => Trabalho::AGUARDANDO_CORRECAO,
        'reprovado' => 'reprovado',
    ];

    public function __construct(private TrabalhoWorkflow $workflow)
    {
    }

    public function criteriosAtivos(Evento $evento): Collection
    {
        return $evento->criterios()->where('ativo', true)->get();
    }

    /** Nota ponderada (0–10) a partir das notas por critério. */
    public function calcularNota(Collection $notas, Collection $criterios): float
    {
        $pesoTotal = $criterios->sum('peso');
        if ($pesoTotal <= 0) {
            return 0.0;
        }

        $soma = 0.0;
        foreach ($criterios as $c) {
            $nota = $notas->get($c->id);
            if ($nota !== null && $c->nota_max > 0) {
                $soma += ($nota / $c->nota_max) * $c->peso;
            }
        }

        return round($soma / $pesoTotal * self::ESCALA, 2);
    }

    // ------------------------------------------------------------------
    // Avaliador
    // ------------------------------------------------------------------

    /** Grava o rascunho da avaliação (qualquer parte pode estar em branco). */
    public function salvar(TrabalhoAvaliacao $avaliacao, array $dados): TrabalhoAvaliacao
    {
        $this->garantirEditavel($avaliacao);
        $criterios = $this->criteriosAtivos($avaliacao->trabalho->evento)->keyBy('id');

        DB::transaction(function () use ($avaliacao, $dados, $criterios) {
            foreach ($dados['notas'] ?? [] as $criterioId => $nota) {
                $criterio = $criterios->get((int) $criterioId);
                if (!$criterio || $nota === null || $nota === '') {
                    continue;
                }
                if ($nota < 0 || $nota > $criterio->nota_max) {
                    throw new \DomainException("A nota de \"{$criterio->nome}\" deve estar entre 0 e " . rtrim(rtrim(number_format($criterio->nota_max, 2, '.', ''), '0'), '.') . '.');
                }

                $avaliacao->notas()->updateOrCreate(
                    ['criterio_id' => $criterio->id],
                    ['nota' => $nota, 'comentario' => $dados['comentarios'][$criterioId] ?? null]
                );
            }

            $avaliacao->fill([
                'parecer' => $dados['parecer'] ?? $avaliacao->parecer,
                'parecer_confidencial' => $dados['parecer_confidencial'] ?? $avaliacao->parecer_confidencial,
                'recomendacao' => $dados['recomendacao'] ?? $avaliacao->recomendacao,
                'status' => 'em_andamento',
            ])->save();
        });

        return $avaliacao->fresh('notas');
    }

    /**
     * Conclui a avaliação: exige todas as notas, parecer e recomendação.
     *
     * @throws \DomainException
     */
    public function concluir(TrabalhoAvaliacao $avaliacao, User $avaliador, array $dados): TrabalhoAvaliacao
    {
        $this->salvar($avaliacao, $dados);
        $avaliacao->refresh();

        $criterios = $this->criteriosAtivos($avaliacao->trabalho->evento);
        $notas = $avaliacao->notas->pluck('nota', 'criterio_id');

        if ($criterios->isEmpty()) {
            throw new \DomainException('O evento não possui critérios de avaliação definidos.');
        }
        if ($criterios->contains(fn ($c) => !$notas->has($c->id))) {
            throw new \DomainException('Atribua nota a todos os critérios.');
        }
        if (mb_strlen(trim((string) $avaliacao->parecer)) < 20) {
            throw new \DomainException('Escreva o parecer (mínimo de 20 caracteres).');
        }
        if (!array_key_exists((string) $avaliacao->recomendacao, TrabalhoAvaliacao::RECOMENDACOES)) {
            throw new \DomainException('Selecione a recomendação final.');
        }

        DB::transaction(function () use ($avaliacao, $avaliador, $criterios, $notas) {
            $avaliacao->update([
                'nota_final' => $this->calcularNota($notas, $criterios),
                'status' => 'concluida',
                'concluida_em' => now(),
            ]);

            $trabalho = $avaliacao->trabalho;
            // Registro interno (de = para): não aparece para o autor
            $this->workflow->registrar($trabalho, $avaliador, $trabalho->status, $trabalho->status, 'Avaliação concluída');
            $this->atualizarTrabalho($trabalho);
        });

        return $avaliacao->fresh();
    }

    public function recusar(TrabalhoAvaliacao $avaliacao, User $avaliador, string $motivo): void
    {
        $this->garantirEditavel($avaliacao);

        if (mb_strlen(trim($motivo)) < 5) {
            throw new \DomainException('Informe o motivo da recusa.');
        }

        DB::transaction(function () use ($avaliacao, $avaliador, $motivo) {
            $avaliacao->update(['status' => 'recusada', 'parecer_confidencial' => trim($motivo)]);
            $trabalho = $avaliacao->trabalho;
            $this->workflow->registrar($trabalho, $avaliador, $trabalho->status, $trabalho->status, 'Avaliação recusada: ' . trim($motivo));
            $this->atualizarTrabalho($trabalho);
        });
    }

    private function garantirEditavel(TrabalhoAvaliacao $avaliacao): void
    {
        $trabalho = $avaliacao->trabalho;

        if (!in_array($avaliacao->status, ['pendente', 'em_andamento'], true)) {
            throw new \DomainException('Esta avaliação já foi encerrada.');
        }
        if ($trabalho->status !== 'em_avaliacao' || $avaliacao->rodada !== $trabalho->rodadaAtual()) {
            throw new \DomainException('Este trabalho não está em avaliação.');
        }
    }

    // ------------------------------------------------------------------
    // Consolidação
    // ------------------------------------------------------------------

    /** Avaliações concluídas da rodada atual. */
    public function concluidas(Trabalho $trabalho): Collection
    {
        return $trabalho->avaliacoes()->where('rodada', $trabalho->rodadaAtual())->where('status', 'concluida')->get();
    }

    public function notaMedia(Trabalho $trabalho): ?float
    {
        $notas = $this->concluidas($trabalho)->pluck('nota_final');

        return $notas->isEmpty() ? null : round($notas->avg(), 2);
    }

    /** Diferença entre a maior e a menor nota da rodada (null com menos de 2 avaliações). */
    public function divergencia(Trabalho $trabalho): ?float
    {
        $notas = $this->concluidas($trabalho)->pluck('nota_final');

        return $notas->count() < 2 ? null : round($notas->max() - $notas->min(), 2);
    }

    public function divergenciaAlta(Trabalho $trabalho): bool
    {
        $limite = $trabalho->evento->trabalhoConfig?->divergencia_maxima ?? 3;
        $d = $this->divergencia($trabalho);

        return $d !== null && $d > $limite;
    }

    /** Recalcula a nota final e encerra a fase de avaliação quando todos os avaliadores concluíram. */
    public function atualizarTrabalho(Trabalho $trabalho): void
    {
        $trabalho->refresh();
        $trabalho->nota_final = $this->notaMedia($trabalho);

        if ($trabalho->status === 'em_avaliacao') {
            $ativas = $trabalho->avaliacoes()->where('rodada', $trabalho->rodadaAtual())->where('status', '!=', 'recusada')->get();
            $meta = $trabalho->evento->trabalhoConfig?->avaliadores_por_trabalho ?? 2;

            if ($ativas->isNotEmpty() && $ativas->every(fn ($a) => $a->status === 'concluida') && $ativas->count() >= $meta) {
                $this->workflow->registrar($trabalho, null, 'em_avaliacao', 'avaliado', 'Todas as avaliações foram concluídas');
                $trabalho->status = 'avaliado';
            }
        }

        $trabalho->save();
    }

    // ------------------------------------------------------------------
    // Desempate
    // ------------------------------------------------------------------

    /** Há divergência alta e ainda dá para adicionar um avaliador de desempate? Devolve o motivo do bloqueio ou null. */
    public function motivoSemDesempate(Trabalho $trabalho): ?string
    {
        if (!in_array($trabalho->status, ['avaliado', 'em_avaliacao'], true)) {
            return 'O desempate só pode ser pedido com o trabalho em avaliação ou já avaliado.';
        }
        if (!$this->divergenciaAlta($trabalho)) {
            return 'Não há divergência alta entre os avaliadores (o limite é definido na chamada).';
        }
        $rodada = $trabalho->rodadaAtual();
        if ($trabalho->avaliacoes()->where('rodada', $rodada)->where('desempate', true)->where('status', '!=', 'recusada')->exists()) {
            return 'Já existe um avaliador de desempate neste trabalho.';
        }

        return null;
    }

    /**
     * Adiciona um avaliador de desempate quando há divergência alta. Se o trabalho já estava "avaliado", ele volta
     * para "em avaliação" para o novo avaliador poder avaliar; ao concluir, a nota passa a ser a média de todos.
     *
     * @throws \DomainException
     */
    public function atribuirDesempate(Trabalho $trabalho, User $avaliador, User $gestor, ?Carbon $prazo = null): TrabalhoAvaliacao
    {
        if ($motivo = $this->motivoSemDesempate($trabalho)) {
            throw new \DomainException($motivo);
        }

        return DB::transaction(function () use ($trabalho, $avaliador, $gestor, $prazo) {
            // valida papel, conflito e duplicidade antes de reabrir (se falhar, nada muda)
            $avaliacao = $this->workflow->atribuirAvaliador($trabalho, $avaliador, $gestor, $prazo);
            $avaliacao->update(['desempate' => true]);

            if ($trabalho->status === 'avaliado') {
                $trabalho->update(['status' => 'em_avaliacao']);
                $this->workflow->registrar($trabalho, $gestor, 'avaliado', 'em_avaliacao', "Reaberta para avaliação de desempate ({$avaliador->name})");
            } else {
                $this->workflow->registrar($trabalho, $gestor, $trabalho->status, $trabalho->status, "Avaliador de desempate atribuído: {$avaliador->name}");
            }

            return $avaliacao->fresh();
        });
    }

    // ------------------------------------------------------------------
    // Gestão
    // ------------------------------------------------------------------

    /** Encerra a avaliação sem esperar os demais avaliadores (precisa de ao menos uma avaliação concluída). */
    public function encerrar(Trabalho $trabalho, User $gestor): Trabalho
    {
        if ($trabalho->status !== 'em_avaliacao') {
            throw new \DomainException('O trabalho não está em avaliação.');
        }
        if ($this->concluidas($trabalho)->isEmpty()) {
            throw new \DomainException('É necessário ao menos uma avaliação concluída.');
        }

        DB::transaction(function () use ($trabalho, $gestor) {
            $trabalho->update(['status' => 'avaliado', 'nota_final' => $this->notaMedia($trabalho)]);
            $this->workflow->registrar($trabalho, $gestor, 'em_avaliacao', 'avaliado', 'Avaliação encerrada pela gestão');
        });

        return $trabalho->fresh();
    }

    /**
     * Decisão final sobre o trabalho avaliado (ou reenviado após correção).
     *
     * @throws \DomainException
     */
    public function decidir(Trabalho $trabalho, User $gestor, string $decisao, ?string $justificativa = null, ?Carbon $prazoCorrecao = null): Trabalho
    {
        if (!isset(self::DECISOES[$decisao])) {
            throw new \DomainException('Decisão inválida.');
        }
        if (!in_array($trabalho->status, ['avaliado', 'resubmetido'], true)) {
            throw new \DomainException('O trabalho precisa estar avaliado (ou reenviado após correção) para receber uma decisão.');
        }

        $justificativa = trim((string) $justificativa);
        if ($decisao !== 'aprovado' && $justificativa === '') {
            throw new \DomainException('Informe a mensagem ao autor para esta decisão.');
        }
        if ($decisao === 'aprovado_com_ressalvas' && (!$prazoCorrecao || $prazoCorrecao->isPast())) {
            throw new \DomainException('Informe um prazo de correção no futuro.');
        }

        $novoStatus = self::DECISOES[$decisao];

        DB::transaction(function () use ($trabalho, $gestor, $decisao, $justificativa, $prazoCorrecao, $novoStatus) {
            $de = $trabalho->status;
            $trabalho->update([
                'status' => $novoStatus,
                'decisao' => $decisao,
                'decisao_justificativa' => $justificativa !== '' ? $justificativa : null,
                'decisao_em' => now(),
                'decidido_por' => $gestor->id,
                'prazo_correcao' => $decisao === 'aprovado_com_ressalvas' ? $prazoCorrecao : null,
                'nota_final' => $this->notaMedia($trabalho) ?? $trabalho->nota_final,
            ]);
            $this->workflow->registrar($trabalho, $gestor, $de, $novoStatus, $justificativa !== '' ? $justificativa : null);
        });

        $trabalho->load(['autores', 'evento', 'submetente']);
        $emails = $trabalho->autores->pluck('email')->filter()->push($trabalho->submetente->email)->map(fn ($e) => mb_strtolower($e))->unique();
        foreach ($emails as $email) {
            Mail::to($email)->queue(new TrabalhoDecisaoMail($trabalho, $decisao));
        }

        return $trabalho->fresh();
    }

    public function prorrogarCorrecao(Trabalho $trabalho, User $gestor, Carbon $novoPrazo): void
    {
        if ($trabalho->status !== Trabalho::AGUARDANDO_CORRECAO) {
            throw new \DomainException('O trabalho não está aguardando correção.');
        }
        if ($novoPrazo->isPast()) {
            throw new \DomainException('O novo prazo deve estar no futuro.');
        }

        $trabalho->update(['prazo_correcao' => $novoPrazo]);
        $this->workflow->registrar($trabalho, $gestor, $trabalho->status, $trabalho->status, 'Prazo de correção prorrogado até ' . $novoPrazo->format('d/m/Y'));
    }

    /** Nova rodada: os avaliadores da rodada anterior recebem a versão corrigida. */
    public function reavaliar(Trabalho $trabalho, User $gestor): Trabalho
    {
        if ($trabalho->status !== 'resubmetido') {
            throw new \DomainException('Somente trabalhos reenviados após correção podem voltar à avaliação.');
        }

        $rodada = $trabalho->rodadaAtual();
        $anteriores = $trabalho->avaliacoes()->where('rodada', $rodada)->where('status', '!=', 'recusada')->get();
        if ($anteriores->isEmpty()) {
            throw new \DomainException('Não há avaliadores na rodada anterior. Volte o trabalho à triagem e atribua avaliadores.');
        }

        $prazo = $trabalho->evento->trabalhoConfig?->avaliacao_fim;
        $prazo = $prazo && $prazo->isFuture() ? $prazo : now()->addDays(14);

        $novas = DB::transaction(function () use ($trabalho, $gestor, $rodada, $anteriores, $prazo) {
            $novas = $anteriores->map(fn ($a) => $trabalho->avaliacoes()->create([
                'avaliador_id' => $a->avaliador_id, 'rodada' => $rodada + 1, 'status' => 'pendente',
                'atribuido_por' => $gestor->id, 'prazo' => $prazo,
            ]));
            $trabalho->update(['status' => 'em_avaliacao', 'nota_final' => null]);
            $this->workflow->registrar($trabalho, $gestor, 'resubmetido', 'em_avaliacao', 'Nova rodada de avaliação (versão corrigida)');

            return $novas;
        });

        $trabalho->loadMissing('evento');
        foreach ($novas as $avaliacao) {
            Mail::to($avaliacao->avaliador->email)->queue(new AvaliadorAtribuidoMail($trabalho, $avaliacao));
        }

        return $trabalho->fresh();
    }
}
