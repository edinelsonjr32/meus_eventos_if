<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipe extends Model
{
    /** Funções da equipe de organização (chave = valor gravado). */
    public const FUNCOES = ['Organizador', 'Coordenador', 'Monitor', 'Apoio Técnico', 'Palestrante', 'Voluntário', 'Secretaria'];

    public const CARGA_PADRAO = 20;

    protected $fillable = [
        'evento_id',
        'participante_id',
        'funcao', // Ex: Coordenador, Monitor, Apoio
        'carga_horaria_trabalho',
        'hash_validacao',
        'certificado_emitido_em',
        'certificado_emitido_por',
    ];

    protected $casts = ['carga_horaria_trabalho' => 'integer', 'certificado_emitido_em' => 'datetime'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    public function certificadoEmitido(): bool
    {
        return $this->certificado_emitido_em !== null && filled($this->hash_validacao);
    }
}
