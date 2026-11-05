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
        'trabalho_id', 'avaliador_id', 'rodada', 'status', 'atribuido_por', 'prazo', 'nota_final', 'parecer',
        'parecer_confidencial', 'recomendacao', 'concluida_em',
    ];

    protected $casts = ['prazo' => 'datetime', 'concluida_em' => 'datetime', 'nota_final' => 'decimal:2'];

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
