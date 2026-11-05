<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AreaTematica extends Model
{
    protected $table = 'areas_tematicas';

    protected $fillable = ['evento_id', 'nome', 'ordem'];

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }
}
