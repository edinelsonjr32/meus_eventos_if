<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inscricao extends Model
{
    protected $table = 'inscricoes'; // Força o nome correto se necessário

    protected $fillable = ['evento_id', 'participante_id', 'data_inscricao', 'checkin_at'];
    protected $casts = ['checkin_at' => 'datetime'];
    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }
}
