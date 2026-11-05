<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrabalhoConfig extends Model
{
    public const TIPOS = [
        'resumo' => 'Resumo',
        'resumo_expandido' => 'Resumo Expandido',
        'artigo_completo' => 'Artigo Completo',
        'relato_experiencia' => 'Relato de Experiência',
        'poster' => 'Pôster',
    ];

    protected $fillable = [
        'evento_id', 'ativo', 'submissao_inicio', 'submissao_fim', 'max_autores', 'max_arquivo_mb',
        'resumo_min_palavras', 'resumo_max_palavras', 'max_trabalhos_por_usuario', 'avaliacao_cega',
        'tipos_permitidos', 'regras_texto',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'avaliacao_cega' => 'boolean',
        'submissao_inicio' => 'datetime',
        'submissao_fim' => 'datetime',
        'tipos_permitidos' => 'array',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    /** Chamada ativa e dentro do período de submissão. */
    public function estaAberta(): bool
    {
        if (!$this->ativo) {
            return false;
        }

        $agora = now();

        return (!$this->submissao_inicio || $agora->gte($this->submissao_inicio))
            && (!$this->submissao_fim || $agora->lte($this->submissao_fim));
    }

    /** @return array<string,string> tipos habilitados (chave => rótulo) */
    public function tiposHabilitados(): array
    {
        $chaves = $this->tipos_permitidos ?: array_keys(self::TIPOS);

        return array_intersect_key(self::TIPOS, array_flip($chaves));
    }
}
