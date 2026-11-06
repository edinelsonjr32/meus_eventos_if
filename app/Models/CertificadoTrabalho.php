<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoTrabalho extends Model
{
    protected $table = 'certificados_trabalho';

    protected $fillable = [
        'trabalho_id', 'trabalho_autor_id', 'hash_validacao', 'tipo', 'apresentador', 'emitido_em', 'emitido_por',
        'downloads', 'revogado_em', 'revogado_motivo',
    ];

    protected $casts = ['apresentador' => 'boolean', 'emitido_em' => 'datetime', 'revogado_em' => 'datetime'];

    public function trabalho(): BelongsTo
    {
        return $this->belongsTo(Trabalho::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(TrabalhoAutor::class, 'trabalho_autor_id');
    }

    public function revogado(): bool
    {
        return $this->revogado_em !== null;
    }

    /** Ex.: "Coautor e apresentador". */
    public function funcaoRotulo(): string
    {
        $base = Trabalho::TIPOS_AUTOR[$this->tipo] ?? ucfirst($this->tipo);

        return $this->apresentador ? "{$base} e apresentador" : $base;
    }
}
