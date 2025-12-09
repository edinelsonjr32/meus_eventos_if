<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Frequencia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificadoController extends Controller
{
    /**
     * Valida o certificado pelo hash (Página Web Pública)
     */
    public function validar($hash)
    {
        $frequencia = Frequencia::where('hash_validacao', $hash)
            ->with(['participante', 'atividade.evento'])
            ->first();

        if (!$frequencia) {
            return view('publico.validacao_erro');
        }

        return view('publico.validacao_sucesso', compact('frequencia'));
    }

    /**
     * Gera e baixa o PDF
     */
    public function download($hash)
    {
        // 1. Busca os dados completos
        $frequencia = Frequencia::where('hash_validacao', $hash)
            ->with(['participante', 'atividade.evento.assinaturas'])
            ->firstOrFail();

        $evento = $frequencia->atividade->evento;
        $atividade = $frequencia->atividade;
        $participante = $frequencia->participante;

        // 2. PREPARAÇÃO DOS TEXTOS DINÂMICOS
        // Define padrões caso o banco esteja vazio
        $textoCabecalho = $evento->cert_cabecalho ?? "Ministério da Educação\nSecretaria de Educação Profissional e Tecnológica\nInstituto Federal do Pará - Campus Óbidos";

        $textoCorpoRaw = $evento->cert_corpo ?? "Certificamos que <b>{PARTICIPANTE}</b>, inscrito(a) sob o CPF nº <b>{CPF}</b>, participou da atividade <b>{ATIVIDADE}</b>, integrante do evento <b>{EVENTO}</b>, realizado em {DATA_EVENTO}, atuando como <b>{TIPO_PARTICIPACAO}</b>, perfazendo uma carga horária total de <b>{CARGA_HORARIA} horas</b>.";

        $textoRodape = $evento->cert_rodape ?? "";

        // Dicionário de Substituição (De -> Para)
        $tags = [
            '{PARTICIPANTE}'    => mb_strtoupper($participante->nome_completo),
            '{CPF}'             => $participante->cpf,
            '{ATIVIDADE}'       => $atividade->titulo,
            '{EVENTO}'          => $evento->titulo,
            '{CARGA_HORARIA}'   => $atividade->carga_horaria,
            '{DATA_EVENTO}'     => $atividade->data_inicio->format('d/m/Y'),
            '{DATA_EMISSAO}'    => $frequencia->created_at->format('d/m/Y'),
            '{TIPO_PARTICIPACAO}' => mb_strtoupper($frequencia->tipo_participacao == 'ouvinte' ? 'participante' : $frequencia->tipo_participacao),
        ];

        // Realiza a troca das variáveis no texto
        $textoCorpoProcessado = str_replace(array_keys($tags), array_values($tags), $textoCorpoRaw);

        // 3. PREPARAÇÃO DAS IMAGENS (Base64 para evitar erros de caminho no PDF)
        $imgFundo = $evento->caminho_fundo ? $this->getImageBase64($evento->caminho_fundo) : null;
        $imgBrasao = $evento->caminho_brasao ? $this->getImageBase64($evento->caminho_brasao) : null;

        // Processa as assinaturas para injetar a imagem Base64 em cada uma
        $assinaturas = $evento->assinaturas->map(function($ass) {
            if ($ass->arquivo_assinatura) {
                $ass->img_base64 = $this->getImageBase64($ass->arquivo_assinatura);
            }
            return $ass;
        });

        // 4. GERAÇÃO DO PDF
        // Passamos TODAS as variáveis que a View espera
        $pdf = Pdf::loadView('certificados.modelo_oficial', [
            'frequencia' => $frequencia,
            'textoCabecalho' => $textoCabecalho,      // <--- A variável que faltava
            'textoCorpo' => $textoCorpoProcessado,    // <--- O texto já com nomes trocados
            'textoRodape' => $textoRodape,
            'imgFundo' => $imgFundo,
            'imgBrasao' => $imgBrasao,
            'assinaturas' => $assinaturas
        ])
        ->setPaper('a4', 'landscape')
        ->setOptions([
            'isRemoteEnabled' => true,
            'defaultFont' => 'Arial',
            'isHtml5ParserEnabled' => true
        ]);

        $nomeArquivo = 'Certificado_' . Str::slug($participante->nome_completo) . '.pdf';

        return $pdf->stream($nomeArquivo);
    }

    /**
     * Auxiliar: Converte arquivo do Storage para string Base64
     * Isso resolve problemas de imagem não carregando no DOMPDF
     */
    private function getImageBase64($caminhoRelativo)
    {
        $path = storage_path('app/public/' . $caminhoRelativo);

        if (file_exists($path)) {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        return null;
    }
}
