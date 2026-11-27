<?php

namespace App\Models;

use App\Support\HtmlSeguro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Notícia ou informação publicada na página pública de um evento. */
class NoticiaEvento extends Model
{
    protected $table = 'noticias_evento';

    protected $fillable = ['evento_id', 'titulo', 'conteudo', 'publicado_em', 'publicada', 'fixada', 'notificar_inscritos', 'criado_por'];

    protected $casts = ['publicado_em' => 'datetime', 'publicada' => 'boolean', 'fixada' => 'boolean', 'notificar_inscritos' => 'boolean', 'notificado_em' => 'datetime'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    /** O que o visitante vê: publicadas e com a data já chegada; fixadas primeiro, depois as mais novas. */
    public function scopeVisiveis($q)
    {
        return $q->where('publicada', true)->where('publicado_em', '<=', now())->orderByDesc('fixada')->orderByDesc('publicado_em')->orderByDesc('id');
    }

    public function conteudoHtml(): string
    {
        return HtmlSeguro::paraExibir($this->conteudo);
    }

    public function situacao(): string
    {
        if (!$this->publicada) {
            return 'rascunho';
        }

        return $this->publicado_em->isFuture() ? 'agendada' : 'no_ar';
    }
}
