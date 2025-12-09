<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Frequencia extends Model
{
    protected $fillable = [
        'participante_id',
        'atividade_id',
        'data_registro',
        'certificado_emitido',
        'hash_validacao',
        'tipo_participacao'
    ];

    protected $casts = [
        'data_registro' => 'datetime',
        'certificado_emitido' => 'boolean'
    ];

    // --- ADICIONE ESTES MÉTODOS ---

    // Relacionamento: Uma frequência pertence a um Participante
    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    // Relacionamento: Uma frequência pertence a uma Atividade
    public function atividade(): BelongsTo
    {
        return $this->belongsTo(Atividade::class);
    }
}
