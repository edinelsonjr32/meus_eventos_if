<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Jobs\GerarAnaisPdf;
use App\Models\Anais;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Services\AnaisPdfService;
use App\Services\TrabalhoWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Montagem e publicação dos Anais (gestor de trabalhos, dono e co-administradores). */
class AnaisGestaoController extends Controller
{
    public function __construct(private AnaisPdfService $pdf, private TrabalhoWorkflow $workflow)
    {
    }

    public function edit(Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $anais = $evento->anais ?? new Anais([
            'evento_id' => $evento->id, 'titulo' => "Anais do {$evento->titulo}", 'ano' => (int) $evento->data_inicio->format('Y'),
            'local_publicacao' => 'Óbidos', 'editora' => 'Instituto Federal do Pará - Campus Óbidos', 'status' => 'rascunho',
        ]);
        $anais->setRelation('evento', $evento);

        $elegiveis = Anais::elegiveis($evento)->with(['autores', 'area', 'arquivoAnais', 'arquivoAtual', 'evento.trabalhoConfig'])->orderBy('codigo')->get();
        $elegiveis->each(fn ($t) => $t->pdfPublico = $this->pdf->arquivoPublico($t));

        // Aprovados e apresentados que ficam de fora por falta de autorização dos autores
        $semAutorizacao = $evento->trabalhos()->where('status', 'aprovado')->where('apresentado', true)->where('autoriza_publicacao', false)->orderBy('codigo')->get();
        $aguardando = $evento->trabalhos()->where('status', 'aprovado')->where('apresentado', false)->count();

        return view('gestao.trabalhos.anais', compact('evento', 'anais', 'elegiveis', 'semAutorizacao', 'aguardando'));
    }

    public function update(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $dados = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'edicao' => ['nullable', 'string', 'max:255'],
            'issn' => ['nullable', 'regex:/^\d{4}-\d{3}[\dXx]$/'],
            'isbn' => ['nullable', 'regex:/^[\d-]{10,17}$/'],
            'doi_prefixo' => ['nullable', 'regex:/^10\.\d{4,9}$/'],
            'apresentacao' => ['nullable', 'string', 'max:10000'],
            'comissao' => ['nullable', 'string', 'max:10000'],
            'local_publicacao' => ['required', 'string', 'max:255'],
            'editora' => ['required', 'string', 'max:255'],
            'ano' => ['required', 'integer', 'min:2000', 'max:2100'],
        ], [
            'issn.regex' => 'ISSN inválido (formato 0000-0000).',
            'isbn.regex' => 'ISBN inválido (somente dígitos e hífens).',
            'doi_prefixo.regex' => 'Prefixo DOI inválido (ex.: 10.12345).',
        ]);

        $dados['issn'] = isset($dados['issn']) ? strtoupper($dados['issn']) : null;

        Anais::updateOrCreate(['evento_id' => $evento->id], $dados);

        return back()->with('success', 'Dados dos Anais salvos.');
    }

    public function gerar(Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $anais = $evento->anais;
        if (!$anais) {
            return back()->with('error', 'Salve os dados dos Anais antes de gerar o PDF.');
        }
        if ($anais->pdf_status === 'gerando') {
            return back()->with('error', 'O PDF já está sendo gerado.');
        }
        if (Anais::elegiveis($evento)->doesntExist()) {
            return back()->with('error', 'Não há trabalhos elegíveis (aprovados, apresentados e autorizados) para compilar.');
        }

        $anais->update(['pdf_status' => 'gerando', 'pdf_erro' => null]);
        GerarAnaisPdf::dispatch($anais);

        return back()->with('success', 'Geração do PDF iniciada. Atualize a página em instantes.');
    }

    public function pdf(Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $anais = $evento->anais;
        abort_unless($anais && $anais->pdfPronto() && Storage::disk('trabalhos')->exists($anais->pdf_path), 404);

        return Storage::disk('trabalhos')->response($anais->pdf_path, 'anais-' . Str::slug($evento->titulo) . '.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function publicar(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $anais = $evento->anais;
        if (!$anais) {
            return back()->with('error', 'Salve os dados dos Anais antes de publicar.');
        }

        $publicaveis = Anais::elegiveis($evento)->with(['arquivoAnais', 'arquivoAtual', 'evento.trabalhoConfig'])->get()
            ->filter(fn ($t) => $this->pdf->arquivoPublico($t) !== null);
        if ($publicaveis->isEmpty()) {
            return back()->with('error', 'Nenhum trabalho elegível possui PDF publicável. Em avaliação cega, envie o PDF final de cada trabalho.');
        }

        $anais->update(['status' => 'publicado', 'publicado_em' => now(), 'publicado_por' => $request->user()->id]);

        return back()->with('success', 'Anais publicados com ' . $publicaveis->count() . ' trabalho(s).');
    }

    public function despublicar(Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        abort_unless($evento->anais, 404);
        $evento->anais->update(['status' => 'rascunho']);

        return back()->with('success', 'Anais despublicados.');
    }

    public function arquivoFinal(Request $request, Trabalho $trabalho)
    {
        $this->authorize('gerirTrabalhos', $trabalho->evento);

        $limite = ($trabalho->evento->trabalhoConfig?->max_arquivo_mb ?? 20) * 1024;
        $request->validate(['arquivo' => ['required', 'file', 'mimes:pdf', 'max:' . $limite]], [
            'arquivo.required' => 'Selecione o PDF.', 'arquivo.mimes' => 'O arquivo deve ser um PDF.', 'arquivo.max' => 'O arquivo excede o tamanho máximo.',
        ]);

        $arquivo = $request->file('arquivo');
        if (@file_get_contents($arquivo->getRealPath(), false, null, 0, 5) !== '%PDF-') {
            return back()->with('error', 'O arquivo enviado não é um PDF válido.');
        }

        $this->workflow->anexarArquivoFinal($trabalho, $request->user(), $arquivo);

        return back()->with('success', 'PDF final dos Anais enviado.');
    }

    public function baixarFinal(Trabalho $trabalho)
    {
        $this->authorize('gerirTrabalhos', $trabalho->evento);

        $arquivo = $trabalho->arquivoAnais;
        abort_unless($arquivo && Storage::disk('trabalhos')->exists($arquivo->path), 404);

        return Storage::disk('trabalhos')->response($arquivo->path, "anais-{$trabalho->codigo}.pdf", [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
