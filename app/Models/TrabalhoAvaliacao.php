<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrabalhoAvaliacao extends Model
{
    protected $table = 'trabalho_avaliacoes';

    public const STATUS = [
        'pendente' => 'Pendente',
        'em_andamento' => 'Em andamento',
        'concluida' => 'Concluída',
        'recusada' => 'Recusada',
    ];

    protected $fillable = [
        'trabalho_id', 'avaliador_id', 'rodada', 'desempate', 'status', 'atribuido_por', 'prazo', 'nota_final', 'parecer',
        'parecer_confidencial', 'recomendacao', 'concluida_em', 'lembrete_enviado_em',
    ];

    protected $casts = [
        'desempate' => 'boolean', 'prazo' => 'datetime', 'concluida_em' => 'datetime', 'lembrete_enviado_em' => 'datetime', 'nota_final' => 'float',
    ];

    public const RECOMENDACOES = [
        'aprovar' => 'Aprovar',
        'aprovar_com_ressalvas' => 'Aprovar com ressalvas',
        'reprovar' => 'Reprovar',
    ];

    public function notas()
    {
        return $this->hasMany(TrabalhoAvaliacaoNota::class, 'avaliacao_id');
    }

    public function recomendacaoRotulo(): ?string
    {
        return self::RECOMENDACOES[$this->recomendacao] ?? null;
    }

    public function atrasada(): bool
    {
        return $this->prazo && $this->prazo->isPast() && in_array($this->status, ['pendente', 'em_andamento'], true);
    }

    public function trabalho(): BelongsTo
    {
        return $this->belongsTo(Trabalho::class);
    }

    public function avaliador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'avaliador_id');
    }

    public function statusRotulo(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }
}
