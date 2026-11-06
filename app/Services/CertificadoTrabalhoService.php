<?php

namespace App\Services;

use App\Mail\CertificadoTrabalhoMail;
use App\Models\CertificadoTrabalho;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\TrabalhoAutor;
use App\Models\TrabalhoConfig;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Certificados dos trabalhos: um por autor, somente para trabalhos aprovados (e apresentados,
 * quando o evento exige). Todo dado variável é escapado antes de entrar no HTML do PDF.
 */
class CertificadoTrabalhoService
{
    /** Tags permitidas no corpo do certificado (formatação básica). */
    private const TAGS_PERMITIDAS = '<b><strong><i><em><u><br>';

    public function __construct(private TrabalhoWorkflow $workflow)
    {
    }

    /** Motivo pelo qual o trabalho ainda não pode receber certificados, ou null se está elegível. */
    public function motivoInelegivel(Trabalho $trabalho): ?string
    {
        if ($trabalho->status !== 'aprovado') {
            return 'trabalho não aprovado';
        }

        $exige = $trabalho->evento->trabalhoConfig?->exige_apresentacao_para_certificado ?? true;
        if ($exige && !$trabalho->apresentado) {
            return 'apresentação ainda não confirmada';
        }

        return null;
    }

    /**
     * Emite os certificados que faltam (idempotente) e avisa cada autor por e-mail.
     *
     * @return Collection<int, CertificadoTrabalho> certificados novos
     * @throws \DomainException
     */
    public function emitir(Trabalho $trabalho, ?User $gestor = null): Collection
    {
        if ($motivo = $this->motivoInelegivel($trabalho)) {
            throw new \DomainException("{$trabalho->codigo}: {$motivo}.");
        }

        $trabalho->loadMissing(['autores', 'evento']);

        $novos = DB::transaction(function () use ($trabalho, $gestor) {
            $existentes = $trabalho->certificados()->pluck('trabalho_autor_id');

            $novos = $trabalho->autores->reject(fn ($a) => $existentes->contains($a->id))
                ->map(fn (TrabalhoAutor $autor) => $trabalho->certificados()->create([
                    'trabalho_autor_id' => $autor->id,
                    'hash_validacao' => $this->novoHash(),
                    'tipo' => $autor->tipo_autor,
                    'apresentador' => $autor->apresentador,
                    'emitido_em' => now(),
                    'emitido_por' => $gestor?->id,
                ]))->values();

            if ($novos->isNotEmpty()) {
                $this->workflow->registrar($trabalho, $gestor, $trabalho->status, $trabalho->status, "Certificados emitidos ({$novos->count()})");
            }

            return $novos;
        });

        foreach ($novos as $certificado) {
            $certificado->setRelation('trabalho', $trabalho);
            Mail::to($certificado->autor->email)->queue(new CertificadoTrabalhoMail($certificado));
        }

        return $novos;
    }

    /** Emite para todos os trabalhos elegíveis do evento; informa os que foram ignorados. */
    public function emitirEvento(Evento $evento, ?User $gestor = null): array
    {
        $emitidos = 0;
        $ignorados = [];

        $evento->trabalhos()->where('status', 'aprovado')->with(['autores', 'evento.trabalhoConfig', 'certificados'])->get()
            ->each(function (Trabalho $t) use ($gestor, &$emitidos, &$ignorados) {
                try {
                    $emitidos += $this->emitir($t, $gestor)->count();
                } catch (\DomainException $e) {
                    $ignorados[] = $e->getMessage();
                }
            });

        return [$emitidos, $ignorados];
    }

