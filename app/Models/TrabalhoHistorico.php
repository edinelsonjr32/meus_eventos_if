<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrabalhoHistorico extends Model
{
    protected $table = 'trabalho_historico';

    public $timestamps = false;

    protected $fillable = ['trabalho_id', 'usuario_id', 'de', 'para', 'justificativa', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
