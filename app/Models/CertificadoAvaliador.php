<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoAvaliador extends Model
{
    protected $table = 'certificados_avaliador';

    public const FASES = ['escrita' => 'Avaliação escrita', 'apresentacao' => 'Avaliação de apresentação'];

    protected $fillable = ['evento_id', 'user_id', 'fase', 'quantidade', 'carga_horaria', 'hash_validacao', 'emitido_em', 'emitido_por', 'revogado_em'];

    protected $casts = ['emitido_em' => 'datetime', 'revogado_em' => 'datetime', 'quantidade' => 'integer', 'carga_horaria' => 'integer'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function avaliador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function revogado(): bool
    {
        return $this->revogado_em !== null;
    }

    public function faseRotulo(): string
    {
        return self::FASES[$this->fase] ?? $this->fase;
    }
}
