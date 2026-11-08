<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrabalhoConfig extends Model
{
    /** Categorias do trabalho (mesmas opções do formulário utilizado na Semana de Ciência e Tecnologia). */
    public const TIPOS = [
        'pesquisa' => 'Pesquisa',
        'extensao' => 'Extensão',
        'projeto_integrador' => 'Projeto Integrador (alunos do IFPA)',
        'relato_experiencia' => 'Relato de Experiência',
    ];

    public const CERT_CABECALHO_PADRAO = "Ministério da Educação\nInstituto Federal do Pará - Campus Óbidos";

    public const CERT_CORPO_PADRAO = 'Certificamos que <b>{AUTOR}</b>, CPF {CPF}, participou como <b>{TIPO_AUTOR}</b> do trabalho "<b>{TITULO_TRABALHO}</b>" (categoria: {TIPO_TRABALHO}, código {CODIGO_TRABALHO}), apresentado no evento <b>{EVENTO}</b>, em {DATA_APRESENTACAO}.{COAUTORES}';

    public const CERT_VARIAVEIS = [
        '{AUTOR}', '{CPF}', '{TIPO_AUTOR}', '{TITULO_TRABALHO}', '{TIPO_TRABALHO}', '{AREA}', '{COAUTORES}',
        '{EVENTO}', '{DATA_APRESENTACAO}', '{CODIGO_TRABALHO}', '{CATEGORIA}', '{INSTITUICAO}',
    ];

    protected $fillable = [
        'evento_id', 'ativo', 'submissao_inicio', 'submissao_fim', 'avaliacao_fim', 'avaliadores_por_trabalho', 'nota_minima_aprovacao', 'divergencia_maxima', 'autor_ve_notas', 'autor_ve_pareceres', 'exige_apresentacao_para_certificado', 'cert_trab_cabecalho', 'cert_trab_corpo', 'cert_trab_rodape', 'max_autores', 'max_arquivo_mb',
        'resumo_min_palavras', 'resumo_max_palavras', 'max_trabalhos_por_usuario', 'avaliacao_cega',
        'tipos_permitidos', 'regras_texto', 'chamada_tema', 'chamada_intro', 'template_url', 'exige_resumo',
        'exige_palavras_chave', 'exige_cpf_apresentador',
        'avaliacao_apresentacao_ativa', 'peso_apresentacao', 'autor_ve_nota_apresentacao',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'avaliacao_apresentacao_ativa' => 'boolean',
        'autor_ve_nota_apresentacao' => 'boolean',
        'avaliacao_cega' => 'boolean',
        'autor_ve_notas' => 'boolean',
        'autor_ve_pareceres' => 'boolean',
        'exige_apresentacao_para_certificado' => 'boolean',
        'nota_minima_aprovacao' => 'float',
        'divergencia_maxima' => 'float',
        'submissao_inicio' => 'datetime',
        'submissao_fim' => 'datetime',
        'avaliacao_fim' => 'datetime',
        'tipos_permitidos' => 'array',
        'exige_resumo' => 'boolean',
        'exige_palavras_chave' => 'boolean',
        'exige_cpf_apresentador' => 'boolean',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    /**
     * Situação da chamada de trabalhos de um evento: nao_criada | rascunho | em_breve | aberta | encerrada.
     * Aceita null (evento ainda sem chamada).
     *
     * @return array{chave:string,rotulo:string,cor:string,descricao:string}
     */
    public static function situacaoDe(?self $config): array
    {
        if (!$config) {
            return ['chave' => 'nao_criada', 'rotulo' => 'Chamada não criada', 'cor' => 'secondary',
                'descricao' => 'Este evento ainda não recebe trabalhos. Crie a chamada para abrir as submissões.'];
        }
        if (!$config->ativo) {
            return ['chave' => 'rascunho', 'rotulo' => 'Rascunho (não publicada)', 'cor' => 'warning',
                'descricao' => 'A chamada foi configurada, mas ainda não está visível ao público. Publique para abrir as submissões.'];
        }
        if ($config->submissao_inicio && now()->lt($config->submissao_inicio)) {
            return ['chave' => 'em_breve', 'rotulo' => 'Publicada — abre em ' . $config->submissao_inicio->format('d/m/Y H:i'), 'cor' => 'info',
                'descricao' => 'Publicada, mas o período de submissão ainda não começou.'];
        }
        if ($config->submissao_fim && now()->gt($config->submissao_fim)) {
            return ['chave' => 'encerrada', 'rotulo' => 'Submissões encerradas', 'cor' => 'dark',
                'descricao' => 'O prazo de submissão terminou em ' . $config->submissao_fim->format('d/m/Y H:i') . '.'];
        }

        return ['chave' => 'aberta', 'rotulo' => 'Chamada aberta', 'cor' => 'success',
            'descricao' => 'Os autores já podem submeter trabalhos' . ($config->submissao_fim ? ' até ' . $config->submissao_fim->format('d/m/Y H:i') : '') . '.'];
    }

    /** Chamada ativa e dentro do período de submissão. */
    public function estaAberta(): bool
    {
        if (!$this->ativo) {
            return false;
        }

        $agora = now();

        return (!$this->submissao_inicio || $agora->gte($this->submissao_inicio))
            && (!$this->submissao_fim || $agora->lte($this->submissao_fim));
    }

    /** @return array<string,string> tipos habilitados (chave => rótulo) */
    public function tiposHabilitados(): array
    {
        $chaves = $this->tipos_permitidos ?: array_keys(self::TIPOS);

        return array_intersect_key(self::TIPOS, array_flip($chaves));
    }
}
