<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assinatura extends Model
{
    protected $fillable = ['evento_id', 'nome', 'cargo', 'arquivo_assinatura'];

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }
}
