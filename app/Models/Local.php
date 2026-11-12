<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Local/espaço cadastrado de um evento (sala, laboratório, auditório...). Nome único por evento, sem diferenciar maiúsculas nem acentos. */
class Local extends Model
{
    protected $table = 'locais';

    public const TIPOS = [
        'sala' => 'Sala de aula', 'laboratorio' => 'Laboratório', 'auditorio' => 'Auditório', 'quadra' => 'Quadra / ginásio',
        'area_externa' => 'Área externa', 'biblioteca' => 'Biblioteca', 'outro' => 'Outro',
    ];

    protected $fillable = ['evento_id', 'nome', 'nome_normalizado', 'bloco', 'tipo', 'capacidade', 'equipamentos', 'ativo'];

    protected $casts = ['ativo' => 'boolean', 'capacidade' => 'integer'];

    protected static function booted(): void
    {
        // O nome é limpo (espaços repetidos) e a chave de unicidade é recalculada sempre
        static::saving(function (Local $l) {
            $l->nome = trim(preg_replace('/\s+/u', ' ', (string) $l->nome));
            $l->nome_normalizado = self::normalizar($l->nome);
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

    public function atividades(): HasMany
    {
        return $this->hasMany(Atividade::class, 'local_id');
    }

    public function demandas(): HasMany
    {
        return $this->hasMany(DemandaVoluntario::class, 'local_id');
    }

    /** Texto exibido nas telas e certificados: "Lab 2 (Bloco B)". */
    public function rotulo(): string
    {
        return $this->nome . ($this->bloco ? " ({$this->bloco})" : '');
    }

    public function tipoRotulo(): string
    {
        return self::TIPOS[$this->tipo] ?? ucfirst($this->tipo);
    }

    public function emUso(): bool
    {
        return $this->atividades()->exists() || $this->demandas()->exists();
    }
}
