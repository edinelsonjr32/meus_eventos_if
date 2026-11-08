<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoPremiacao extends Model
{
    protected $table = 'certificados_premiacao';

    protected $fillable = ['premiacao_id', 'trabalho_autor_id', 'hash_validacao', 'emitido_em', 'emitido_por', 'downloads', 'revogado_em', 'revogado_motivo'];

    protected $casts = ['emitido_em' => 'datetime', 'revogado_em' => 'datetime'];

    public function premiacao(): BelongsTo
    {
        return $this->belongsTo(Premiacao::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(TrabalhoAutor::class, 'trabalho_autor_id');
    }

    public function revogado(): bool
    {
        return $this->revogado_em !== null;
    }
}
