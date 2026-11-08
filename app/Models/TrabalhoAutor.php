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

    public function certificado()
    {
        return $this->hasOne(CertificadoTrabalho::class, 'trabalho_autor_id');
    }

    public function trabalho(): BelongsTo
    {
        return $this->belongsTo(Trabalho::class);
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    /** Este autor é o usuário? (mesmo e-mail, mesmo CPF ou participante vinculado à conta) */
    public function pertenceA(User $usuario): bool
    {
        $participante = $usuario->participante;

        return ($this->email && mb_strtolower($this->email) === mb_strtolower($usuario->email))
            || ($participante && ($this->participante_id === $participante->id || ($this->cpf && $this->cpf === $participante->cpf)));
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
