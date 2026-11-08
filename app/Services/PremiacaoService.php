<?php

namespace App\Services;

use App\Models\CertificadoPremiacao;
use App\Models\Evento;
use App\Models\Premiacao;
use App\Models\Trabalho;
use App\Models\TrabalhoAutor;
use App\Models\TrabalhoConfig;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Premiação: 1º, 2º e 3º lugar por categoria (tipo de trabalho aceito no evento), pela nota geral
 * (parte escrita + apresentação). O certificado já pode ser impresso pela gestão; o autor só o vê
 * em seu perfil depois que a gestão divulga a premiação.
 */
class PremiacaoService
{
    private const TAGS_PERMITIDAS = '<b><strong><i><em><u><br>';

    /** Categorias premiadas: os tipos de trabalho aceitos na chamada do evento (rótulo por chave). */
    public function categorias(Evento $evento): array
    {
        $aceitos = $evento->trabalhoConfig?->tipos_permitidos ?: array_keys(TrabalhoConfig::TIPOS);

        return collect($aceitos)->filter(fn ($t) => isset(TrabalhoConfig::TIPOS[$t]))->mapWithKeys(fn ($t) => [$t => TrabalhoConfig::TIPOS[$t]])->all();
    }

    /**
     * Trabalhos da categoria que podem ser premiados (aprovados nas duas partes, com nota geral), do melhor ao pior.
     * Desempate: nota do texto, nota da apresentação, protocolo (quem submeteu antes). "empate" marca nota geral igual à do vizinho.
     */
    public function ranking(Evento $evento, string $tipo): Collection
    {
        $evento->loadMissing('trabalhoConfig');
        $comApresentacao = (bool) $evento->trabalhoConfig?->avaliacao_apresentacao_ativa;

        $lista = $evento->trabalhos()->where('status', 'aprovado')->where('tipo_trabalho', $tipo)
            ->with(['autores', 'evento.trabalhoConfig', 'certificados', 'premiacao'])->get()
            ->filter(fn (Trabalho $t) => $t->aprovadoCompleto() && $t->nota_final !== null && (!$comApresentacao || $t->nota_apresentacao !== null))
            ->each(fn (Trabalho $t) => $t->nota_geral_calculada = $t->notaGeral())
            ->sort(function (Trabalho $a, Trabalho $b) {
                return [$b->nota_geral_calculada, $b->nota_final, $b->nota_apresentacao ?? 0, $a->codigo]
                    <=> [$a->nota_geral_calculada, $a->nota_final, $a->nota_apresentacao ?? 0, $b->codigo];
            })->values();

        return $lista->each(function (Trabalho $t, int $i) use ($lista) {
            $vizinhos = collect([$lista->get($i - 1), $lista->get($i + 1)])->filter();
            $t->empate = $vizinhos->contains(fn ($v) => $v->nota_geral_calculada === $t->nota_geral_calculada);
        });
    }

    /** Sugestão automática: os três primeiros do ranking de cada categoria. @return array<string,array<int,int>> tipo => [colocacao => trabalho_id] */
    public function sugestao(Evento $evento): array
    {
        $out = [];
        foreach (array_keys($this->categorias($evento)) as $tipo) {
            foreach ($this->ranking($evento, $tipo)->take(3)->values() as $i => $t) {
                $out[$tipo][$i + 1] = $t->id;
            }
        }

        return $out;
    }

    /** Premiações gravadas, indexadas por categoria e colocação. @return Collection<string,Collection<int,Premiacao>> */
    public function atuais(Evento $evento): Collection
    {
        return $evento->premiacoes()->with(['trabalho.autores', 'certificados.autor'])->get()->groupBy('tipo_trabalho')->map(fn ($g) => $g->keyBy('colocacao'));
    }

