<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Inscrição de um usuário como voluntário de um evento. */
class Voluntario extends Model
{
    public const STATUS = [
        'inscrito' => 'Inscrito (em análise)',
        'aprovado' => 'Aprovado',
        'lista_espera' => 'Lista de espera',
        'reprovado' => 'Não selecionado',
        'desistente' => 'Desistente',
    ];

    /** Períodos do dia que o voluntário pode marcar (início e fim em horas, o fim é exclusivo). */
    public const PERIODOS = [
        'manha' => ['rotulo' => 'Manhã', 'faixa' => '06h–12h', 'de' => 6, 'ate' => 12],
        'tarde' => ['rotulo' => 'Tarde', 'faixa' => '12h–18h', 'de' => 12, 'ate' => 18],
        'noite' => ['rotulo' => 'Noite', 'faixa' => '18h–24h', 'de' => 18, 'ate' => 24],
    ];

    protected $fillable = ['chamada_id', 'evento_id', 'user_id', 'participante_id', 'motivacao', 'disponibilidade', 'disponibilidade_dias', 'habilidades', 'telefone', 'status', 'parecer', 'decidido_por', 'decidido_em'];

    protected $casts = ['decidido_em' => 'datetime', 'disponibilidade_dias' => 'array'];

    public function chamada(): BelongsTo
    {
        return $this->belongsTo(ChamadaVoluntario::class, 'chamada_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    public function demandas(): BelongsToMany
    {
        return $this->belongsToMany(DemandaVoluntario::class, 'voluntario_demanda', 'voluntario_id', 'demanda_id')->withTimestamps()->orderBy('data_inicio');
    }

    public function statusRotulo(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /** Informou a disponibilidade pelo seletor de dias e períodos? (inscrições antigas só têm texto) */
    public function temDisponibilidade(): bool
    {
        return !empty($this->disponibilidade_dias);
    }

    /**
     * Dias/períodos exigidos pela demanda que o voluntário NÃO marcou como disponíveis (lista vazia = cobre tudo).
     * Sem horário na demanda ou sem disponibilidade estruturada não há o que comparar (retorna lista vazia).
     *
     * @return string[] ex.: ["12/11 (manhã)", "13/11 (tarde)"]
     */
    public function faltaDisponibilidade(DemandaVoluntario $demanda): array
    {
        if (!$this->temDisponibilidade() || !$demanda->data_inicio) {
            return [];
        }

        $inicio = $demanda->data_inicio;
        $fim = $demanda->data_fim ?: $inicio->copy()->addMinutes((int) round($demanda->carga_horaria * 60));
        $faltas = [];

        for ($dia = $inicio->copy()->startOfDay(); $dia->lt($fim); $dia->addDay()) {
            foreach (self::PERIODOS as $chave => $p) {
                $de = $dia->copy()->addHours($p['de']);
                $ate = $dia->copy()->addHours($p['ate']);
                if ($inicio->lt($ate) && $fim->gt($de) && !in_array($chave, $this->disponibilidade_dias[$dia->format('Y-m-d')] ?? [], true)) {
                    $faltas[] = $dia->format('d/m') . ' (' . mb_strtolower($p['rotulo']) . ')';
                }
            }
        }

        return $faltas;
    }

    /** Resumo legível: "12/11: manhã, tarde · 13/11: noite". */
    public function disponibilidadeResumo(): string
    {
        if (!$this->temDisponibilidade()) {
            return (string) $this->disponibilidade;
        }

        return collect($this->disponibilidade_dias)->sortKeys()->map(fn ($ps, $dia) => \Illuminate\Support\Carbon::parse($dia)->format('d/m') . ': ' . collect($ps)->map(fn ($p) => mb_strtolower(self::PERIODOS[$p]['rotulo']))->implode(', '))->implode(' · ');
    }

    /** Total de horas das demandas em que está alocado. */
    public function cargaHoraria(): float
    {
        return (float) $this->demandas->sum('carga_horaria');
    }
}
