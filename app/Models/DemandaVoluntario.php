<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Tarefa/atividade em que voluntários atuam (com o servidor responsável: quem o voluntário deve procurar). */
class DemandaVoluntario extends Model
{
    protected $table = 'demandas_voluntarios';

    protected $fillable = ['evento_id', 'atividade_id', 'titulo', 'descricao', 'data_inicio', 'data_fim', 'local', 'local_id', 'vagas', 'carga_horaria', 'responsavel_nome', 'responsavel_contato'];

    protected $casts = ['data_inicio' => 'datetime', 'data_fim' => 'datetime', 'vagas' => 'integer', 'carga_horaria' => 'float'];

    protected static function booted(): void
    {
        static::saving(function (DemandaVoluntario $d) {
            if ($d->local_id && ($d->isDirty('local_id') || blank($d->local))) {
                $d->local = Local::find($d->local_id)?->rotulo() ?? $d->local;
            }
        });
    }

    public function localCadastrado(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class)->withTrashed();
    }

    public function atividade(): BelongsTo
    {
        return $this->belongsTo(Atividade::class);
    }

    public function voluntarios(): BelongsToMany
    {
        return $this->belongsToMany(Voluntario::class, 'voluntario_demanda', 'demanda_id', 'voluntario_id')->withTimestamps();
    }

    public function vagasRestantes(): ?int
    {
        return $this->vagas === null ? null : max(0, $this->vagas - $this->voluntarios()->count());
    }
}
