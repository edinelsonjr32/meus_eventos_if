<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Presença (e certificado) de quem ministrou ou ajudou em uma atividade. */
class AtividadeEquipe extends Model
{
    protected $table = 'atividade_equipe';

    public const FUNCOES = ['ministrante' => 'Ministrante', 'ajudante' => 'Ajudante'];

    protected $fillable = ['atividade_id', 'participante_id', 'funcao', 'presenca_em', 'presenca_por', 'certificado_emitido_em', 'hash_validacao'];

    protected $casts = ['presenca_em' => 'datetime', 'certificado_emitido_em' => 'datetime'];

    public function atividade(): BelongsTo
    {
        return $this->belongsTo(Atividade::class);
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    public function funcaoRotulo(): string
    {
        return self::FUNCOES[$this->funcao] ?? 'Ajudante';
    }

    public function certificadoEmitido(): bool
    {
        return $this->certificado_emitido_em !== null;
    }

    /** Registros da pessoa logada: pelo cadastro ligado à conta ou, se ainda não houver ligação, pelo mesmo e-mail. */
    public function scopeDaConta(Builder $q, User $user): Builder
    {
        return $q->whereHas('participante', fn ($p) => $p->where('user_id', $user->id)
            ->orWhere(fn ($w) => $w->whereNull('user_id')->where('email', $user->email)));
    }

    public function pertenceA(User $user): bool
    {
        return self::query()->daConta($user)->whereKey($this->id)->exists();
    }
}
