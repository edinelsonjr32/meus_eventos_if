<?php

namespace App\Services;

use App\Models\Anais;
use App\Models\Trabalho;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Compila o PDF dos Anais: capa, expediente, sumário (com páginas) e os PDFs dos trabalhos,
 * agrupados por área. A união dos PDFs usa o Ghostscript com -dSAFER (arquivos enviados por
 * autores são tratados como não confiáveis).
 */
class AnaisPdfService
{
    private const DISCO = 'trabalhos';

    /** PDF que representa o trabalho nos Anais, ou null se não houver arquivo publicável. */
    public function arquivoPublico(Trabalho $trabalho)
    {
        if ($trabalho->arquivoAnais) {
            return $trabalho->arquivoAnais;
        }

        // Em avaliação cega o PDF enviado é anonimizado: sem versão final, não é publicado
        if ($trabalho->evento->trabalhoConfig?->avaliacao_cega) {
            return null;
        }

        return $trabalho->arquivoAtual;
    }

    /**
     * Trabalhos publicáveis, ordenados por área (ordem cadastrada; sem área por último) e título.
     *
     * @return Collection<int, Trabalho>
     */
    public function trabalhos(Anais $anais): Collection
    {
        return Anais::elegiveis($anais->evento)
            ->with(['autores', 'area', 'arquivoAnais', 'arquivoAtual', 'evento.trabalhoConfig'])
            ->get()
            ->sortBy(fn (Trabalho $t) => [$t->area?->ordem ?? PHP_INT_MAX, mb_strtolower($t->area?->nome ?? ''), mb_strtolower($t->titulo)])
            ->values();
    }

    /**
     * @throws \RuntimeException em falha de compilação
     */
    public function gerar(Anais $anais): Anais
    {
        $anais->loadMissing('evento');
        $disco = Storage::disk(self::DISCO);
        $pasta = "anais/{$anais->evento_id}";
        $disco->makeDirectory($pasta);

        $itens = collect();
        $avisos = [];

        foreach ($this->trabalhos($anais) as $trabalho) {
            $arquivo = $this->arquivoPublico($trabalho);
            if (!$arquivo || !$disco->exists($arquivo->path)) {
                $avisos[] = "{$trabalho->codigo}: sem PDF publicável (omitido).";
                continue;
            }

            $paginas = $this->paginas($disco->path($arquivo->path));
            if ($paginas === null) {
                $avisos[] = "{$trabalho->codigo}: PDF ilegível ou corrompido (omitido).";
                continue;
            }

            $itens->push(['trabalho' => $trabalho, 'caminho' => $disco->path($arquivo->path), 'paginas' => $paginas]);
        }

        if ($itens->isEmpty()) {
            throw new \RuntimeException('Nenhum trabalho com PDF publicável para compilar.');
        }

        $tmp = "{$pasta}/tmp-" . Str::random(10);
        $disco->makeDirectory($tmp);

        try {
            // Duas passagens: a primeira mede o total de páginas da parte inicial; a segunda numera o sumário
            $parte1 = $this->parteInicial($anais, $itens, 0);
            $frente = $this->paginas($this->gravar($disco, "{$tmp}/frente.pdf", $parte1)) ?? 1;
            $this->gravar($disco, "{$tmp}/frente.pdf", $this->parteInicial($anais, $itens, $frente));

            $saida = "{$pasta}/anais-" . now()->format('Ymd-His') . '-' . Str::random(6) . '.pdf';
            $this->unir(
                array_merge([$disco->path("{$tmp}/frente.pdf")], $itens->pluck('caminho')->all()),
                $disco->path($saida)
            );

            $total = $this->paginas($disco->path($saida));
            if ($total === null) {
                throw new \RuntimeException('O PDF compilado não pôde ser verificado.');
            }

            $anterior = $anais->pdf_path;
            $anais->update([
                'pdf_status' => 'pronto', 'pdf_erro' => null, 'pdf_aviso' => $avisos ? implode("\n", $avisos) : null,
                'pdf_path' => $saida, 'pdf_gerado_em' => now(), 'pdf_paginas' => $total, 'pdf_tamanho' => $disco->size($saida),
            ]);

            if ($anterior && $anterior !== $saida) {
                $disco->delete($anterior);
            }
        } finally {
            $disco->deleteDirectory($tmp);
        }

        return $anais->fresh();
    }

    /** Capa, expediente e sumário. `$paginasFrente` = páginas desta parte (para numerar o sumário). */
    private function parteInicial(Anais $anais, Collection $itens, int $paginasFrente): string
    {
        $pagina = $paginasFrente + 1;
        $grupos = [];

        foreach ($itens as $item) {
            /** @var Trabalho $t */
            $t = $item['trabalho'];
            $grupos[$t->area?->nome ?? 'Sem área temática'][] = [
                'titulo' => $t->titulo,
                'autores' => $t->autores->whereIn('tipo_autor', ['autor_principal', 'coautor'])->pluck('nome_completo')->implode(', '),
                'pagina' => $paginasFrente === 0 ? '—' : $pagina,
            ];
            $pagina += $item['paginas'];
        }

        return Pdf::loadView('anais.pdf-inicial', ['anais' => $anais, 'grupos' => $grupos, 'total' => $itens->count()])
            ->setPaper('a4', 'portrait')
            ->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'Times-Roman', 'isHtml5ParserEnabled' => true])
            ->output();
    }

    private function gravar($disco, string $caminho, string $conteudo): string
    {
        $disco->put($caminho, $conteudo);

        return $disco->path($caminho);
    }

    /** Número de páginas via Ghostscript (modo seguro); null se o PDF não for legível. */
    public function paginas(string $arquivo): ?int
    {
        $ps = '(' . addcslashes($arquivo, "()\\") . ') (r) file runpdfbegin pdfpagecount = quit';

        $r = Process::timeout(60)->run([
            config('trabalhos.ghostscript'), '-q', '-dNODISPLAY', '-dSAFER', '--permit-file-read=' . $arquivo, '-c', $ps,
        ]);

        $n = trim($r->output());

        return $r->successful() && ctype_digit($n) && (int) $n > 0 ? (int) $n : null;
    }

    /** @param string[] $entradas */
    private function unir(array $entradas, string $saida): void
    {
        $r = Process::timeout(config('trabalhos.anais_timeout'))->run(array_merge(
            [config('trabalhos.ghostscript'), '-q', '-dBATCH', '-dNOPAUSE', '-dSAFER', '-sDEVICE=pdfwrite', '-sOutputFile=' . $saida],
            $entradas
        ));

        if (!$r->successful() || !is_file($saida)) {
            throw new \RuntimeException('Falha ao unir os PDFs (Ghostscript): ' . Str::limit(trim($r->errorOutput() ?: $r->output()), 300));
        }
    }
}
