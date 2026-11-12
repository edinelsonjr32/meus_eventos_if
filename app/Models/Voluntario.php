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

    /** Horário comercial de disponibilidade (08–12, 14–18, 18–22); o fim é exclusivo. Fora dessas faixas ninguém pode se declarar disponível. */
    public const PERIODOS = [
        'manha' => ['rotulo' => 'Manhã', 'faixa' => '08h–12h', 'de' => 8, 'ate' => 12],
        'tarde' => ['rotulo' => 'Tarde', 'faixa' => '14h–18h', 'de' => 14, 'ate' => 18],
        'noite' => ['rotulo' => 'Noite', 'faixa' => '18h–22h', 'de' => 18, 'ate' => 22],
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
            $total = max(0, $inicio->copy()->max($dia)->diffInMinutes($fim->copy()->min($dia->copy()->addDay()), false));
            $coberto = 0;
            foreach (self::PERIODOS as $chave => $p) {
                $de = $dia->copy()->addHours($p['de']);
                $ate = $dia->copy()->addHours($p['ate']);
                if ($inicio->lt($ate) && $fim->gt($de)) {
                    $coberto += $inicio->copy()->max($de)->diffInMinutes($fim->copy()->min($ate));
                    if (!in_array($chave, $this->disponibilidade_dias[$dia->format('Y-m-d')] ?? [], true)) {
                        $faltas[] = $dia->format('d/m') . ' (' . mb_strtolower($p['rotulo']) . ')';
                    }
                }
            }
            // trecho da demanda fora do horário comercial: o voluntário não tem como ter marcado disponibilidade
            if ($total - $coberto > 0) {
                $faltas[] = $dia->format('d/m') . ' (fora do horário de disponibilidade: ' . collect(self::PERIODOS)->pluck('faixa')->implode(', ') . ')';
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
