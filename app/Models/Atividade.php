<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Atividade extends Model
{
    protected static function booted(): void
    {
        // O texto "local" (usado em telas, QR codes e certificados) acompanha sempre o local cadastrado
        static::saving(function (Atividade $a) {
            if ($a->local_id && ($a->isDirty('local_id') || blank($a->local))) {
                $a->local = Local::find($a->local_id)?->rotulo() ?? $a->local;
            }
        });
    }

    public function localCadastrado(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    // CORREÇÃO: Adicionados 'data_fim' e 'vagas' que estavam faltando
    protected $fillable = [
        'evento_id',
        'titulo',
        'descricao',
        'tipo',
        'responsavel',
        'local',
        'local_id',
        'espaco',
        'equipamentos',
        'observacoes_organizacao',
        'proposta_id',
        'carga_horaria',
        'vagas',
        'data_inicio',
        'data_fim', // <--- Essencial para corrigir o erro SQLSTATE[HY000]
        'token_frequencia'
    ];

    protected $casts = [
        'data_inicio' => 'datetime',
        'data_fim' => 'datetime',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function frequencias(): HasMany
    {
        return $this->hasMany(Frequencia::class);
    }

    public function inscritos(): BelongsToMany
    {
        return $this->belongsToMany(Participante::class, 'atividade_inscricoes');
    }
}
