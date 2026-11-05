<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrabalhoArquivo extends Model
{
    protected $table = 'trabalho_arquivos';

    protected $fillable = [
        'trabalho_id', 'versao', 'tipo', 'path', 'nome_original', 'mime', 'tamanho', 'sha256', 'enviado_por', 'ativo',
    ];

    protected $casts = ['ativo' => 'boolean'];

    public function trabalho(): BelongsTo
    {
        return $this->belongsTo(Trabalho::class);
    }
}
