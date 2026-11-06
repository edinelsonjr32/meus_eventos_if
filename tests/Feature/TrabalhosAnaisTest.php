<?php

use App\Jobs\GerarAnaisPdf;
use App\Models\Anais;
use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use App\Services\AnaisPdfService;
use App\Services\CertificadoTrabalhoService;
use App\Services\CitacaoAbnt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

/** PDF real com N páginas. */
function pdfComPaginas(int $n): string
{
    $html = collect(range(1, $n))->map(fn ($i) => "<p>Página {$i}</p>")->implode('<div style="page-break-after: always;"></div>');

    return Pdf::loadHTML($html)->output();
}

/** Trabalho publicável nos Anais, com PDF no disco privado. */
function trabalhoPublicavel($ctx, array $extra = [], int $paginas = 2, bool $comPdf = true): Trabalho
{
    $t = mkTrabalho($ctx->evento, $ctx->autorUser, $extra, 'aprovado');
    $t->update(['apresentado' => true, 'apresentado_em' => now(), 'autoriza_publicacao' => true]);

    if ($comPdf) {
        $path = "{$t->evento_id}/{$t->id}/v1-teste.pdf";
        Storage::disk('trabalhos')->put($path, pdfComPaginas($paginas));
        $t->arquivos()->create(['versao' => 1, 'tipo' => 'original', 'path' => $path, 'nome_original' => 'x.pdf', 'mime' => 'application/pdf', 'tamanho' => 10, 'sha256' => str_repeat('a', 64), 'enviado_por' => $ctx->autorUser->id, 'ativo' => true]);
    }

    return $t->fresh();
}

function criarAnais($ctx, array $extra = []): Anais
{
    return Anais::create(array_merge([
        'evento_id' => $ctx->evento->id, 'titulo' => 'Anais da Semana Teste', 'ano' => 2026, 'local_publicacao' => 'Óbidos',
        'editora' => 'IFPA Campus Óbidos', 'status' => 'publicado', 'publicado_em' => now(),
    ], $extra));
}

function gsDisponivel(): bool
{
    return Process::run([config('trabalhos.ghostscript'), '--version'])->successful();
}

function arquivoFinalFake($ctx, Trabalho $t, int $versao = 2): void
{
    Storage::disk('trabalhos')->put("final-{$versao}.pdf", pdfComPaginas(1));
    $t->arquivos()->create(['versao' => $versao, 'tipo' => 'final_anais', 'path' => "final-{$versao}.pdf", 'nome_original' => 'f.pdf', 'mime' => 'application/pdf', 'tamanho' => 10, 'sha256' => str_repeat('b', 64), 'enviado_por' => $ctx->gestor->id, 'ativo' => false]);
}

beforeEach(function () {
    Mail::fake();
    Storage::fake('trabalhos');
    RateLimiter::clear('anais');

    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->evento->update(['slug' => 'semana-teste', 'titulo' => 'Semana de Tecnologia']);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->autorUser = mkUser();
    $this->cit = app(CitacaoAbnt::class);
    $this->pdfs = app(AnaisPdfService::class);
});

// ---------- Citação ABNT ----------
it('formata nomes no padrão ABNT', function (string $nome, string $esperado) {
    expect($this->cit->formatar($nome))->toBe($esperado);
})->with([
    'simples' => ['Maria Souza', 'SOUZA, M.'],
    'nome composto' => ['Maria Clara Souza', 'SOUZA, M. C.'],
    'partículas' => ['João da Silva Santos', 'SANTOS, J. S.'],
    'sufixo Júnior' => ['Pedro Alves Junior', 'ALVES JUNIOR, P.'],
    'sufixo Neto' => ['Ana Costa Neto', 'COSTA NETO, A.'],
    'nome único' => ['Madonna', 'MADONNA'],
    'espaços extras' => ['  Carlos   Lima ', 'LIMA, C.'],
    'acentos' => ['érica Álvares', 'ÁLVARES, É.'],
]);

