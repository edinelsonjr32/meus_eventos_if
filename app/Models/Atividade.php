<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Atividade extends Model
{
    // CORREÇÃO: Adicionados 'data_fim' e 'vagas' que estavam faltando
    protected $fillable = [
        'evento_id',
        'titulo',
        'tipo',
        'carga_horaria',
        'vagas',
        'data_inicio',
        'data_fim', // <--- Essencial para corrigir o erro SQLSTATE[HY000]
        'token_frequencia'
    ];

    protected $casts = [
        'data_inicio' => 'datetime',
        'data_fim' => 'datetime',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function frequencias(): HasMany
    {
        return $this->hasMany(Frequencia::class);
    }

    public function inscritos(): BelongsToMany
    {
        return $this->belongsToMany(Participante::class, 'atividade_inscricoes');
    }
}
