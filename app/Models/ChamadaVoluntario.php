<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Chamada para voluntários de um evento. */
class ChamadaVoluntario extends Model
{
    use \App\Models\Concerns\UrlComSlug;

    protected $table = 'chamadas_voluntarios';

    protected $fillable = ['evento_id', 'titulo', 'descricao', 'requisitos', 'vagas', 'inicio', 'fim', 'ativa', 'criado_por'];

    protected $casts = ['inicio' => 'datetime', 'fim' => 'datetime', 'ativa' => 'boolean', 'vagas' => 'integer'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function voluntarios(): HasMany
    {
        return $this->hasMany(Voluntario::class, 'chamada_id');
    }

    public function estaAberta(): bool
    {
        $agora = now();

        return $this->ativa && (!$this->inicio || $agora->gte($this->inicio)) && (!$this->fim || $agora->lte($this->fim));
    }

    public function situacao(): string
    {
        if (!$this->ativa) {
            return 'desativada';
        }
        if ($this->inicio && now()->lt($this->inicio)) {
            return 'em_breve';
        }

        return $this->fim && now()->gt($this->fim) ? 'encerrada' : 'aberta';
    }

    public function scopeAbertas($q)
    {
        return $q->where('ativa', true)
            ->where(fn ($w) => $w->whereNull('inicio')->orWhere('inicio', '<=', now()))
            ->where(fn ($w) => $w->whereNull('fim')->orWhere('fim', '>=', now()));
    }
}
