<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Turma extends Model
{
    // Removemos 'nome' daqui
    protected $fillable = ['curso_id', 'ano'];

    protected $with = ['curso'];

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }

    // Ajustamos a exibição para: "Nome do Curso - Ano"
    // Ex: "Técnico em Informática - 2024"
    protected function nomeCompleto(): Attribute
    {
        return Attribute::make(
            get: fn() => "{$this->curso->nome} - {$this->ano}",
        );
    }
}
