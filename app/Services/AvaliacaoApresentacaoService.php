<?php

namespace App\Services;

use App\Mail\ApresentacaoAvaliadorAtribuidoMail;
use App\Models\ApresentacaoAvaliacao;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\User;
use App\Support\ResultadoLote;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Avaliação da apresentação dos trabalhos aprovados (feira de trabalhos).
 * Independente da avaliação do texto: critérios, avaliadores e notas próprios; a nota final do
 * trabalho continua sendo a do texto, e a "nota geral" combina as duas conforme o peso configurado.
 */
class AvaliacaoApresentacaoService
{
    public function __construct(private TrabalhoWorkflow $workflow)
    {
    }

    public function criteriosAtivos(Evento $evento): Collection
    {
        return $evento->criteriosApresentacao()->where('ativo', true)->get();
    }

    public function calcularNota(Collection $notas, Collection $criterios): float
    {
        $pesoTotal = $criterios->sum('peso');
        if ($pesoTotal <= 0) {
            return 0.0;
        }
        $soma = 0.0;
        foreach ($criterios as $c) {
            $n = $notas->get($c->id);
            if ($n !== null && $c->nota_max > 0) {
                $soma += ($n / $c->nota_max) * $c->peso;
            }
        }

        return round($soma / $pesoTotal * AvaliacaoService::ESCALA, 2);
    }

    // ------------------------------------------------------------------ gestão

    /** Só trabalhos aprovados (inclui os aprovados após a correção) entram na avaliação da apresentação. */
    public function elegivel(Trabalho $trabalho): bool
    {
        return $trabalho->status === 'aprovado';
    }

    /** @throws \DomainException */
    public function atribuir(Trabalho $trabalho, User $avaliador, User $gestor, ?Carbon $prazo = null): ApresentacaoAvaliacao
    {
        if (!$trabalho->evento->trabalhoConfig?->avaliacao_apresentacao_ativa) {
            throw new \DomainException('A avaliação das apresentações não está ativada neste evento.');
        }
        if (!$this->elegivel($trabalho)) {
            throw new \DomainException("{$trabalho->codigo}: só trabalhos aprovados têm a apresentação avaliada.");
        }
        $designado = DB::table('evento_user')->where(['evento_id' => $trabalho->evento_id, 'user_id' => $avaliador->id, 'role' => Evento::PAPEL_AVALIADOR])->exists();
        if (!$designado) {
            throw new \DomainException("{$avaliador->name} não é avaliador deste evento.");
        }
        if ($motivo = $this->workflow->conflitoDeInteresse($trabalho, $avaliador)) {
            throw new \DomainException("Conflito de interesse: {$avaliador->name} {$motivo}.");
        }

        $existente = $trabalho->avaliacoesApresentacao()->where('avaliador_id', $avaliador->id)->first();
        if ($existente && $existente->status !== 'recusada') {
            throw new \DomainException("{$avaliador->name} já está atribuído à apresentação de {$trabalho->codigo}.");
        }

        $prazo ??= $trabalho->sessao?->data_fim ? $trabalho->sessao->data_fim->copy()->addDays(2) : now()->addDays(7);

        $avaliacao = DB::transaction(function () use ($trabalho, $avaliador, $gestor, $prazo, $existente) {
            // Reatribuir quem tinha recusado reabre a mesma avaliação
            $dados = ['status' => 'pendente', 'atribuido_por' => $gestor->id, 'prazo' => $prazo, 'parecer_confidencial' => null];
            $a = $existente ? tap($existente)->update($dados) : $trabalho->avaliacoesApresentacao()->create($dados + ['avaliador_id' => $avaliador->id]);
            $this->workflow->registrar($trabalho, $gestor, $trabalho->status, $trabalho->status, 'Avaliador da apresentação atribuído');
            $this->recalcular($trabalho);   // novo avaliador pendente: o resultado volta a ficar em aberto

            return $a;
        });

        Mail::to($avaliador->email)->queue(new ApresentacaoAvaliadorAtribuidoMail($trabalho, $avaliacao));

        return $avaliacao;
    }

    /**
     * Atribui os avaliadores a vários trabalhos de uma vez (ex.: os da "Feira de Trabalhos").
     * Cada recusa (conflito, duplicado, status) vira um item ignorado com motivo.
     *
     * @param  Collection<int,Trabalho>  $trabalhos
     * @param  Collection<int,User>  $avaliadores
     */
    public function atribuirEmLote(Collection $trabalhos, Collection $avaliadores, User $gestor, ?Carbon $prazo = null): ResultadoLote
    {
        $resultado = new ResultadoLote();
        foreach ($trabalhos as $t) {
            foreach ($avaliadores as $a) {
                try {
                    $this->atribuir($t, $a, $gestor, $prazo);
                    $resultado->ok();
                } catch (\DomainException $e) {
                    $resultado->ignorar($t->codigo, $e->getMessage());
                }
            }
        }

        return $resultado;
    }

    /** @throws \DomainException */
    public function remover(ApresentacaoAvaliacao $avaliacao): void
    {
        if ($avaliacao->status === 'concluida') {
            throw new \DomainException('Esta avaliação já foi concluída e não pode ser removida.');
        }
        $trabalho = $avaliacao->trabalho;
        $avaliacao->delete();
        $this->recalcular($trabalho);
    }

