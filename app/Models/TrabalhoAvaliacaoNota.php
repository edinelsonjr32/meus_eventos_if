<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrabalhoAvaliacaoNota extends Model
{
    protected $table = 'trabalho_avaliacao_notas';

    protected $fillable = ['avaliacao_id', 'criterio_id', 'nota', 'comentario'];

    protected $casts = ['nota' => 'float'];

    public function avaliacao()
    {
        return $this->belongsTo(TrabalhoAvaliacao::class, 'avaliacao_id');
    }

    public function criterio()
    {
        return $this->belongsTo(CriterioAvaliacao::class, 'criterio_id');
    }
}
