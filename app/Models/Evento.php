<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evento extends Model
{
    use SoftDeletes;

    // Papéis na tabela pivô evento_user
    public const PAPEL_EDITOR = 'editor';
    public const PAPEL_GESTOR_TRABALHOS = 'gestor_trabalhos';
    public const PAPEL_AVALIADOR = 'avaliador';
    public const PAPEL_RECEPCAO = 'recepcao';

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

    /**
     * Eventos que o usuário pode gerenciar (dono ou co-administrador).
     */
    public function scopeGerenciaveisPor($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('criado_por', $user->id)
                ->orWhereHas('administradoresSecundarios', fn ($u) => $u->where('users.id', $user->id)
                    ->where('evento_user.role', self::PAPEL_EDITOR));
        });
    }

    /**
     * Eventos em que o usuário gere trabalhos: dono, co-administrador ou gestor de trabalhos.
     */
    public function scopeGestaoTrabalhosPor($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('criado_por', $user->id)
                ->orWhereHas('administradoresSecundarios', function ($u) use ($user) {
                    $u->where('users.id', $user->id)
                        ->whereIn('evento_user.role', [self::PAPEL_EDITOR, self::PAPEL_GESTOR_TRABALHOS]);
                });
        });
    }

    /** Eventos em que o usuário faz check-in e credenciamento: dono, co-administrador ou recepção. */
    public function scopeCredenciamentoPor($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('criado_por', $user->id)
                ->orWhereHas('administradoresSecundarios', function ($u) use ($user) {
                    $u->where('users.id', $user->id)->whereIn('evento_user.role', [self::PAPEL_EDITOR, self::PAPEL_RECEPCAO]);
                });
        });
    }

    public function anais()
    {
        return $this->hasOne(Anais::class);
    }

    /** Quem acompanha os trabalhos: criador do evento, co-administradores e gestores de trabalhos. */
    public function usuariosDaGestaoDeTrabalhos()
    {
        return User::query()
            ->where('id', $this->criado_por)
            ->orWhereIn('id', \Illuminate\Support\Facades\DB::table('evento_user')
                ->where('evento_id', $this->id)
                ->whereIn('role', [self::PAPEL_EDITOR, self::PAPEL_GESTOR_TRABALHOS])
                ->select('user_id'))
            ->get();
    }

    public function trabalhoConfig()
    {
        return $this->hasOne(TrabalhoConfig::class);
    }

    public function premiacoes()
    {
        return $this->hasMany(Premiacao::class)->orderBy('tipo_trabalho')->orderBy('colocacao');
    }

    public function criteriosApresentacao()
    {
        return $this->hasMany(CriterioApresentacao::class)->orderBy('ordem')->orderBy('id');
    }

    public function criterios()
    {
        return $this->hasMany(CriterioAvaliacao::class)->orderBy('ordem')->orderBy('id');
    }

    public function areasTematicas()
    {
        return $this->hasMany(AreaTematica::class)->orderBy('ordem')->orderBy('nome');
    }

    public function trabalhos()
    {
        return $this->hasMany(Trabalho::class);
    }

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
