<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Tarefa/atividade em que voluntários atuam (com o servidor responsável: quem o voluntário deve procurar). */
class DemandaVoluntario extends Model
{
    protected $table = 'demandas_voluntarios';

    protected $fillable = ['evento_id', 'atividade_id', 'titulo', 'descricao', 'data_inicio', 'data_fim', 'local', 'local_id', 'vagas', 'carga_horaria', 'responsavel_nome', 'responsavel_contato', 'automatica'];

    protected $casts = ['data_inicio' => 'datetime', 'data_fim' => 'datetime', 'vagas' => 'integer', 'carga_horaria' => 'float', 'automatica' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (DemandaVoluntario $d) {
            if ($d->local_id && ($d->isDirty('local_id') || blank($d->local))) {
                $d->local = Local::find($d->local_id)?->rotulo() ?? $d->local;
            }
        });
    }

    /** Demanda de apoio gerada para a atividade (idempotente: não duplica se já existir uma ligada a ela). */
    public static function criarPara(Atividade $a): ?self
    {
        if (self::where('atividade_id', $a->id)->exists()) {
            return null;
        }

        return self::create([
            'evento_id' => $a->evento_id, 'atividade_id' => $a->id, 'titulo' => self::tituloPara($a->titulo),
            'descricao' => 'Apoio à atividade "' . $a->titulo . '" (recepção, organização da sala, controle de presença).',
            'data_inicio' => $a->data_inicio, 'data_fim' => $a->data_fim, 'local_id' => $a->local_id,
            'carga_horaria' => max(0.5, (float) $a->carga_horaria), 'responsavel_nome' => $a->responsavel, 'automatica' => true,
            'vagas' => $a->voluntarios_necessarios ?: null,
        ]);
    }

    public static function tituloPara(?string $titulo): string
    {
        return mb_substr('Apoio: ' . $titulo, 0, 255);
    }

    /** Mantém a demanda automática alinhada com a atividade (horário, local, carga horária e responsável). */
    public static function sincronizarCom(Atividade $a): void
    {
        $d = self::where('atividade_id', $a->id)->where('automatica', true)->first();
        if (!$d) {
            return;
        }
        $campos = [
            'data_inicio' => $a->data_inicio, 'data_fim' => $a->data_fim, 'local_id' => $a->local_id,
            'carga_horaria' => max(0.5, (float) $a->carga_horaria), 'responsavel_nome' => $a->responsavel,
        ];
        if ($a->wasChanged('voluntarios_necessarios')) {
            $campos['vagas'] = $a->voluntarios_necessarios ?: null;
        }
        // o título só acompanha se o admin não o renomeou
        if ($d->titulo === self::tituloPara($a->getOriginal('titulo'))) {
            $campos['titulo'] = self::tituloPara($a->titulo);
        }
        $campos['local'] = null;   // o modelo preenche pelo cadastro de locais
        $d->update($campos);
    }

    public function localCadastrado(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class)->withTrashed();
    }

    public function atividade(): BelongsTo
    {
        return $this->belongsTo(Atividade::class);
    }

    public function voluntarios(): BelongsToMany
    {
        return $this->belongsToMany(Voluntario::class, 'voluntario_demanda', 'demanda_id', 'voluntario_id')->withTimestamps();
    }

    public function vagasRestantes(): ?int
    {
        return $this->vagas === null ? null : max(0, $this->vagas - $this->voluntarios()->count());
    }
}
