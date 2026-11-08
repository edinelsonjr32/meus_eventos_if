<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Avaliação da apresentação oral/pôster de um trabalho aprovado (feita por avaliadores, em geral na feira de trabalhos). */
class ApresentacaoAvaliacao extends Model
{
    protected $table = 'apresentacao_avaliacoes';

    public const STATUS = ['pendente' => 'Pendente', 'em_andamento' => 'Em andamento', 'concluida' => 'Concluída', 'recusada' => 'Recusada'];

    protected $fillable = ['trabalho_id', 'avaliador_id', 'status', 'atribuido_por', 'prazo', 'nota_final', 'parecer', 'parecer_confidencial', 'concluida_em'];

    protected $casts = ['prazo' => 'datetime', 'concluida_em' => 'datetime', 'nota_final' => 'float'];

    public function trabalho(): BelongsTo
    {
        return $this->belongsTo(Trabalho::class);
    }

    public function avaliador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'avaliador_id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(ApresentacaoAvaliacaoNota::class, 'avaliacao_id');
    }

    public function statusRotulo(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function aberta(): bool
    {
        return in_array($this->status, ['pendente', 'em_andamento'], true);
    }

    public function atrasada(): bool
    {
        return $this->prazo && $this->prazo->isPast() && $this->aberta();
    }
}
