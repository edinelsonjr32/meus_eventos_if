<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Chamada para propostas de minicursos e oficinas de um evento. */
class ChamadaProposta extends Model
{
    use \App\Models\Concerns\UrlComSlug;

    protected $table = 'chamadas_propostas';

    public const TIPOS = ['minicurso' => 'Minicurso', 'oficina' => 'Oficina'];

    protected $fillable = ['evento_id', 'titulo', 'descricao', 'tipos_aceitos', 'inicio', 'fim', 'ativa', 'max_por_usuario', 'criado_por'];

    protected $casts = ['tipos_aceitos' => 'array', 'inicio' => 'datetime', 'fim' => 'datetime', 'ativa' => 'boolean', 'max_por_usuario' => 'integer'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function propostas(): HasMany
    {
        return $this->hasMany(Proposta::class, 'chamada_id');
    }

    /** Tipos aceitos (chave => rótulo); sem lista, todos. */
    public function tiposAceitos(): array
    {
        $chaves = $this->tipos_aceitos ?: array_keys(self::TIPOS);

        return array_intersect_key(self::TIPOS, array_flip($chaves));
    }

    public function estaAberta(): bool
    {
        $agora = now();

        return $this->ativa && (!$this->inicio || $agora->gte($this->inicio)) && (!$this->fim || $agora->lte($this->fim));
    }

    /** Situação para exibição: aberta | em_breve | encerrada | desativada */
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
