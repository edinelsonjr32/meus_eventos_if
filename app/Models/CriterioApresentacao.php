<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CriterioApresentacao extends Model
{
    protected $table = 'criterios_apresentacao';

    protected $fillable = ['evento_id', 'nome', 'descricao', 'peso', 'nota_max', 'ordem', 'ativo'];

    protected $casts = ['peso' => 'float', 'nota_max' => 'float', 'ativo' => 'boolean'];

    /** Critérios sugeridos para a feira de trabalhos (escala 0–10, pesos iguais). */
    public const PADROES = [
        ['Domínio do conteúdo', 'Segurança e conhecimento do assunto apresentado.'],
        ['Clareza e comunicação', 'Objetividade, organização da fala e linguagem adequada.'],
        ['Material de apoio', 'Qualidade visual do pôster/slides e legibilidade.'],
        ['Respostas à arguição', 'Capacidade de responder às perguntas da banca/público.'],
        ['Uso do tempo', 'Adequação ao tempo disponível.'],
    ];

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }
}
