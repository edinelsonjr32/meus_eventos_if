<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Edição dos Anais de Trabalhos de um evento. */
class Anais extends Model
{
    protected $table = 'anais';

    protected $fillable = [
        'evento_id', 'titulo', 'edicao', 'issn', 'isbn', 'doi_prefixo', 'apresentacao', 'comissao', 'local_publicacao',
        'editora', 'ano', 'status', 'publicado_em', 'publicado_por', 'pdf_status', 'pdf_erro', 'pdf_aviso', 'pdf_path',
        'pdf_gerado_em', 'pdf_paginas', 'pdf_tamanho',
    ];

    protected $casts = ['publicado_em' => 'datetime', 'pdf_gerado_em' => 'datetime'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function publicado(): bool
    {
        return $this->status === 'publicado';
    }

    public function pdfPronto(): bool
    {
        return $this->pdf_status === 'pronto' && $this->pdf_path !== null;
    }

    /**
     * Trabalhos que entram nos Anais: aprovados, apresentados e com autorização de publicação dos autores.
     */
    public static function elegiveis(Evento $evento): Builder
    {
        return $evento->trabalhos()->getQuery()
            ->where('status', 'aprovado')
            ->where('apresentado', true)
            ->where('autoriza_publicacao', true);
    }
}
