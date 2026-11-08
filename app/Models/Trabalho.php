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

    /** Rótulos extras de exibição (não são status gravados): ver statusFinal(). */
    public const STATUS_FINAL_EXTRA = [
        'aprovado_escrita' => 'Aprovado na parte escrita',
        'aprovado_apresentacao' => 'Aprovado na apresentação',
        'aguardando_certificado' => 'Aguardando emissão de certificado',
        'concluido' => 'Concluído (certificados emitidos)',
    ];

    public const TIPOS_AUTOR = [
        'autor_principal' => 'Autor principal',
        'coautor' => 'Coautor',
        'orientador' => 'Orientador',
        'coorientador' => 'Coorientador',
    ];

    public const MODALIDADES = ['poster' => 'Pôster', 'oral' => 'Apresentação oral/remoto'];

    /** Texto completo exibido no formulário (igual ao do formulário original). */
    public const MODALIDADES_DESCRICAO = [
        'poster' => 'Pôster',
        'oral' => 'Apresentação oral/remoto (para autores externos na impossibilidade de comparecer no evento)',
    ];

    public const CATEGORIAS_AUTOR = ['docente' => 'Docente', 'discente' => 'Discente'];

    protected $fillable = [
        'evento_id', 'codigo', 'submetido_por', 'titulo', 'resumo', 'abstract', 'palavras_chave', 'tipo_trabalho',
        'area_tematica_id', 'atividade_id', 'modalidade_apresentacao', 'agencia_fomento', 'projeto_pesquisa', 'comite_etica',
        'status', 'nota_final', 'nota_apresentacao', 'resultado_apresentacao', 'decisao', 'decisao_justificativa', 'decisao_em', 'decidido_por', 'prazo_correcao',
        'apresentado', 'apresentado_em', 'apresentacao_origem',
        'autoriza_publicacao', 'autorizou_em', 'autorizacao_solicitada_em', 'submetido_em',
    ];

    protected $casts = [
        'palavras_chave' => 'array',
        'nota_apresentacao' => 'float',
        'autoriza_publicacao' => 'boolean',
        'autorizou_em' => 'datetime',
        'autorizacao_solicitada_em' => 'datetime',
        'submetido_em' => 'datetime',
        'decisao_em' => 'datetime',
        'prazo_correcao' => 'datetime',
        'apresentado' => 'boolean',
        'apresentado_em' => 'datetime',
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

    /** Sessão (atividade) em que o trabalho é apresentado. */
    public function sessao(): BelongsTo
    {
        return $this->belongsTo(Atividade::class, 'atividade_id');
    }

    public function premiacao(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Premiacao::class);
    }

    public function certificados(): HasMany
    {
        return $this->hasMany(CertificadoTrabalho::class);
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
        // O filtro vai DENTRO da subconsulta: senão um arquivo inativo de versão maior (ex.: PDF final dos Anais) esconderia o ativo
        return $this->hasOne(TrabalhoArquivo::class)->ofMany(['versao' => 'max'], fn ($q) => $q->where('ativo', true));
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(TrabalhoAvaliacao::class)->orderBy('id');
    }

    /**
     * Situação para exibição. Regra de negócio: o trabalho só é "Aprovado" (100%) se foi aprovado na parte escrita
     * E na apresentação. Aprovado só no texto (aguardando ou reprovado na apresentação) = "Aprovado na parte escrita".
     * Na prática só se apresenta quem foi aprovado no texto, então "aprovado_apresentacao" (sem o texto) não ocorre;
     * a chave existe apenas por completude. Sem avaliação de apresentação ativa no evento, vale a aprovação do texto.
     */
    public function statusFinal(): string
    {
        $cfg = $this->evento->trabalhoConfig;
        $temCertificado = $this->relationLoaded('certificados')
            ? $this->certificados->whereNull('revogado_em')->isNotEmpty()
            : $this->certificados()->whereNull('revogado_em')->exists();

        return self::situacaoDe(
            $this->status, (bool) $this->apresentado, $this->resultado_apresentacao,
            (bool) $cfg?->avaliacao_apresentacao_ativa, (bool) ($cfg?->exige_apresentacao_para_certificado ?? true), $temCertificado
        );
    }

    /**
     * Situação de exibição a partir dos dados básicos (usada também para contadores e filtros sem carregar modelos):
     *   aprovado_escrita → aprovado só no texto (apresentação pendente/reprovada)
     *   aprovado → aprovado nas duas partes, ainda sem a apresentação confirmada
     *   aguardando_certificado → aprovado nas duas partes e apresentado: pronto para emitir os certificados (em massa)
     *   concluido → certificados emitidos: último passo do fluxo
     * Os demais status são devolvidos como estão.
     */
    public static function situacaoDe(string $status, bool $apresentado, ?string $resultadoApresentacao, bool $apresentacaoAtiva, bool $exigeApresentacao, bool $temCertificado): string
    {
        if ($status !== 'aprovado') {
            return $status;
        }
        if ($apresentacaoAtiva && $resultadoApresentacao !== 'aprovado') {
            return 'aprovado_escrita';
        }
        if ($temCertificado) {
            return 'concluido';
        }

        return (!$exigeApresentacao || $apresentado) ? 'aguardando_certificado' : 'aprovado';
    }

    public function statusFinalRotulo(): string
    {
        return self::STATUS[$this->statusFinal()] ?? self::STATUS_FINAL_EXTRA[$this->statusFinal()] ?? $this->statusFinal();
    }

    /** Complemento do selo "Aprovado na parte escrita": diz o que falta, sem afirmar que não houve apresentação quando ela já ocorreu. */
    public function extraSituacao(): ?string
    {
        if ($this->statusFinal() === 'aprovado' && !$this->apresentado) {
            return 'aguardando apresentação';
        }
        if ($this->statusFinal() !== 'aprovado_escrita') {
            return null;
        }
        if ($this->resultado_apresentacao === 'reprovado') {
            return 'apresentação não aprovada';
        }

        return $this->apresentado ? 'aguardando resultado da apresentação' : 'aguardando apresentação';
    }

    /** Aprovado nas duas partes (ou apenas no texto, quando o evento não avalia apresentações). */
    public function aprovadoCompleto(): bool
    {
        return in_array($this->statusFinal(), ['aprovado', 'aguardando_certificado', 'concluido'], true);
    }

    /** Avaliações da apresentação (feira de trabalhos), feitas por avaliadores após a aprovação. */
    public function avaliacoesApresentacao(): HasMany
    {
        return $this->hasMany(ApresentacaoAvaliacao::class)->orderBy('id');
    }

    /**
     * Nota geral: média ponderada entre a nota do trabalho e a da apresentação (peso configurável na chamada).
     * Sem nota de apresentação, vale a nota do trabalho.
     */
    public function notaGeral(): ?float
    {
        if ($this->nota_final === null) {
            return null;
        }
        $config = $this->evento->trabalhoConfig;
        if ($this->nota_apresentacao === null || !($config?->avaliacao_apresentacao_ativa)) {
            return (float) $this->nota_final;
        }
        $p = max(0, min(100, (int) $config->peso_apresentacao)) / 100;

        return round($this->nota_final * (1 - $p) + $this->nota_apresentacao * $p, 2);
    }

    /** PDF final preparado pela gestão para os Anais (por exemplo, versão com identificação dos autores). */
    public function arquivoAnais(): HasOne
    {
        return $this->hasOne(TrabalhoArquivo::class)->ofMany(['versao' => 'max'], fn ($q) => $q->where('tipo', 'final_anais'));
    }

    public function historico(): HasMany
    {
        return $this->hasMany(TrabalhoHistorico::class)->orderBy('id');
    }

    /** O usuário é o responsável pela submissão ou consta entre os autores (por e-mail, CPF ou cadastro)? */
    public function ehAutor(User $usuario): bool
    {
        return $this->submetido_por === $usuario->id
            || $this->autores->contains(fn ($a) => $a->pertenceA($usuario));
    }

    /** Trabalhos em que o usuário é o responsável pela submissão ou consta como autor. */
    public function scopeDoAutor($query, User $usuario)
    {
        $participante = $usuario->participante;

        return $query->where(function ($q) use ($usuario, $participante) {
            $q->where('submetido_por', $usuario->id)
                ->orWhereHas('autores', function ($a) use ($usuario, $participante) {
                    $a->where('email', mb_strtolower($usuario->email));
                    if ($participante) {
                        $a->orWhere('participante_id', $participante->id)->orWhere('cpf', $participante->cpf);
                    }
                });
        });
    }

    /** Rodada de avaliação em andamento (1 até a primeira reavaliação). */
    public function rodadaAtual(): int
    {
        // Listas carregam o máximo de uma vez (withMax) para evitar uma consulta por trabalho
        $carregada = $this->attributes['avaliacoes_max_rodada'] ?? null;

        return max(1, (int) ($carregada ?? $this->avaliacoes()->max('rodada')));
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
