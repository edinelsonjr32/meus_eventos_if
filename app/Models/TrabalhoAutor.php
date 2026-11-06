<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrabalhoAutor extends Model
{
    protected $table = 'trabalho_autores';

    protected $fillable = [
        'trabalho_id', 'ordem', 'participante_id', 'nome_completo', 'cpf', 'email', 'telefone', 'instituicao',
        'vinculo', 'titulacao', 'lattes', 'orcid', 'tipo_autor', 'categoria_autor', 'apresentador', 'correspondente',
    ];

    protected $casts = ['apresentador' => 'boolean', 'correspondente' => 'boolean'];

    public function trabalho(): BelongsTo
    {
        return $this->belongsTo(Trabalho::class);
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    public function categoriaRotulo(): ?string
    {
        return Trabalho::CATEGORIAS_AUTOR[$this->categoria_autor] ?? null;
    }

    public function tipoRotulo(): string
    {
        return Trabalho::TIPOS_AUTOR[$this->tipo_autor] ?? $this->tipo_autor;
    }
}
