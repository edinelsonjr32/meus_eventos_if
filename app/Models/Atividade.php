<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Atividade extends Model
{
    protected static function booted(): void
    {
        // O texto "local" (usado em telas, QR codes e certificados) acompanha sempre o local cadastrado
        // Toda atividade do evento ganha, ao ser criada, a demanda de voluntários correspondente (e a acompanha depois)
        static::created(function (Atividade $a) {
            try {
                DemandaVoluntario::criarPara($a);
            } catch (\Throwable $e) {
                report($e);   // nunca impede a criação da atividade
            }
        });
        static::updated(function (Atividade $a) {
            if ($a->wasChanged(['titulo', 'data_inicio', 'data_fim', 'local_id', 'carga_horaria', 'responsavel', 'voluntarios_necessarios'])) {
                try {
                    DemandaVoluntario::sincronizarCom($a);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });
        static::deleting(fn (Atividade $a) => DemandaVoluntario::where('atividade_id', $a->id)->where('automatica', true)->delete());

        static::saving(function (Atividade $a) {
            if ($a->local_id && ($a->isDirty('local_id') || blank($a->local))) {
                $a->local = Local::find($a->local_id)?->rotulo() ?? $a->local;
            }
        });
    }

    /** Presença e certificado de ministrantes e ajudantes. */
    public function equipeCertificada(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AtividadeEquipe::class);
    }

    /** Pessoas cadastradas vinculadas como responsáveis. */
    public function responsaveis(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AtividadeResponsavel::class)->orderBy('id');
    }

    public function localCadastrado(): BelongsTo
    {
        return $this->belongsTo(Local::class, 'local_id');
    }

    // CORREÇÃO: Adicionados 'data_fim' e 'vagas' que estavam faltando
    protected $fillable = [
        'evento_id',
        'titulo',
        'descricao',
        'tipo',
        'responsavel',
        'local',
        'local_id',
        'espaco',
        'equipamentos',
        'observacoes_organizacao',
        'proposta_id',
        'carga_horaria',
        'gera_certificado',
        'vagas',
        'voluntarios_necessarios',
        'data_inicio',
        'data_fim', // <--- Essencial para corrigir o erro SQLSTATE[HY000]
        'token_frequencia',
        'frequencia_encerrada_em',
        'frequencia_fecha_em',
    ];

    protected $casts = [
        'data_inicio' => 'datetime',
        'data_fim' => 'datetime',
        'frequencia_encerrada_em' => 'datetime',
        'frequencia_fecha_em' => 'datetime',
        'gera_certificado' => 'boolean',
    ];

    /** Sugestões de tipo (o campo é livre: qualquer outro texto também vale). */
    public const TIPOS = [
        'palestra' => 'Palestra', 'minicurso' => 'Minicurso', 'oficina' => 'Oficina', 'mesa_redonda' => 'Mesa Redonda',
        'jogo' => 'Jogo / Competição', 'gincana' => 'Gincana', 'torneio' => 'Torneio', 'exibicao_filme' => 'Exibição de filme',
        'apresentacao_cultural' => 'Apresentação cultural', 'exposicao' => 'Exposição', 'feira' => 'Feira', 'roda_conversa' => 'Roda de conversa',
        'visita_tecnica' => 'Visita técnica', 'sarau' => 'Sarau', 'cerimonia' => 'Cerimônia', 'outro' => 'Outro',
    ];

    /** Nome do tipo para exibição (aceita os códigos antigos e textos livres). */
    public function tipoRotulo(): string
    {
        return self::TIPOS[$this->tipo] ?? ucfirst(str_replace('_', ' ', (string) $this->tipo));
    }

    /** A frequência (QR Code) está aberta? Fecha ao ser encerrada pela organização ou ao chegar o horário de fechamento automático. */
    public function frequenciaAberta(): bool
    {
        return $this->frequencia_encerrada_em === null && !($this->frequencia_fecha_em && $this->frequencia_fecha_em->isPast());
    }

    /** Texto do estado da frequência para as telas: 'aberta', 'encerrada' (manual) ou 'encerrada_auto'. */
    public function estadoDaFrequencia(): string
    {
        if ($this->frequencia_encerrada_em !== null) {
            return 'encerrada';
        }

        return $this->frequencia_fecha_em && $this->frequencia_fecha_em->isPast() ? 'encerrada_auto' : 'aberta';
    }

    /** Só gera certificado de presença se a atividade estiver marcada para isso (jogos e atividades acadêmicas usam apenas a frequência). */
    public function emiteCertificado(): bool
    {
        return (bool) ($this->gera_certificado ?? true);
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function frequencias(): HasMany
    {
        return $this->hasMany(Frequencia::class);
    }

    public function inscritos(): BelongsToMany
    {
        return $this->belongsToMany(Participante::class, 'atividade_inscricoes');
    }
}
