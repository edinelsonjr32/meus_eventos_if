<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LgpdRegistro extends Model
{
    protected $table = 'lgpd_registros';

    public $timestamps = false;

    protected $fillable = ['acao', 'titular_hash', 'executado_por', 'resumo', 'created_at'];

    protected $casts = ['resumo' => 'array', 'created_at' => 'datetime'];
}
