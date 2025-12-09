<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Participante extends Model
{
    protected $fillable = [
        'user_id',
        'nome_completo',
        'cpf',
        'email',
        'tipo_vinculo',
        'matricula',
        'turma_id'
    ];

    /**
     * Relação com o Usuário de Login (Opcional)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relação com a Turma (Apenas para alunos)
     */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    /**
     * Relação com as Inscrições no Evento (Geral)
     */
    public function inscricoes(): HasMany
    {
        return $this->hasMany(Inscricao::class);
    }

    /**
     * Relação com as Frequências / Presenças (Certificados)
     * ESTA ERA A FUNÇÃO QUE FALTAVA
     */
    public function frequencias(): HasMany
    {
        return $this->hasMany(Frequencia::class);
    }

    /**
     * Relação com a Agenda (Atividades que ele se inscreveu)
     */
    public function atividadesInscritas(): BelongsToMany
    {
        return $this->belongsToMany(Atividade::class, 'atividade_inscricoes')
            ->withTimestamps();
    }
}
