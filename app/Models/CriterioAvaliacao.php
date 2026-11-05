<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CriterioAvaliacao extends Model
{
    protected $table = 'criterios_avaliacao';

    protected $fillable = ['evento_id', 'nome', 'descricao', 'peso', 'nota_max', 'ordem', 'ativo'];

    protected $casts = ['peso' => 'float', 'nota_max' => 'float', 'ativo' => 'boolean'];

    /** Critérios sugeridos (escala 0–10, pesos iguais). */
    public const PADROES = [
        ['Relevância e originalidade', 'Contribuição e atualidade do tema para a área.'],
        ['Fundamentação teórica', 'Qualidade do referencial e das referências citadas.'],
        ['Metodologia', 'Clareza e adequação dos procedimentos adotados.'],
        ['Resultados e discussão', 'Coerência entre dados, análise e conclusões.'],
        ['Clareza e normas', 'Redação, organização e atendimento às normas do evento.'],
    ];

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }
}
