<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Colocação (1º a 3º) de um trabalho em uma categoria do evento. */
class Premiacao extends Model
{
    protected $table = 'premiacoes';

    public const COLOCACOES = [1 => '1º lugar', 2 => '2º lugar', 3 => '3º lugar'];

    protected $fillable = ['evento_id', 'trabalho_id', 'tipo_trabalho', 'colocacao', 'nota_geral', 'nota_texto', 'nota_apresentacao', 'apurada_por'];

    protected $casts = ['nota_geral' => 'float', 'nota_texto' => 'float', 'nota_apresentacao' => 'float', 'colocacao' => 'integer'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function trabalho(): BelongsTo
    {
        return $this->belongsTo(Trabalho::class);
    }

    public function certificados(): HasMany
    {
        return $this->hasMany(CertificadoPremiacao::class, 'premiacao_id');
    }

    public function colocacaoRotulo(): string
    {
        return self::COLOCACOES[$this->colocacao] ?? "{$this->colocacao}º lugar";
    }

    public function categoriaRotulo(): string
    {
        return TrabalhoConfig::TIPOS[$this->tipo_trabalho] ?? ucfirst((string) $this->tipo_trabalho);
    }
}
