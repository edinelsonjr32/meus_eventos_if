<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pessoa de apoio (ajudante/monitor) indicada na proposta; a organização pode adicioná-la como participante do evento. */
class PropostaApoio extends Model
{
    protected $table = 'proposta_apoios';

    protected $fillable = ['proposta_id', 'nome', 'email', 'cpf', 'participante_id', 'adicionado_ao_evento_em'];

    protected $casts = ['adicionado_ao_evento_em' => 'datetime'];

    public function proposta(): BelongsTo
    {
        return $this->belongsTo(Proposta::class);
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    public function noEvento(): bool
    {
        return $this->adicionado_ao_evento_em !== null;
    }
}
