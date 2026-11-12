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

    protected $fillable = ['chamada_id', 'evento_id', 'user_id', 'participante_id', 'motivacao', 'disponibilidade', 'habilidades', 'telefone', 'status', 'parecer', 'decidido_por', 'decidido_em'];

    protected $casts = ['decidido_em' => 'datetime'];

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

    /** Total de horas das demandas em que está alocado. */
    public function cargaHoraria(): float
    {
        return (float) $this->demandas->sum('carga_horaria');
    }
}