    /**
     * Grava as colocações escolhidas e emite os certificados dos autores premiados (prontos para impressão).
     * Colocações trocadas descartam os certificados antigos daquele lugar; as que não mudam são preservadas.
     *
     * @param  array<string,array<int,int|string|null>>  $escolhas  tipo => [colocacao => trabalho_id|null]
     * @throws \DomainException
     */
    public function salvar(Evento $evento, array $escolhas, ?User $gestor = null): Collection
    {
        $categorias = $this->categorias($evento);
        $novas = [];   // [tipo, colocacao, trabalho]

        foreach ($categorias as $tipo => $rotulo) {
            $usados = [];
            foreach ([1, 2, 3] as $col) {
                $id = $escolhas[$tipo][$col] ?? null;
                if (blank($id)) {
                    continue;
                }
                $trabalho = $this->ranking($evento, $tipo)->firstWhere('id', (int) $id);
                if (!$trabalho) {
                    throw new \DomainException("Trabalho inválido para {$col}º lugar em {$rotulo}: precisa ser da categoria, aprovado nas duas partes e com nota geral.");
                }
                if (in_array($trabalho->id, $usados, true)) {
                    throw new \DomainException("O mesmo trabalho não pode ocupar dois lugares em {$rotulo}.");
                }
                $usados[] = $trabalho->id;
                $novas[] = [$tipo, $col, $trabalho];
            }
        }

        DB::transaction(function () use ($evento, $novas, $gestor) {
            $existentes = $evento->premiacoes()->get();

            // remove o que saiu ou mudou (libera as chaves únicas antes de gravar)
            foreach ($existentes as $p) {
                $continua = collect($novas)->contains(fn ($n) => $n[0] === $p->tipo_trabalho && $n[1] === $p->colocacao && $n[2]->id === $p->trabalho_id);
                if (!$continua) {
                    $p->delete();
                }
            }

            foreach ($novas as [$tipo, $col, $trabalho]) {
                $atual = $evento->premiacoes()->where('tipo_trabalho', $tipo)->where('colocacao', $col)->first();
                $campos = ['nota_geral' => $trabalho->nota_geral_calculada ?? $trabalho->notaGeral(), 'nota_texto' => $trabalho->nota_final, 'nota_apresentacao' => $trabalho->nota_apresentacao, 'apurada_por' => $gestor?->id];
                $p = $atual ? tap($atual)->update($campos) : $evento->premiacoes()->create($campos + ['trabalho_id' => $trabalho->id, 'tipo_trabalho' => $tipo, 'colocacao' => $col]);
                $this->emitirCertificados($p, $gestor);
            }
        });

        return $this->atuais($evento)->flatten();
    }

    /** Aplica a sugestão automática (top 3 de cada categoria). */
    public function aplicarSugestao(Evento $evento, ?User $gestor = null): Collection
    {
        return $this->salvar($evento, $this->sugestao($evento), $gestor);
    }

    /** Emite o certificado de cada autor do trabalho premiado que ainda não tem (idempotente). */
    public function emitirCertificados(Premiacao $premiacao, ?User $gestor = null): Collection
    {
        $premiacao->loadMissing('trabalho.autores');
        $existentes = $premiacao->certificados()->pluck('trabalho_autor_id');

        return $premiacao->trabalho->autores->reject(fn ($a) => $existentes->contains($a->id))
            ->map(fn (TrabalhoAutor $a) => $premiacao->certificados()->create([
                'trabalho_autor_id' => $a->id, 'hash_validacao' => $this->novoHash(), 'emitido_em' => now(), 'emitido_por' => $gestor?->id,
            ]))->values();
    }

    public function divulgar(Evento $evento): void
    {
        $evento->trabalhoConfig->update(['premiacao_divulgada_em' => now()]);
    }

    public function ocultar(Evento $evento): void
    {
        $evento->trabalhoConfig->update(['premiacao_divulgada_em' => null]);
    }

    public function divulgada(Evento $evento): bool
    {
        return $evento->trabalhoConfig?->premiacao_divulgada_em !== null;
    }

    // ------------------------------------------------------------------ certificado (PDF)

