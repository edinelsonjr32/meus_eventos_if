<?php

namespace App\Services;

use App\Models\Equipe;
use App\Models\Evento;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Certificado da equipe de organização: um por membro, com a função e a carga horária do vínculo. */
class CertificadoEquipeService
{
    public const CORPO_PADRAO = 'Certificamos que <b>{PARTICIPANTE}</b>, inscrito(a) sob o CPF nº <b>{CPF}</b>, atuou como <b>{FUNCAO}</b> na equipe de organização do evento <b>{EVENTO}</b>, realizado em {DATA_EVENTO}, perfazendo uma carga horária total de <b>{CARGA_HORARIA} horas</b>.';

    private const TAGS_PERMITIDAS = '<b><strong><i><em><u><br>';

    /** Emite (idempotente) o certificado do membro; mantém o código se já foi emitido. */
    public function emitir(Equipe $membro, ?User $gestor = null): bool
    {
        if ($membro->certificadoEmitido()) {
            return false;
        }
        if ((int) $membro->carga_horaria_trabalho < 1) {
            throw new \DomainException('Informe a carga horária do membro antes de emitir o certificado.');
        }

        $membro->update(['hash_validacao' => $this->novoHash(), 'certificado_emitido_em' => now(), 'certificado_emitido_por' => $gestor?->id]);

        return true;
    }

    /** Emite para toda a equipe do evento; devolve [emitidos, ignorados(mensagens)]. */
    public function emitirEvento(Evento $evento, ?User $gestor = null): array
    {
        $emitidos = 0;
        $ignorados = [];
        foreach ($evento->equipe()->with('participante')->get() as $membro) {
            try {
                $emitidos += $this->emitir($membro, $gestor) ? 1 : 0;
            } catch (\DomainException $e) {
                $ignorados[] = $membro->participante->nome_completo . ': ' . $e->getMessage();
            }
        }

        return [$emitidos, $ignorados];
    }

    /** Revoga o certificado (o código deixa de validar); pode ser reemitido depois com novo código. */
    public function revogar(Equipe $membro): void
    {
        $membro->update(['hash_validacao' => null, 'certificado_emitido_em' => null, 'certificado_emitido_por' => null]);
    }

    /** @return array{cabecalho:string,corpo:string,rodape:string} */
    public function textos(Equipe $membro): array
    {
        $membro->loadMissing(['participante', 'evento']);
        $evento = $membro->evento;
        $p = $membro->participante;

        $valores = [
            '{PARTICIPANTE}' => e(mb_strtoupper($p->nome_completo)),
            '{CPF}' => e((string) $p->cpf),
            '{FUNCAO}' => e(mb_strtolower($membro->funcao)),
            '{EVENTO}' => e($evento->titulo),
            '{DATA_EVENTO}' => e($evento->data_inicio?->format('d/m/Y') ?? ''),
            '{CARGA_HORARIA}' => e((string) $membro->carga_horaria_trabalho),
            '{DATA_EMISSAO}' => e(($membro->certificado_emitido_em ?? now())->format('d/m/Y')),
        ];

        return [
            'cabecalho' => $evento->cert_cabecalho ?: "Ministério da Educação\nSecretaria de Educação Profissional e Tecnológica\nInstituto Federal do Pará - Campus Óbidos",
            'corpo' => str_replace(array_keys($valores), array_values($valores), strip_tags($evento->cert_equipe_corpo ?: self::CORPO_PADRAO, self::TAGS_PERMITIDAS)),
            'rodape' => (string) $evento->cert_rodape,
        ];
    }

    public function gerarPdf(Equipe $membro)
    {
        $membro->loadMissing(['participante', 'evento.assinaturas']);
        $evento = $membro->evento;
        $textos = $this->textos($membro);

        $assinaturas = $evento->assinaturas->map(function ($ass) {
            $ass->img_base64 = $ass->arquivo_assinatura ? $this->imagemBase64($ass->arquivo_assinatura) : null;

            return $ass;
        });

        return Pdf::loadView('certificados.modelo_oficial', [
            'textoCabecalho' => $textos['cabecalho'],
            'textoCorpo' => $textos['corpo'],
            'textoRodape' => $textos['rodape'],
            'imgFundo' => $evento->caminho_fundo ? $this->imagemBase64($evento->caminho_fundo) : null,
            'imgBrasao' => $evento->caminho_brasao ? $this->imagemBase64($evento->caminho_brasao) : null,
            'assinaturas' => $assinaturas,
            'hashCert' => $membro->hash_validacao,
            'urlValidacao' => route('certificado.equipe.validar', $membro->hash_validacao),
            'emitidoEm' => $membro->certificado_emitido_em,
        ])->setPaper('a4', 'landscape')->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'Arial', 'isHtml5ParserEnabled' => true]);
    }

    public function nomeArquivo(Equipe $membro): string
    {
        return 'Certificado-equipe_' . Str::slug($membro->participante->nome_completo) . '.pdf';
    }

    private function imagemBase64(string $caminhoRelativo): ?string
    {
        $path = storage_path('app/public/' . $caminhoRelativo);
        if (!is_file($path)) {
            return null;
        }
        $tipo = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return 'data:image/' . ($tipo === 'jpg' ? 'jpeg' : $tipo) . ';base64,' . base64_encode(file_get_contents($path));
    }

    private function novoHash(): string
    {
        do {
            $hash = 'E' . strtoupper(Str::random(19));
        } while (Equipe::where('hash_validacao', $hash)->exists());

        return $hash;
    }
}
