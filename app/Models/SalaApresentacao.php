<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Sala virtual de apresentações do evento (link do Meet/Zoom/Teams...). O mesmo link serve a várias apresentações em horários diferentes. */
class SalaApresentacao extends Model
{
    protected $table = 'salas_apresentacao';

    protected $fillable = ['evento_id', 'nome', 'nome_normalizado', 'link', 'instrucoes', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (SalaApresentacao $s) {
            $s->nome = trim(preg_replace('/\s+/u', ' ', (string) $s->nome));
            $s->nome_normalizado = self::normalizar($s->nome);
        });
    }

    public static function normalizar(string $nome): string
    {
        return (string) Str::of($nome)->squish()->ascii()->lower();
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class)->withTrashed();
    }

    public function trabalhos(): HasMany
    {
        return $this->hasMany(Trabalho::class, 'sala_apresentacao_id');
    }

    /** Só abre links http(s): nunca javascript:, data: etc. */
    public function linkSeguro(): ?string
    {
        return preg_match('#^https?://#i', (string) $this->link) ? $this->link : null;
    }
}
