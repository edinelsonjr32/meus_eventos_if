<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Foto, descrição e visibilidade de quem ministra atividades do evento. */
class MinistranteEvento extends Model
{
    protected $table = 'ministrantes_evento';

    protected $fillable = ['evento_id', 'chave', 'nome', 'descricao', 'caminho_foto', 'ordem', 'oculto'];

    protected $casts = ['oculto' => 'boolean', 'ordem' => 'integer'];

    /** "  JOÃO  da Silva " e "João da Silva" são a mesma pessoa. */
    public static function chaveDe(string $nome): string
    {
        return Str::limit(Str::lower(Str::squish(Str::ascii($nome))), 160, '');
    }

    public function fotoUrl(): ?string
    {
        return $this->caminho_foto && Storage::disk('public')->exists($this->caminho_foto)
            ? Storage::disk('public')->url($this->caminho_foto)
            : null;
    }
}