it('gera a referência completa e exclui orientadores dos autores', function () {
    $anais = criarAnais($this);
    $t = mkTrabalho($this->evento, $this->autorUser, [
        'titulo' => 'Robótica na escola.',
        'autores' => [
            ['nome_completo' => 'Ana Beatriz Lima', 'cpf' => '111.444.777-35', 'email' => 'a@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => true],
            ['nome_completo' => 'Caio Mendes', 'cpf' => '529.982.247-25', 'email' => 'c@x.com', 'tipo_autor' => 'coautor'],
            ['nome_completo' => 'Olga Orientadora', 'cpf' => '168.995.350-09', 'email' => 'o@x.com', 'tipo_autor' => 'orientador'],
        ],
    ]);

    expect($this->cit->gerar($t->fresh('autores'), $anais))
        ->toBe('LIMA, A. B.; MENDES, C. Robótica na escola. In: SEMANA DE TECNOLOGIA, 2026, Óbidos. Anais [...]. Óbidos: IFPA Campus Óbidos, 2026.');
});

it('usa et al. com mais de três autores', function () {
    $cpfs = ['111.444.777-35', '529.982.247-25', '168.995.350-09', '935.411.347-80'];
    $autores = collect($cpfs)->map(fn ($c, $i) => ['nome_completo' => "Fulano Silva{$i}", 'cpf' => $c, 'email' => "f{$i}@x.com", 'tipo_autor' => $i === 0 ? 'autor_principal' : 'coautor', 'apresentador' => $i === 0])->all();
    $t = mkTrabalho($this->evento, $this->autorUser, ['autores' => $autores]);

    expect($this->cit->autores($t->fresh('autores')))->toBe('SILVA0, F. et al.');
});

// ---------- Acesso público ----------
it('não expõe os Anais antes da publicação nem de evento inexistente', function () {
    $t = trabalhoPublicavel($this);
    criarAnais($this, ['status' => 'rascunho']);

    $this->get(route('anais.index', 'semana-teste'))->assertNotFound();
    $this->get(route('anais.show', ['semana-teste', $t->codigo]))->assertNotFound();
    $this->get(route('anais.trabalho.pdf', ['semana-teste', $t->codigo]))->assertNotFound();
    $this->get(route('anais.pdf', 'semana-teste'))->assertNotFound();
    $this->get(route('anais.index', 'nao-existe'))->assertNotFound();
});

it('lista somente trabalhos aprovados, apresentados e autorizados', function () {
    criarAnais($this);
    trabalhoPublicavel($this, ['titulo' => 'Publicável alfa']);
    $semAutorizacao = trabalhoPublicavel($this, ['titulo' => 'Sem autorização']);
    $semAutorizacao->update(['autoriza_publicacao' => false]);
    $naoApresentou = trabalhoPublicavel($this, ['titulo' => 'Não apresentado']);
    $naoApresentou->update(['apresentado' => false]);
    $reprovado = trabalhoPublicavel($this, ['titulo' => 'Reprovado']);
    $reprovado->update(['status' => 'reprovado']);
    mkTrabalho($this->evento, $this->autorUser, ['titulo' => 'Apenas submetido']);

    $this->get(route('anais.index', 'semana-teste'))->assertOk()
        ->assertSee('Publicável alfa')->assertSee('1 trabalho(s) publicado(s)')
        ->assertDontSee('Sem autorização')->assertDontSee('Não apresentado')->assertDontSee('Reprovado')->assertDontSee('Apenas submetido');

    foreach ([$semAutorizacao, $naoApresentou, $reprovado] as $t) {
        $this->get(route('anais.show', ['semana-teste', $t->codigo]))->assertNotFound();
    }
});

it('filtra e busca na listagem pública', function () {
    criarAnais($this);
    trabalhoPublicavel($this, ['titulo' => 'Inteligência artificial', 'tipo_trabalho' => 'artigo_completo', 'palavras_chave' => 'ia, ensino, dados']);
    $b = trabalhoPublicavel($this, ['titulo' => 'Horta escolar', 'tipo_trabalho' => 'resumo']);
    $b->update(['modalidade_apresentacao' => 'poster']);

    $this->get(route('anais.index', ['semana-teste', 'q' => 'artificial']))->assertSee('Inteligência artificial')->assertDontSee('Horta escolar');
    $this->get(route('anais.index', ['semana-teste', 'q' => 'ensino']))->assertSee('Inteligência artificial')->assertDontSee('Horta escolar');
    $this->get(route('anais.index', ['semana-teste', 'tipo' => 'resumo']))->assertSee('Horta escolar')->assertDontSee('Inteligência artificial');
    $this->get(route('anais.index', ['semana-teste', 'modalidade' => 'poster']))->assertSee('Horta escolar')->assertDontSee('Inteligência artificial');
    $this->get(route('anais.index', ['semana-teste', 'q' => '%_']))->assertOk()->assertDontSee('Horta escolar');   // curingas não vazam resultados
});

it('mostra detalhes com metadados de indexação e citação', function () {
    criarAnais($this, ['isbn' => '978-85-1234-567-8']);
    $t = trabalhoPublicavel($this, ['titulo' => 'Tema acadêmico']);

    $this->get(route('anais.show', ['semana-teste', $t->codigo]))->assertOk()
        ->assertSee('<meta name="citation_title" content="Tema acadêmico">', false)
        ->assertSee('<meta name="citation_conference_title" content="Semana de Tecnologia">', false)
        ->assertSee('citation_author', false)->assertSee('citation_pdf_url', false)->assertSee('citation_isbn', false)
        ->assertSee('Como citar (ABNT)')->assertSee('Anais [...]');
});

it('não vaza dados sensíveis dos autores na página pública', function () {
    criarAnais($this);
    $t = trabalhoPublicavel($this);

    $this->get(route('anais.show', ['semana-teste', $t->codigo]))->assertOk()
        ->assertDontSee('111.444.777-35')->assertDontSee($this->autorUser->email);
});

// ---------- PDF do trabalho ----------
it('serve o PDF do trabalho publicado com cabeçalhos seguros', function () {
    criarAnais($this);
    $t = trabalhoPublicavel($this);

    $r = $this->get(route('anais.trabalho.pdf', ['semana-teste', $t->codigo]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($r->headers->get('Content-Type'))->toContain('application/pdf');
});

it('trabalho de outro evento não é servido pelo slug errado', function () {
    criarAnais($this);
    $outroEvento = mkEvento($this->dono);
    $outroEvento->update(['slug' => 'outro-evento']);
    $estranho = mkTrabalho($outroEvento, $this->autorUser, [], 'aprovado');
    $estranho->update(['apresentado' => true, 'autoriza_publicacao' => true]);

    $this->get(route('anais.show', ['semana-teste', $estranho->codigo]))->assertNotFound();
    $this->get(route('anais.trabalho.pdf', ['semana-teste', $estranho->codigo]))->assertNotFound();
});

it('em avaliação cega só publica PDF se houver a versão final dos Anais', function () {
    criarAnais($this);
    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['avaliacao_cega' => true]);
    $t = trabalhoPublicavel($this);

    $this->get(route('anais.trabalho.pdf', ['semana-teste', $t->codigo]))->assertNotFound();
    $this->get(route('anais.show', ['semana-teste', $t->codigo]))->assertOk()->assertDontSee('Baixar PDF do trabalho');

    arquivoFinalFake($this, $t);

    $this->get(route('anais.trabalho.pdf', ['semana-teste', $t->codigo]))->assertOk();
});

it('a versão final dos Anais tem prioridade sobre o PDF da submissão', function () {
    $t = trabalhoPublicavel($this);
    arquivoFinalFake($this, $t);
    $final = $t->arquivos()->where('tipo', 'final_anais')->first();

    $recarregado = $t->fresh()->load(['arquivoAnais', 'arquivoAtual', 'evento.trabalhoConfig']);
    expect($this->pdfs->arquivoPublico($recarregado)->id)->toBe($final->id);
    // e o arquivo em avaliação continua sendo o da submissão
    expect($t->fresh()->arquivoAtual->tipo)->toBe('original');
});

// ---------- Certificado pelo autor ----------
function certificadoDoTrabalho($ctx, Trabalho $t): void
{
    app(CertificadoTrabalhoService::class)->emitir($t->fresh(), $ctx->gestor);
}

it('autor obtém o certificado informando CPF e e-mail corretos', function () {
    criarAnais($this);
    $t = trabalhoPublicavel($this, ['autores' => [
        ['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => true],
        ['nome_completo' => 'Caio', 'cpf' => '529.982.247-25', 'email' => 'caio@x.com', 'tipo_autor' => 'coautor'],
    ]]);
    certificadoDoTrabalho($this, $t);
    $cert = $t->certificados()->whereHas('autor', fn ($a) => $a->where('cpf', '529.982.247-25'))->first();

    $this->post(route('anais.certificado', ['semana-teste', $t->codigo]), ['cpf' => '52998224725', 'email' => 'CAIO@x.com'])
        ->assertRedirect(route('certificado.trabalho.download', $cert->hash_validacao));
});

it('falhas na obtenção do certificado dão resposta genérica, sem revelar o que existe', function (array $dados) {
    criarAnais($this);
    $t = trabalhoPublicavel($this, ['autores' => [
        ['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => true],
    ]]);
    certificadoDoTrabalho($this, $t);

    $r = $this->from(route('anais.show', ['semana-teste', $t->codigo]))->post(route('anais.certificado', ['semana-teste', $t->codigo]), $dados);

    $r->assertRedirect(route('anais.show', ['semana-teste', $t->codigo]))->assertSessionHas('erro_certificado');
    expect(session('erro_certificado'))->toBe('Não foi possível localizar um certificado com esses dados. Verifique o CPF e o e-mail informados na submissão.');
})->with([
    'e-mail errado' => [['cpf' => '111.444.777-35', 'email' => 'outro@x.com']],
    'CPF errado' => [['cpf' => '529.982.247-25', 'email' => 'ana@x.com']],
    'CPF de ninguém' => [['cpf' => '935.411.347-80', 'email' => 'ana@x.com']],
]);

it('não entrega certificado revogado ou ainda não emitido', function () {
    criarAnais($this);
    $t = trabalhoPublicavel($this, ['autores' => [['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => true]]]);
    $dados = ['cpf' => '111.444.777-35', 'email' => 'ana@x.com'];

    $this->post(route('anais.certificado', ['semana-teste', $t->codigo]), $dados)->assertSessionHas('erro_certificado');   // ainda não emitido

    certificadoDoTrabalho($this, $t);
    $t->certificados()->update(['revogado_em' => now()]);
    $this->post(route('anais.certificado', ['semana-teste', $t->codigo]), $dados)->assertSessionHas('erro_certificado');
});

it('limita tentativas de obter certificado (anti força bruta)', function () {
    criarAnais($this);
    $t = trabalhoPublicavel($this);

    foreach (range(1, 10) as $i) {
        $this->post(route('anais.certificado', ['semana-teste', $t->codigo]), ['cpf' => '111.444.777-35', 'email' => 'x@x.com'])->assertSessionHas('erro_certificado');
    }
    $this->post(route('anais.certificado', ['semana-teste', $t->codigo]), ['cpf' => '111.444.777-35', 'email' => 'x@x.com'])->assertStatus(429);
});

// ---------- Gestão ----------
it('gestor edita os dados dos Anais com validação', function () {
    $url = route('gestao.trabalhos.anais.update', $this->evento);
    $base = ['titulo' => 'Anais X', 'ano' => 2026, 'local_publicacao' => 'Óbidos', 'editora' => 'IFPA'];
    $this->actingAs($this->gestor);

    $this->get(route('gestao.trabalhos.anais.edit', $this->evento))->assertOk()->assertSee('Anais do Semana de Tecnologia');
    $this->put($url, array_merge($base, ['issn' => '1234-567X', 'isbn' => '978-85-1234-567-8', 'doi_prefixo' => '10.12345', 'comissao' => "Ana\nCaio"]))->assertSessionHasNoErrors();

    $a = Anais::where('evento_id', $this->evento->id)->first();
    expect($a->issn)->toBe('1234-567X')->and($a->status)->toBe('rascunho')->and($a->comissao)->toBe("Ana\nCaio");

    $this->put($url, array_merge($base, ['issn' => '123']))->assertSessionHasErrors('issn');
    $this->put($url, array_merge($base, ['isbn' => 'abc']))->assertSessionHasErrors('isbn');
    $this->put($url, array_merge($base, ['doi_prefixo' => '11.1']))->assertSessionHasErrors('doi_prefixo');
    $this->put($url, array_merge($base, ['ano' => 1999]))->assertSessionHasErrors('ano');
    $this->put($url, ['titulo' => ''])->assertSessionHasErrors(['titulo', 'ano', 'editora']);
});

it('publica e despublica; publicar exige trabalho elegível com PDF', function () {
    $anais = criarAnais($this, ['status' => 'rascunho', 'publicado_em' => null]);
    $this->actingAs($this->gestor);

    $this->post(route('gestao.trabalhos.anais.publicar', $this->evento))->assertSessionHas('error');   // nada elegível
    expect($anais->fresh()->publicado())->toBeFalse();

    trabalhoPublicavel($this, [], 1, false);                                                          // elegível, mas sem PDF
    $this->post(route('gestao.trabalhos.anais.publicar', $this->evento))->assertSessionHas('error');

    trabalhoPublicavel($this);
    $this->post(route('gestao.trabalhos.anais.publicar', $this->evento))->assertSessionHas('success');
    expect($anais->fresh()->publicado())->toBeTrue()->and($anais->fresh()->publicado_por)->toBe($this->gestor->id);
    $this->get(route('anais.index', 'semana-teste'))->assertOk();

    $this->post(route('gestao.trabalhos.anais.despublicar', $this->evento))->assertSessionHas('success');
    $this->get(route('anais.index', 'semana-teste'))->assertNotFound();
});

it('página do evento mostra o botão Anais somente quando publicados', function () {
    $anais = criarAnais($this, ['status' => 'rascunho']);
    $this->get(route('evento.publico.show', 'semana-teste'))->assertOk()->assertDontSee(route('anais.index', 'semana-teste'), false);

    $anais->update(['status' => 'publicado']);
    $this->get(route('evento.publico.show', 'semana-teste'))->assertOk()->assertSee(route('anais.index', 'semana-teste'), false);
});

it('somente quem gere trabalhos usa a gestão dos Anais', function (string $quem) {
    $t = trabalhoPublicavel($this);
    $anais = criarAnais($this, ['status' => 'rascunho']);
    $usuario = match ($quem) {
        'autor' => $this->autorUser,
        'avaliador' => tap(mkUser(), fn ($u) => mkPapel($u, $this->evento, 'avaliador')),
        'gestor de outro evento' => tap(mkUser(), fn ($u) => mkPapel($u, mkEvento($this->dono), 'gestor_trabalhos')),
        'admin de outro evento' => mkUser('admin'),
    };
    $this->actingAs($usuario);
    Queue::fake();

    $this->get(route('gestao.trabalhos.anais.edit', $this->evento))->assertForbidden();
    $this->put(route('gestao.trabalhos.anais.update', $this->evento), ['titulo' => 'x', 'ano' => 2026, 'local_publicacao' => 'a', 'editora' => 'b'])->assertForbidden();
    $this->post(route('gestao.trabalhos.anais.gerar', $this->evento))->assertForbidden();
    $this->post(route('gestao.trabalhos.anais.publicar', $this->evento))->assertForbidden();
    $this->post(route('gestao.trabalhos.anais.despublicar', $this->evento))->assertForbidden();
    $this->get(route('gestao.trabalhos.anais.pdf', $this->evento))->assertForbidden();
    $this->post(route('gestao.trabalhos.arquivo-final', $t), ['arquivo' => UploadedFile::fake()->createWithContent('f.pdf', "%PDF-1.4\n%%EOF")])->assertForbidden();
    $this->get(route('gestao.trabalhos.arquivo-final.baixar', $t))->assertForbidden();

    Queue::assertNothingPushed();
    expect($anais->fresh()->status)->toBe('rascunho')->and($t->fresh()->arquivoAnais)->toBeNull();
})->with(['autor', 'avaliador', 'gestor de outro evento', 'admin de outro evento']);

it('gerar enfileira o job e marca como gerando; exige dados salvos e trabalhos elegíveis', function () {
    Queue::fake();
    $this->actingAs($this->gestor);

    $this->post(route('gestao.trabalhos.anais.gerar', $this->evento))->assertSessionHas('error');   // sem registro de Anais

    $anais = criarAnais($this, ['status' => 'rascunho']);
    $this->post(route('gestao.trabalhos.anais.gerar', $this->evento))->assertSessionHas('error');   // sem elegíveis
    Queue::assertNothingPushed();

    trabalhoPublicavel($this);
    $this->post(route('gestao.trabalhos.anais.gerar', $this->evento))->assertSessionHas('success');
    Queue::assertPushed(GerarAnaisPdf::class);
    expect($anais->fresh()->pdf_status)->toBe('gerando');

    $this->post(route('gestao.trabalhos.anais.gerar', $this->evento))->assertSessionHas('error');   // já gerando
});

it('envia o PDF final de um trabalho sem substituir o arquivo da submissão', function () {
    $t = trabalhoPublicavel($this);
    $atualAntes = $t->arquivoAtual->id;
    $this->actingAs($this->gestor);

    $this->post(route('gestao.trabalhos.arquivo-final', $t), ['arquivo' => UploadedFile::fake()->createWithContent('final.pdf', pdfComPaginas(1))])->assertSessionHas('success');

    $t = $t->fresh();
    $versaoPrimeira = $t->arquivoAnais->versao;
    expect($t->arquivoAnais->tipo)->toBe('final_anais')->and($t->arquivoAnais->ativo)->toBeFalse()
        ->and($t->arquivoAtual->id)->toBe($atualAntes)
        ->and(Storage::disk('trabalhos')->exists($t->arquivoAnais->path))->toBeTrue();
    $this->get(route('gestao.trabalhos.arquivo-final.baixar', $t))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

    // nova versão passa a valer
    $this->post(route('gestao.trabalhos.arquivo-final', $t), ['arquivo' => UploadedFile::fake()->createWithContent('final2.pdf', pdfComPaginas(1))]);
    expect($t->fresh()->arquivoAnais->versao)->toBeGreaterThan($versaoPrimeira);
});

it('rejeita PDF final inválido (não PDF, extensão falsa, vazio)', function (string $nome, string $conteudo) {
    $t = trabalhoPublicavel($this);
    $this->actingAs($this->gestor);

    $this->post(route('gestao.trabalhos.arquivo-final', $t), ['arquivo' => UploadedFile::fake()->createWithContent($nome, $conteudo)]);

    expect($t->fresh()->arquivoAnais)->toBeNull();
})->with([
    'texto com extensão pdf' => ['falso.pdf', 'isto não é um pdf'],
    'executável' => ['malware.pdf', "MZ\x90\x00 binário"],
    'html' => ['pagina.html', '<html></html>'],
]);

// ---------- Compilação do PDF ----------
it('compila o PDF com capa, sumário e os trabalhos', function () {
    if (!gsDisponivel()) {
        $this->markTestSkipped('Ghostscript indisponível.');
    }

    $anais = criarAnais($this, ['comissao' => "Ana Lima\nCaio Mendes", 'apresentacao' => 'Bem-vindos.', 'issn' => '1234-5678']);
    trabalhoPublicavel($this, ['titulo' => 'Zebra primeiro trabalho'], 3);
    trabalhoPublicavel($this, ['titulo' => 'Abelha segundo trabalho'], 2);

    $resultado = $this->pdfs->gerar($anais->fresh('evento'));

    $frente = $resultado->pdf_paginas - 3 - 2;
    expect($resultado->pdf_status)->toBe('pronto')->and($resultado->pdf_aviso)->toBeNull()
        ->and($frente)->toBeGreaterThanOrEqual(3)   // capa + expediente + sumário
        ->and($resultado->pdf_tamanho)->toBeGreaterThan(1000)
        ->and(Storage::disk('trabalhos')->exists($resultado->pdf_path))->toBeTrue();
    expect(substr(Storage::disk('trabalhos')->get($resultado->pdf_path), 0, 5))->toBe('%PDF-');
    expect($this->pdfs->paginas(Storage::disk('trabalhos')->path($resultado->pdf_path)))->toBe($resultado->pdf_paginas);
});

it('omite trabalhos sem PDF ou com PDF corrompido e informa no aviso', function () {
    if (!gsDisponivel()) {
        $this->markTestSkipped('Ghostscript indisponível.');
    }

    $anais = criarAnais($this);
    trabalhoPublicavel($this, ['titulo' => 'Bom'], 2);
    $semPdf = trabalhoPublicavel($this, ['titulo' => 'Sem pdf'], 1, false);
    $corrompido = trabalhoPublicavel($this, ['titulo' => 'Corrompido'], 1, false);
    Storage::disk('trabalhos')->put('x/corrompido.pdf', "%PDF-1.4\nlixo sem estrutura");
    $corrompido->arquivos()->create(['versao' => 1, 'tipo' => 'original', 'path' => 'x/corrompido.pdf', 'nome_original' => 'c.pdf', 'mime' => 'application/pdf', 'tamanho' => 10, 'sha256' => str_repeat('c', 64), 'enviado_por' => $this->autorUser->id, 'ativo' => true]);

    $r = $this->pdfs->gerar($anais->fresh('evento'));

    expect($r->pdf_status)->toBe('pronto')->and($r->pdf_aviso)->toContain($semPdf->codigo)->toContain($corrompido->codigo)
        ->and($r->pdf_paginas)->toBeGreaterThan(2);
});

it('falha com mensagem clara quando não há nenhum PDF publicável', function () {
    $anais = criarAnais($this);
    trabalhoPublicavel($this, [], 1, false);

    expect(fn () => $this->pdfs->gerar($anais->fresh('evento')))->toThrow(RuntimeException::class, 'Nenhum trabalho com PDF publicável');
});

it('ao gerar de novo apaga o PDF anterior e não deixa temporários', function () {
    if (!gsDisponivel()) {
        $this->markTestSkipped('Ghostscript indisponível.');
    }

    $anais = criarAnais($this);
    trabalhoPublicavel($this);

    $primeiro = $this->pdfs->gerar($anais->fresh('evento'));
    $velho = $primeiro->pdf_path;
    $segundo = $this->pdfs->gerar($primeiro->fresh('evento'));

    expect($segundo->pdf_path)->not->toBe($velho)
        ->and(Storage::disk('trabalhos')->exists($velho))->toBeFalse()
        ->and(Storage::disk('trabalhos')->exists($segundo->pdf_path))->toBeTrue()
        ->and(collect(Storage::disk('trabalhos')->allFiles("anais/{$this->evento->id}"))->filter(fn ($f) => str_contains($f, 'tmp-')))->toBeEmpty();
});

it('conta páginas e rejeita arquivos que não são PDF', function () {
    if (!gsDisponivel()) {
        $this->markTestSkipped('Ghostscript indisponível.');
    }

    Storage::disk('trabalhos')->put('t/ok.pdf', pdfComPaginas(3));
    Storage::disk('trabalhos')->put('t/ruim.pdf', 'não sou pdf');
    Storage::disk('trabalhos')->put('t/vazio.pdf', '');

    expect($this->pdfs->paginas(Storage::disk('trabalhos')->path('t/ok.pdf')))->toBe(3)
        ->and($this->pdfs->paginas(Storage::disk('trabalhos')->path('t/ruim.pdf')))->toBeNull()
        ->and($this->pdfs->paginas(Storage::disk('trabalhos')->path('t/vazio.pdf')))->toBeNull()
        ->and($this->pdfs->paginas(Storage::disk('trabalhos')->path('t/nao-existe.pdf')))->toBeNull();
});

it('o job registra o erro quando a compilação falha', function () {
    $anais = criarAnais($this);

    (new GerarAnaisPdf($anais))->failed(new RuntimeException('Falha simulada ao unir'));

    $a = $anais->fresh();
    expect($a->pdf_status)->toBe('erro')->and($a->pdf_erro)->toContain('Falha simulada');
});

it('o PDF compilado só é baixado publicamente depois de publicado e pronto', function () {
    if (!gsDisponivel()) {
        $this->markTestSkipped('Ghostscript indisponível.');
    }

    $anais = criarAnais($this, ['status' => 'rascunho']);
    trabalhoPublicavel($this);
    $anais = $this->pdfs->gerar($anais->fresh('evento'));

    $this->get(route('anais.pdf', 'semana-teste'))->assertNotFound();                  // rascunho
    $anais->update(['status' => 'publicado']);
    $this->get(route('anais.pdf', 'semana-teste'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('anais.index', 'semana-teste'))->assertSee('Baixar os Anais completos');

    $anais->update(['pdf_status' => 'gerando']);
    $this->get(route('anais.pdf', 'semana-teste'))->assertNotFound();                  // sem PDF pronto
    $this->get(route('anais.index', 'semana-teste'))->assertDontSee('Baixar os Anais completos');
});