    /** @return array{cabecalho:string,corpo:string,rodape:string} */
    public function textos(CertificadoPremiacao $certificado): array
    {
        $certificado->loadMissing(['premiacao.trabalho.autores', 'premiacao.trabalho.evento.trabalhoConfig', 'autor']);
        $premiacao = $certificado->premiacao;
        $trabalho = $premiacao->trabalho;
        $autor = $certificado->autor;
        $config = $trabalho->evento->trabalhoConfig;

        $coautores = $trabalho->autores->where('id', '!=', $autor->id)->pluck('nome_completo');
        $nota = $premiacao->nota_geral !== null ? number_format($premiacao->nota_geral, 2, ',', '.') : '—';

        $valores = [
            '{AUTOR}' => e(mb_strtoupper($autor->nome_completo)),
            '{CPF_TRECHO}' => filled($autor->cpf) ? ', CPF ' . e($autor->cpf) . ',' : '',
            '{COLOCACAO}' => e($premiacao->colocacaoRotulo()),
            '{CATEGORIA}' => e(mb_strtolower($premiacao->categoriaRotulo())),
            '{TITULO_TRABALHO}' => e($trabalho->titulo),
            '{CODIGO_TRABALHO}' => e($trabalho->codigo),
            '{EVENTO}' => e($trabalho->evento->titulo),
            '{NOTA_GERAL}' => e($nota),
            '{COAUTORES}' => $coautores->isEmpty() ? '' : ' Coautoria: ' . e($coautores->implode(', ')) . '.',
            '{INSTITUICAO}' => e((string) $autor->instituicao),
        ];

        $corpo = $config?->cert_premio_corpo ?: TrabalhoConfig::CERT_PREMIO_CORPO_PADRAO;

        return [
            'cabecalho' => e($config?->cert_premio_cabecalho ?: ($config?->cert_trab_cabecalho ?: TrabalhoConfig::CERT_CABECALHO_PADRAO)),
            'corpo' => str_replace(array_keys($valores), array_values($valores), strip_tags($corpo, self::TAGS_PERMITIDAS)),
            'rodape' => e((string) ($config?->cert_premio_rodape ?: $config?->cert_trab_rodape)),
        ];
    }

    /** PDF (uma página por certificado) para impressão: aceita um ou vários certificados do mesmo evento. */
    public function gerarPdf(Collection $certificados)
    {
        // (aceita coleção simples; o carregamento de relações exige a coleção do Eloquent)
        $certificados = \Illuminate\Database\Eloquent\Collection::make($certificados->all())->load(['premiacao.trabalho.evento.assinaturas', 'premiacao.trabalho.evento.trabalhoConfig', 'autor']);
        $evento = $certificados->first()->premiacao->trabalho->evento;

        $assinaturas = $evento->assinaturas->map(function ($ass) {
            $ass->img_base64 = $ass->arquivo_assinatura ? $this->imagemBase64($ass->arquivo_assinatura) : null;

            return $ass;
        });

        $itens = $certificados->map(function (CertificadoPremiacao $c) {
            $url = route('certificado.premiacao.validar', $c->hash_validacao);

            return [
                'certificado' => $c, 'textos' => $this->textos($c), 'urlValidacao' => $url,
                'qrCode' => 'data:image/svg+xml;base64,' . base64_encode((string) QrCode::format('svg')->size(150)->margin(0)->generate($url)),
            ];
        });

        return Pdf::loadView('certificados.premiacao', [
            'itens' => $itens,
            'imgFundo' => $evento->caminho_fundo ? $this->imagemBase64($evento->caminho_fundo) : null,
            'imgBrasao' => $evento->caminho_brasao ? $this->imagemBase64($evento->caminho_brasao) : null,
            'assinaturas' => $assinaturas,
        ])->setPaper('a4', 'landscape')->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'Arial', 'isHtml5ParserEnabled' => true]);
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
            $hash = 'P' . strtoupper(Str::random(19));
        } while (CertificadoPremiacao::where('hash_validacao', $hash)->exists());

        return $hash;
    }
}
