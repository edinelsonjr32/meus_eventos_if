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

        // Aprovados (nas duas partes) e apresentados que ficam de fora dos Anais, com o motivo
        $foraDosAnais = $this->foraDosAnais($evento);
        $semAutorizacao = $foraDosAnais->filter(fn ($t) => $t->motivoAnais === 'autorizacao');
        $aguardando = $evento->trabalhos()->where('status', 'aprovado')->where('apresentado', false)->count();

        return view('gestao.trabalhos.anais', compact('evento', 'anais', 'elegiveis', 'semAutorizacao', 'foraDosAnais', 'aguardando'));
    }

    /**
     * Trabalhos concluídos (aprovados nas duas partes e apresentados) que NÃO entram nos Anais, cada um com o motivo:
     * 'autorizacao' (autores não autorizaram a publicação) ou 'pdf' (sem PDF publicável).
     */
    private function foraDosAnais(Evento $evento)
    {
        $evento->loadMissing('trabalhoConfig');
        $consulta = $evento->trabalhos()->where('status', 'aprovado')->where('apresentado', true)->with(['autores', 'arquivoAnais', 'arquivoAtual', 'evento.trabalhoConfig', 'certificados'])->orderBy('codigo');
        if ($evento->trabalhoConfig?->avaliacao_apresentacao_ativa) {
            $consulta->where('resultado_apresentacao', 'aprovado');
        }

        return $consulta->get()->each(function ($t) {
            $t->motivoAnais = !$t->autoriza_publicacao ? 'autorizacao' : ($t->versao_final_pendente ? 'versao_final' : ($this->pdf->arquivoPublico($t) === null ? 'pdf' : null));
        })->filter(fn ($t) => $t->motivoAnais !== null)->values();
    }

    /** Pede (por e-mail) aos autores dos trabalhos concluídos sem autorização que autorizem a publicação nos Anais. */
    public function solicitarAutorizacao(Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $enviados = 0;
        $recentes = 0;
        foreach ($this->foraDosAnais($evento)->where('motivoAnais', 'autorizacao') as $t) {
            // evita repetir o pedido em menos de 24 h
            if ($t->autorizacao_solicitada_em && $t->autorizacao_solicitada_em->gt(now()->subDay())) {
                $recentes++;
                continue;
            }
            $emails = $t->autores->pluck('email')->filter()->push($t->submetente?->email)->filter()->map(fn ($e) => mb_strtolower($e))->unique();
            foreach ($emails as $email) {
                \Illuminate\Support\Facades\Mail::to($email)->queue(new \App\Mail\AutorizacaoPublicacaoMail($t));
            }
            Trabalho::whereKey($t->id)->update(['autorizacao_solicitada_em' => now()]);   // (o modelo carrega um atributo de exibição que não vai ao banco)
            $enviados++;
        }

        if ($enviados === 0 && $recentes === 0) {
            return back()->with('error', 'Não há trabalhos concluídos aguardando autorização dos autores.');
        }

        return back()->with($enviados ? 'success' : 'warning', $enviados
            ? "Pedido de autorização enviado para os autores de {$enviados} trabalho(s)." . ($recentes ? " {$recentes} já tinham recebido o pedido nas últimas 24 h." : '')
            : "Os autores já receberam o pedido nas últimas 24 h ({$recentes} trabalho(s)).");
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
            'local_publicacao' => ['nullable', 'string', 'max:255'],
            'editora' => ['nullable', 'string', 'max:255'],
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
        if ($anais->pdfGerando() && !$anais->pdfPreso()) {
            return back()->with('error', 'O PDF já está sendo gerado.');
        }
        if (Anais::elegiveis($evento)->doesntExist()) {
            return back()->with('error', 'Não há trabalhos elegíveis (aprovados, apresentados e autorizados) para compilar.');
        }

        $anais->update(['pdf_status' => 'gerando', 'pdf_erro' => null, 'pdf_iniciado_em' => now()]);
        GerarAnaisPdf::dispatch($anais);

        return back()->with('success', 'Geração do PDF iniciada. A página acompanha o andamento sozinha.');
    }

    /** Andamento da geração (consultado pela página a cada poucos segundos). */
    public function statusPdf(Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $anais = $evento->anais;
        abort_unless($anais, 404);

        $batimento = \Illuminate\Support\Facades\Cache::get('agendador:ultimo-batimento');
        $conexao = config('queue.default');
        $filaParada = $anais->pdfGerando() && $conexao !== 'sync'
            && (!$batimento || \Illuminate\Support\Carbon::parse($batimento)->lt(now()->subMinutes(3)));

        return response()->json([
            'status' => $anais->pdf_status,
            'erro' => $anais->pdf_erro,
            'segundos' => $anais->pdfGerando() && $anais->pdf_iniciado_em ? (int) $anais->pdf_iniciado_em->diffInSeconds(now()) : null,
            'preso' => $anais->pdfPreso(),
            'fila_parada' => $filaParada,
        ]);
    }

    /** Gera o PDF agora, dentro da requisição (sem depender da fila). Útil quando a geração em segundo plano não anda. */
    public function gerarAgora(Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $anais = $evento->anais;
        if (!$anais) {
            return back()->with('error', 'Salve os dados dos Anais antes de gerar o PDF.');
        }
        if (Anais::elegiveis($evento)->doesntExist()) {
            return back()->with('error', 'Não há trabalhos elegíveis (aprovados, apresentados e autorizados) para compilar.');
        }

        @set_time_limit(0);
        $anais->update(['pdf_status' => 'gerando', 'pdf_erro' => null, 'pdf_iniciado_em' => now()]);

        try {
            app(\App\Services\AnaisPdfService::class)->gerar($anais);
        } catch (\Throwable $e) {
            report($e);
            $anais->update(['pdf_status' => 'erro', 'pdf_erro' => \Illuminate\Support\Str::limit($e->getMessage(), 500)]);

            return back()->with('error', 'Não foi possível gerar o PDF: ' . \Illuminate\Support\Str::limit($e->getMessage(), 200));
        }

        return back()->with('success', 'PDF dos Anais gerado.');
    }

    /** Cancela uma geração presa para poder tentar de novo. */
    public function cancelarPdf(Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $anais = $evento->anais;
        if ($anais && $anais->pdfGerando()) {
            $anais->update(['pdf_status' => 'erro', 'pdf_erro' => 'Geração cancelada manualmente. Gere o PDF novamente.']);
        }

        return back()->with('success', 'Geração cancelada. Você já pode gerar de novo.');
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

        $anais->update(['status' => 'publicado', 'publicado_em' => $anais->publicado_em ?? now(), 'publicado_por' => $request->user()->id]);

        $total = Anais::elegiveis($evento)->count();
        $aviso = $total === 0 ? ' Ainda não há trabalhos elegíveis: eles aparecem na página conforme forem apresentados.' : " {$total} trabalho(s) listado(s).";

        return back()->with('success', 'Anais visíveis ao público.' . $aviso . ($anais->dadosVisiveis() ? '' : ' Os dados de publicação continuam ocultos.'));
    }

    /** Libera ou oculta os dados de publicação (ISBN/ISSN/DOI, editora, local, ano, citação ABNT e PDF compilado). */
    public function dadosPublicacao(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $dados = $request->validate(['acao' => ['required', 'in:exibir,ocultar']]);
        $anais = $evento->anais;
        abort_unless($anais, 404);

        $anais->update(['dados_publicacao_visiveis' => $dados['acao'] === 'exibir']);

        return back()->with('success', $dados['acao'] === 'exibir'
            ? 'Dados de publicação liberados na página pública.'
            : 'Dados de publicação ocultados. A página pública mostra apenas os trabalhos.');
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
