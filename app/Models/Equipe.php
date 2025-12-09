<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipe extends Model
{
    protected $fillable = [
        'evento_id',
        'participante_id',
        'funcao', // Ex: Coordenador, Monitor, Apoio
        'carga_horaria_trabalho'
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }
}
