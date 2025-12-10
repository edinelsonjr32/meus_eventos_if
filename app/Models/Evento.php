<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evento extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'titulo',
        'slug',
        'descricao',
        'data_inicio',
        'data_fim',
        'local',
        'configuracoes',
        'criado_por',
        'latitude',
        'longitude',
        'raio_permitido',
        'caminho_fundo',
        'caminho_brasao',
        'cert_cabecalho',
        'cert_corpo',
        'cert_rodape'
    ];

    public function assinaturas()
    {
        return $this->hasMany(Assinatura::class);
    }


    public function administradoresSecundarios()
    {
        // Define a relação M-M, nome da tabela pivô e inclui a coluna 'role' da pivô
        return $this->belongsToMany(User::class, 'evento_user', 'evento_id', 'user_id')
            ->withPivot('role');
    }
    protected $casts = [
        'data_inicio' => 'datetime',
        'data_fim' => 'datetime',
        'configuracoes' => 'array' // Converte JSON para Array automaticamente
    ];

    public function atividades(): HasMany
    {
        return $this->hasMany(Atividade::class);
    }

    public function inscricoes()
    {
        return $this->hasMany(Inscricao::class);
    }

    // Helper para saber quantos inscritos tem
    public function totalInscritos(): int
    {
        return $this->inscricoes()->count();
    }

    // Adicione este método no Model Evento
    public function equipe()
    {
        return $this->hasMany(Equipe::class);
    }
}
