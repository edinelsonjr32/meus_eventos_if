<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Curso extends Model
{
    protected $fillable = ['nome'];

    // Relacionamento: Um curso tem várias turmas
    public function turmas()
    {
        return $this->hasMany(Turma::class);
    }
}