    // ------------------------------------------------------------------ avaliador

    public function editavel(ApresentacaoAvaliacao $avaliacao): bool
    {
        return $avaliacao->aberta() && $this->elegivel($avaliacao->trabalho)
            && (bool) $avaliacao->trabalho->evento->trabalhoConfig?->avaliacao_apresentacao_ativa;
    }

    public function salvar(ApresentacaoAvaliacao $avaliacao, array $dados): ApresentacaoAvaliacao
    {
        $this->garantirEditavel($avaliacao);
        $criterios = $this->criteriosAtivos($avaliacao->trabalho->evento)->keyBy('id');

        DB::transaction(function () use ($avaliacao, $dados, $criterios) {
            foreach ($dados['notas'] ?? [] as $id => $nota) {
                $c = $criterios->get((int) $id);
                if (!$c || $nota === null || $nota === '') {
                    continue;
                }
                if ($nota < 0 || $nota > $c->nota_max) {
                    throw new \DomainException("A nota de \"{$c->nome}\" deve estar entre 0 e " . rtrim(rtrim(number_format($c->nota_max, 2, '.', ''), '0'), '.') . '.');
                }
                $avaliacao->notas()->updateOrCreate(['criterio_id' => $c->id], ['nota' => $nota, 'comentario' => $dados['comentarios'][$id] ?? null]);
            }
            $avaliacao->fill([
                'parecer' => $dados['parecer'] ?? $avaliacao->parecer,
                'parecer_confidencial' => $dados['parecer_confidencial'] ?? $avaliacao->parecer_confidencial,
                'status' => 'em_andamento',
            ])->save();
        });

        return $avaliacao->fresh('notas');
    }

    /** @throws \DomainException */
    public function concluir(ApresentacaoAvaliacao $avaliacao, array $dados): ApresentacaoAvaliacao
    {
        $this->salvar($avaliacao, $dados);
        $avaliacao->refresh();

        $criterios = $this->criteriosAtivos($avaliacao->trabalho->evento);
        $notas = $avaliacao->notas->pluck('nota', 'criterio_id');

        if ($criterios->isEmpty()) {
            throw new \DomainException('O evento não possui critérios de avaliação da apresentação.');
        }
        if ($criterios->contains(fn ($c) => !$notas->has($c->id))) {
            throw new \DomainException('Atribua nota a todos os critérios.');
        }

        DB::transaction(function () use ($avaliacao, $criterios, $notas) {
            $avaliacao->update(['nota_final' => $this->calcularNota($notas, $criterios), 'status' => 'concluida', 'concluida_em' => now()]);
            $trabalho = $avaliacao->trabalho;

            // Se avaliaram a apresentação, ela aconteceu: marca como apresentado para não travar o fluxo (certificado, Anais)
            if (!$trabalho->apresentado) {
                app(ApresentacaoService::class)->marcar($trabalho, true, $avaliacao->avaliador, 'avaliacao');
            }

            $this->recalcular($trabalho->fresh());
        });

        return $avaliacao->fresh();
    }

    public function recusar(ApresentacaoAvaliacao $avaliacao, string $motivo): void
    {
        $this->garantirEditavel($avaliacao);
        if (mb_strlen(trim($motivo)) < 5) {
            throw new \DomainException('Informe o motivo da recusa.');
        }
        $avaliacao->update(['status' => 'recusada', 'parecer_confidencial' => trim($motivo)]);
        $this->recalcular($avaliacao->trabalho);
    }

    private function garantirEditavel(ApresentacaoAvaliacao $avaliacao): void
    {
        if (!$avaliacao->aberta()) {
            throw new \DomainException('Esta avaliação já foi encerrada.');
        }
        if (!$this->editavel($avaliacao)) {
            throw new \DomainException('A avaliação desta apresentação não está disponível.');
        }
    }

    // ------------------------------------------------------------------ consolidação

    public function concluidas(Trabalho $trabalho): Collection
    {
        return $trabalho->avaliacoesApresentacao()->where('status', 'concluida')->get();
    }

    /** Recalcula a nota da apresentação (média das avaliações concluídas). */
    public function recalcular(Trabalho $trabalho): ?float
    {
        $notas = $this->concluidas($trabalho)->pluck('nota_final');
        $media = $notas->isEmpty() ? null : round($notas->avg(), 2);

        // O resultado só sai quando não há mais avaliador pendente: média >= nota mínima => aprovado
        $pendentes = $trabalho->avaliacoesApresentacao()->whereIn('status', ['pendente', 'em_andamento'])->exists();
        $minima = (float) ($trabalho->evento->trabalhoConfig?->nota_minima_apresentacao ?? 7);
        $resultado = ($media === null || $pendentes) ? null : ($media >= $minima ? 'aprovado' : 'reprovado');

        $trabalho->update(['nota_apresentacao' => $media, 'resultado_apresentacao' => $resultado]);

        return $media;
    }

    /** Recalcula todos os trabalhos do evento (ex.: depois de mudar a nota mínima). */
    public function recalcularEvento(Evento $evento): void
    {
        $evento->trabalhos()->where('status', 'aprovado')->with('evento.trabalhoConfig')->get()->each(fn ($t) => $this->recalcular($t));
    }
}
