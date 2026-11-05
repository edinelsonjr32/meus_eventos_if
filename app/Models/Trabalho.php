<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trabalho extends Model
{
    use SoftDeletes;

    public const RASCUNHO = 'rascunho';
    public const SUBMETIDO = 'submetido';
    public const DEVOLVIDO_TRIAGEM = 'devolvido_triagem';
    public const AGUARDANDO_CORRECAO = 'aguardando_correcao';
    public const RETIRADO = 'retirado';

    public const STATUS = [
        self::RASCUNHO => 'Rascunho',
        self::SUBMETIDO => 'Submetido',
        self::DEVOLVIDO_TRIAGEM => 'Devolvido na triagem',
        self::AGUARDANDO_CORRECAO => 'Aguardando correção',
        self::RETIRADO => 'Retirado',
        'em_triagem' => 'Em triagem',
        'rejeitado_triagem' => 'Rejeitado na triagem',
        'em_avaliacao' => 'Em avaliação',
        'avaliado' => 'Avaliado',
        'aprovado' => 'Aprovado',
        'aprovado_com_ressalvas' => 'Aprovado com ressalvas',
        'reprovado' => 'Reprovado',
        'resubmetido' => 'Resubmetido',
    ];

    public const TIPOS_AUTOR = [
        'autor_principal' => 'Autor principal',
        'coautor' => 'Coautor',
        'orientador' => 'Orientador',
        'coorientador' => 'Coorientador',
    ];

    public const MODALIDADES = ['oral' => 'Apresentação oral', 'poster' => 'Pôster'];

    protected $fillable = [
        'evento_id', 'codigo', 'submetido_por', 'titulo', 'resumo', 'abstract', 'palavras_chave', 'tipo_trabalho',
        'area_tematica_id', 'modalidade_apresentacao', 'agencia_fomento', 'projeto_pesquisa', 'comite_etica',
        'status', 'nota_final', 'decisao', 'decisao_justificativa', 'decisao_em', 'decidido_por', 'prazo_correcao',
        'autoriza_publicacao', 'autorizou_em', 'submetido_em',
    ];

    protected $casts = [
        'palavras_chave' => 'array',
        'autoriza_publicacao' => 'boolean',
        'autorizou_em' => 'datetime',
        'submetido_em' => 'datetime',
        'decisao_em' => 'datetime',
        'prazo_correcao' => 'datetime',
        'nota_final' => 'float',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function submetente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submetido_por');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaTematica::class, 'area_tematica_id');
    }

    public function autores(): HasMany
    {
        return $this->hasMany(TrabalhoAutor::class)->orderBy('ordem');
    }

    public function arquivos(): HasMany
    {
        return $this->hasMany(TrabalhoArquivo::class)->orderByDesc('versao');
    }

    public function arquivoAtual(): HasOne
    {
        return $this->hasOne(TrabalhoArquivo::class)->where('ativo', true)->latestOfMany('versao');
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(TrabalhoAvaliacao::class)->orderBy('id');
    }

    public function historico(): HasMany
    {
        return $this->hasMany(TrabalhoHistorico::class)->orderBy('id');
    }

    /** Rodada de avaliação em andamento (1 até a primeira reavaliação). */
    public function rodadaAtual(): int
    {
        return max(1, (int) $this->avaliacoes()->max('rodada'));
    }

    /** O autor já pode ver o resultado? (somente após a decisão da gestão) */
    public function temDecisao(): bool
    {
        return $this->decisao !== null && in_array($this->status, ['aprovado', 'reprovado', 'aguardando_correcao', 'resubmetido'], true);
    }

    public function statusRotulo(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /** O autor ainda pode editar o conteúdo? */
    public function editavel(): bool
    {
        return in_array($this->status, [self::RASCUNHO, self::DEVOLVIDO_TRIAGEM, self::AGUARDANDO_CORRECAO], true);
    }
}
