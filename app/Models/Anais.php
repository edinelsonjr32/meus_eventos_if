<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Edição dos Trabalhos Publicados de um evento. */
class Anais extends Model
{
    protected $table = 'anais';

    protected $fillable = [
        'evento_id', 'titulo', 'edicao', 'issn', 'isbn', 'doi_prefixo', 'apresentacao', 'comissao', 'local_publicacao',
        'editora', 'ano', 'status', 'dados_publicacao_visiveis', 'publicado_em', 'publicado_por', 'pdf_status', 'pdf_erro', 'pdf_aviso', 'pdf_path',
        'pdf_gerado_em', 'pdf_paginas', 'pdf_tamanho', 'pdf_iniciado_em',
    ];

    protected $casts = ['publicado_em' => 'datetime', 'pdf_gerado_em' => 'datetime', 'pdf_iniciado_em' => 'datetime', 'dados_publicacao_visiveis' => 'boolean'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function publicado(): bool
    {
        return $this->status === 'publicado';
    }

    /** Os dados de publicação (ISBN/ISSN/DOI, editora, local, ano, citação ABNT e PDF compilado) estão liberados ao público? */
    public function dadosVisiveis(): bool
    {
        return $this->publicado() && $this->dados_publicacao_visiveis;
    }

    /** Minutos sem terminar a partir dos quais a geração em segundo plano é considerada presa. */
    public const PDF_MINUTOS_PRESO = 12;

    public function pdfGerando(): bool
    {
        return $this->pdf_status === 'gerando';
    }

    /** A geração começou há muito tempo e não terminou (fila parada, processo morto...). */
    public function pdfPreso(): bool
    {
        return $this->pdfGerando() && $this->pdf_iniciado_em !== null && $this->pdf_iniciado_em->lt(now()->subMinutes(self::PDF_MINUTOS_PRESO));
    }

    public function pdfPronto(): bool
    {
        return $this->pdf_status === 'pronto' && $this->pdf_path !== null;
    }

    /**
     * Trabalhos que entram nos Trabalhos Publicados: aprovados, apresentados e com autorização de publicação dos autores.
     */
    public static function elegiveis(Evento $evento): Builder
    {
        $query = $evento->trabalhos()->getQuery()
            ->where('status', 'aprovado')
            ->where('apresentado', true)
            ->where('autoriza_publicacao', true)
            ->where('versao_final_pendente', false);   // aprovado com ressalvas: só entra depois que o autor envia a versão final

        // Com avaliação de apresentação ativa, só entra quem foi aprovado também na apresentação (aprovação 100%)
        if ($evento->trabalhoConfig?->avaliacao_apresentacao_ativa) {
            $query->where('resultado_apresentacao', 'aprovado');
        }

        return $query;
    }
}
