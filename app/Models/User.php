<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function participante()
    {
        return $this->hasOne(Participante::class);
    }

    /**
     * Verifica se o usuário tem algum dos papéis informados no evento (tabela evento_user).
     */
    public function papelNoEvento(Evento $evento, array $papeis): bool
    {
        return $this->eventosAdministrados()
            ->where('eventos.id', $evento->id)
            ->whereIn('evento_user.role', $papeis)
            ->exists();
    }

    public function eventosAdministrados()
    {
        // Define a relação M-M, nome da tabela pivô e inclui a coluna 'role' da pivô
        return $this->belongsToMany(Evento::class, 'evento_user', 'user_id', 'evento_id')
            ->withPivot('role');
    }
}
