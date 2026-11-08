<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApresentacaoAvaliacaoNota extends Model
{
    protected $table = 'apresentacao_avaliacao_notas';

    protected $fillable = ['avaliacao_id', 'criterio_id', 'nota', 'comentario'];

    protected $casts = ['nota' => 'float'];

    public function criterio()
    {
        return $this->belongsTo(CriterioApresentacao::class, 'criterio_id');
    }
}
