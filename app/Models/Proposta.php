<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Proposta de minicurso ou oficina enviada por um usuário a uma chamada. */
class Proposta extends Model
{
    public const STATUS = [
        'enviada' => 'Enviada',
        'em_analise' => 'Em análise',
        'ajustes' => 'Ajustes solicitados',
        'aprovada' => 'Aprovada',
        'reprovada' => 'Não aprovada',
        'retirada' => 'Retirada',
    ];

    /** Situações em que o proponente ainda pode editar a proposta. */
    public const EDITAVEIS = ['enviada', 'ajustes'];

    protected $fillable = [
        'chamada_id', 'evento_id', 'user_id', 'participante_id', 'tipo', 'titulo', 'resumo', 'objetivos', 'publico_alvo', 'conteudo_programatico', 'metodologia',
        'carga_horaria_prevista', 'vagas_sugeridas', 'requer_laboratorio', 'espaco_solicitado', 'equipamentos_solicitados', 'observacoes',
        'status', 'parecer', 'decidido_por', 'decidido_em', 'atividade_id',
    ];

    protected $casts = ['requer_laboratorio' => 'boolean', 'decidido_em' => 'datetime', 'carga_horaria_prevista' => 'integer', 'vagas_sugeridas' => 'integer'];

    public function chamada(): BelongsTo
    {
        return $this->belongsTo(ChamadaProposta::class, 'chamada_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class)->withTrashed();
    }

    public function proponente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    public function atividade(): BelongsTo
    {
        return $this->belongsTo(Atividade::class);
    }

    public function apoios(): HasMany
    {
        return $this->hasMany(PropostaApoio::class);
    }

    public function statusRotulo(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function tipoRotulo(): string
    {
        return ChamadaProposta::TIPOS[$this->tipo] ?? ucfirst($this->tipo);
    }

    public function editavel(): bool
    {
        return in_array($this->status, self::EDITAVEIS, true);
    }

    /** A atividade vinculada já terminou? (libera o certificado de ministrante) */
    public function atividadeConcluida(): bool
    {
        return $this->status === 'aprovada' && $this->atividade && $this->atividade->data_fim && $this->atividade->data_fim->isPast();
    }
}