    public function revogar(CertificadoTrabalho $certificado, User $gestor, string $motivo): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new \DomainException('Informe o motivo da revogação.');
        }
        if ($certificado->revogado()) {
            throw new \DomainException('Este certificado já foi revogado.');
        }

        $certificado->update(['revogado_em' => now(), 'revogado_motivo' => Str::limit($motivo, 250, '')]);
        $trabalho = $certificado->trabalho;
        $this->workflow->registrar($trabalho, $gestor, $trabalho->status, $trabalho->status, "Certificado revogado ({$certificado->autor->nome_completo}): {$motivo}");
    }

    // ------------------------------------------------------------------
    // Renderização
    // ------------------------------------------------------------------

    /** @return array{cabecalho:string,corpo:string,rodape:string} textos prontos (HTML seguro). */
    public function textos(CertificadoTrabalho $certificado): array
    {
        $certificado->loadMissing(['trabalho.evento.trabalhoConfig', 'trabalho.area', 'trabalho.sessao', 'trabalho.autores', 'autor']);
        $trabalho = $certificado->trabalho;
        $autor = $certificado->autor;
        $config = $trabalho->evento->trabalhoConfig;

        $coautores = $trabalho->autores->where('id', '!=', $autor->id)->pluck('nome_completo');
        $dataApresentacao = ($trabalho->apresentado_em ?? $trabalho->sessao?->data_inicio ?? $certificado->emitido_em)->format('d/m/Y');

        // Valores variáveis SEMPRE escapados; o modelo (escrito pela gestão) só aceita tags básicas
        $valores = [
            '{AUTOR}' => e(mb_strtoupper($autor->nome_completo)),
            '{CPF}' => e($autor->cpf),
            '{TIPO_AUTOR}' => e(mb_strtolower($certificado->funcaoRotulo())),
            '{TITULO_TRABALHO}' => e($trabalho->titulo),
            '{TIPO_TRABALHO}' => e(mb_strtolower(TrabalhoConfig::TIPOS[$trabalho->tipo_trabalho] ?? 'trabalho')),
            '{AREA}' => e($trabalho->area->nome ?? ''),
            '{COAUTORES}' => $coautores->isEmpty() ? '' : ' Coautoria: ' . e($coautores->implode(', ')) . '.',
            '{EVENTO}' => e($trabalho->evento->titulo),
            '{DATA_APRESENTACAO}' => e($dataApresentacao),
            '{CODIGO_TRABALHO}' => e($trabalho->codigo),
        ];

        $corpo = $config?->cert_trab_corpo ?: TrabalhoConfig::CERT_CORPO_PADRAO;

        return [
            'cabecalho' => e($config?->cert_trab_cabecalho ?: TrabalhoConfig::CERT_CABECALHO_PADRAO),
            'corpo' => str_replace(array_keys($valores), array_values($valores), strip_tags($corpo, self::TAGS_PERMITIDAS)),
            'rodape' => e((string) $config?->cert_trab_rodape),
        ];
    }

    public function gerarPdf(CertificadoTrabalho $certificado)
    {
        $certificado->loadMissing(['trabalho.evento.assinaturas', 'autor']);
        $evento = $certificado->trabalho->evento;

        $assinaturas = $evento->assinaturas->map(function ($ass) {
            $ass->img_base64 = $ass->arquivo_assinatura ? $this->imagemBase64($ass->arquivo_assinatura) : null;

            return $ass;
        });

        $url = route('certificado.trabalho.validar', $certificado->hash_validacao);

        return Pdf::loadView('certificados.trabalho', [
            'certificado' => $certificado,
            'textos' => $this->textos($certificado),
            'imgFundo' => $evento->caminho_fundo ? $this->imagemBase64($evento->caminho_fundo) : null,
            'imgBrasao' => $evento->caminho_brasao ? $this->imagemBase64($evento->caminho_brasao) : null,
            'assinaturas' => $assinaturas,
            'urlValidacao' => $url,
            // QR gerado localmente (sem enviar a URL a serviços externos)
            'qrCode' => 'data:image/svg+xml;base64,' . base64_encode((string) QrCode::format('svg')->size(150)->margin(0)->generate($url)),
        ])
            ->setPaper('a4', 'landscape')
            // Nunca buscar recursos remotos durante a renderização
            ->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'Arial', 'isHtml5ParserEnabled' => true]);
    }

    private function imagemBase64(string $caminhoRelativo): ?string
    {
        $path = storage_path('app/public/' . $caminhoRelativo);
        if (!is_file($path)) {
            return null;
        }

        $tipo = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $tipo = $tipo === 'jpg' ? 'jpeg' : $tipo;

        return "data:image/{$tipo};base64," . base64_encode(file_get_contents($path));
    }

    private function novoHash(): string
    {
        do {
            $hash = strtoupper(Str::random(20));
        } while (CertificadoTrabalho::where('hash_validacao', $hash)->exists());

        return $hash;
    }
}
