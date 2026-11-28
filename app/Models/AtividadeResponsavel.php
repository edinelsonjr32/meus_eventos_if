<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Vínculo de uma pessoa cadastrada como responsável por uma atividade. */
class AtividadeResponsavel extends Model
{
    protected $table = 'atividade_responsaveis';

    protected $fillable = ['atividade_id', 'participante_id', 'user_id', 'nome'];

    public function atividade(): BelongsTo
    {
        return $this->belongsTo(Atividade::class);
    }

    /** Identificador usado no formulário: "p:12" (participante) ou "u:7" (conta sem cadastro de participante). */
    public function referencia(): string
    {
        return $this->participante_id ? "p:{$this->participante_id}" : "u:{$this->user_id}";
    }
}
