<?php

use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use App\Services\TrabalhoWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function pdfComTexto(string $texto): UploadedFile
{
    return UploadedFile::fake()->createWithContent('t.pdf', "%PDF-1.4\n{$texto}\n%%EOF");
}

function dadosCegos(array $extra = []): array
{
    return array_merge([
        'acao' => 'submeter', 'titulo' => 'Um trabalho de teste', 'resumo' => trim(str_repeat('palavra ', 120)), 'palavras_chave' => 'ensino, tecnologia, inovação',
        'tipo_trabalho' => 'pesquisa', 'modalidade_apresentacao' => 'oral', 'declaracao_ineditismo' => 1, 'declaracao_regulamento' => 1, 'autoriza_publicacao' => 1,
        'autores' => [['nome_completo' => 'Ana Autora', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'instituicao' => 'IFPA', 'categoria_autor' => 'docente', 'tipo_autor' => 'autor_principal', 'apresentador' => 1, 'correspondente' => 1]],
        'arquivo' => pdfComTexto('PRINCIPAL'),
    ], $extra);
}

beforeEach(function () {
    Mail::fake();
    Storage::fake('trabalhos');

    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->av1 = mkUser();
    mkPapel($this->av1, $this->evento, 'avaliador');
    $this->av2 = mkUser();
    mkPapel($this->av2, $this->evento, 'avaliador');
    $this->autor = mkUser();
    $this->w = app(TrabalhoWorkflow::class);

    $this->trabalho = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Trabalho cego'], 'em_triagem');
    $this->a1 = $this->w->atribuirAvaliador($this->trabalho, $this->av1, $this->gestor);
    $this->w->atribuirAvaliador($this->trabalho, $this->av2, $this->gestor);
    $this->w->transicionar($this->trabalho->fresh(), $this->gestor, 'em_avaliacao');
    $this->trabalho = $this->trabalho->fresh();
    Mail::fake();
    $this->cega = fn (bool $v) => TrabalhoConfig::where('evento_id', $this->evento->id)->update(['avaliacao_cega' => $v]);
});

it('trabalho já enviado (só PDF principal) continua abrindo para o avaliador, sem erro, com ou sem avaliação cega', function () {
    $this->w->salvar($this->evento, $this->autor, ['titulo' => 'Trabalho cego'], $this->trabalho, pdfComTexto('PRINCIPAL-ANTIGO'));
    $trabalho = $this->trabalho->fresh(['arquivoAtual', 'arquivoAnonimo', 'evento.trabalhoConfig']);

    foreach ([false, true] as $cega) {
        ($this->cega)($cega);
        $r = $this->actingAs($this->av1)->get(route('avaliador.avaliacoes.arquivo', $this->a1))->assertOk();
        expect($r->streamedContent())->toContain('PRINCIPAL-ANTIGO');
        $this->actingAs($this->av1)->get(route('avaliador.avaliacoes.show', $this->a1))->assertOk();
    }
    expect($trabalho->fresh(['arquivoAtual', 'arquivoAnonimo', 'evento.trabalhoConfig'])->semVersaoAnonima())->toBeTrue();
});

it('com avaliação cega o avaliador abre só a versão sem identificação; sem cegueira, o principal', function () {
    $this->w->salvar($this->evento, $this->autor, ['titulo' => 'Trabalho cego'], $this->trabalho, pdfComTexto('PRINCIPAL-COM-AUTORES'), pdfComTexto('VERSAO-ANONIMA'));
    $t = $this->trabalho->fresh(['arquivoAtual', 'arquivoAnonimo']);

    // o PDF principal continua sendo o "atual" para autor/gestão; o anônimo não o substitui
    expect($t->arquivoAtual->anonimo)->toBeFalse()->and($t->arquivoAnonimoVigente()?->anonimo)->toBeTrue();
    expect($this->actingAs($this->autor)->get(route('trabalhos.arquivo', $t))->streamedContent())->toContain('PRINCIPAL-COM-AUTORES');

    ($this->cega)(true);
    $r = $this->actingAs($this->av1)->get(route('avaliador.avaliacoes.arquivo', $this->a1))->assertOk();
    expect($r->streamedContent())->toContain('VERSAO-ANONIMA')->not->toContain('PRINCIPAL-COM-AUTORES');
    expect($r->headers->get('content-disposition'))->not->toContain('Trabalho cego');

    ($this->cega)(false);
    expect($this->actingAs($this->av1)->get(route('avaliador.avaliacoes.arquivo', $this->a1))->streamedContent())->toContain('PRINCIPAL-COM-AUTORES');
});

it('trocar só o PDF principal deixa a versão anônima vencida: volta ao principal e avisa', function () {
    $this->w->salvar($this->evento, $this->autor, ['titulo' => 'Trabalho cego'], $this->trabalho, pdfComTexto('P1'), pdfComTexto('ANON1'));
    $this->w->salvar($this->evento, $this->autor, ['titulo' => 'Trabalho cego'], $this->trabalho->fresh(), pdfComTexto('P2-NOVO'));
    $t = $this->trabalho->fresh(['arquivoAtual', 'arquivoAnonimo', 'evento.trabalhoConfig']);
    ($this->cega)(true);

    expect($t->arquivoAnonimoVigente())->toBeNull();
    expect($this->actingAs($this->av1)->get(route('avaliador.avaliacoes.arquivo', $this->a1))->streamedContent())->toContain('P2-NOVO');

    // enviando outra versão anônima ela volta a valer
    $this->w->salvar($this->evento, $this->autor, ['titulo' => 'Trabalho cego'], $t, null, pdfComTexto('ANON2'));
    expect($this->actingAs($this->av1)->get(route('avaliador.avaliacoes.arquivo', $this->a1))->streamedContent())->toContain('ANON2');
});

it('envio novo com avaliação cega exige o PDF sem identificação; rascunho e trabalho antigo não', function () {
    $evento = mkEvento($this->dono);
    TrabalhoConfig::where('evento_id', $evento->id)->update(['avaliacao_cega' => true, 'ativo' => true, 'submissao_inicio' => now()->subDay(), 'submissao_fim' => now()->addDays(5), 'max_autores' => 3, 'resumo_min_palavras' => 20, 'resumo_max_palavras' => 300]);
    $url = route('trabalhos.store', $evento->slug);
    $autor = mkUser();

    // sem o segundo PDF: recusa com mensagem clara
    $this->actingAs($autor)->post($url, dadosCegos())->assertSessionHasErrors(['arquivo_anonimo' => 'Envie também o PDF sem identificação dos autores (avaliação cega).']);
    expect(Trabalho::where('evento_id', $evento->id)->count())->toBe(0);

    // rascunho passa sem nenhum PDF
    $this->actingAs($autor)->post($url, ['acao' => 'rascunho', 'titulo' => 'Rascunho'])->assertSessionHasNoErrors();

    // PDF inválido (não é PDF de verdade)
    $this->actingAs($autor)->post($url, dadosCegos(['titulo' => 'Falso', 'arquivo_anonimo' => UploadedFile::fake()->createWithContent('x.pdf', 'isto não é pdf')]))->assertSessionHasErrors('arquivo_anonimo');

    // com os dois PDFs: grava ambos e o principal segue como arquivo atual
    $this->actingAs($autor)->post($url, dadosCegos(['titulo' => 'Completo', 'arquivo' => pdfComTexto('COMPLETO'), 'arquivo_anonimo' => pdfComTexto('CEGO')]))->assertSessionHasNoErrors();
    $t = Trabalho::where('titulo', 'Completo')->with(['arquivos', 'arquivoAtual'])->firstOrFail();
    expect($t->arquivos)->toHaveCount(2)->and($t->arquivoAtual->anonimo)->toBeFalse()->and($t->arquivoAnonimoVigente()->tipo)->toBe('anonimizado');
});

it('reenvio de trabalho antigo (sem versão cega) não é bloqueado pela exigência nova', function () {
    ($this->cega)(true);
    $this->w->salvar($this->evento, $this->autor, ['titulo' => 'Trabalho cego'], $this->trabalho, pdfComTexto('ANTIGO'));
    $t = $this->trabalho->fresh(['evento.trabalhoConfig', 'arquivoAtual', 'arquivoAnonimo']);
    $t->update(['status' => 'aguardando_correcao', 'prazo_correcao' => now()->addDays(5)]);

    $this->actingAs($this->autor)->put(route('trabalhos.update', $t), dadosCegos(['arquivo' => pdfComTexto('CORRIGIDO')]))->assertSessionHasNoErrors();
    expect($t->fresh()->status)->toBe('resubmetido');
});

it('gestão envia a versão sem identificação de um trabalho antigo; autor e estranho não', function () {
    ($this->cega)(true);
    $this->w->salvar($this->evento, $this->autor, ['titulo' => 'Trabalho cego'], $this->trabalho, pdfComTexto('ORIGINAL'));
    $t = $this->trabalho->fresh();

    $this->actingAs($this->autor)->post(route('gestao.trabalhos.arquivo-anonimo', $t), ['arquivo_anonimo' => pdfComTexto('X')])->assertForbidden();
    $this->actingAs($this->av1)->post(route('gestao.trabalhos.arquivo-anonimo', $t), ['arquivo_anonimo' => pdfComTexto('X')])->assertForbidden();

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.arquivo-anonimo', $t), [])->assertSessionHasErrors('arquivo_anonimo');
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.arquivo-anonimo', $t), ['arquivo_anonimo' => UploadedFile::fake()->createWithContent('y.pdf', 'nao e pdf')])->assertSessionHas('error');
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.arquivo-anonimo', $t), ['arquivo_anonimo' => pdfComTexto('SEM-NOMES')])->assertSessionHas('success');

    expect($this->actingAs($this->av1)->get(route('avaliador.avaliacoes.arquivo', $this->a1))->streamedContent())->toContain('SEM-NOMES');
    expect($this->actingAs($this->gestor)->get(route('gestao.trabalhos.arquivo-anonimo.baixar', $t))->streamedContent())->toContain('SEM-NOMES');
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $t))->assertOk()->assertSee('Versão para avaliação cega')->assertSee('é a que o avaliador abre');
    expect($t->historico()->latest('id')->first()->justificativa ?? $t->historico()->latest('id')->first()->observacao ?? 'ok')->not->toBeNull();
});

it('configuração mostra o modelo sem identificação e avisa quantos trabalhos já enviados não têm a versão cega', function () {
    $this->w->salvar($this->evento, $this->autor, ['titulo' => 'Trabalho cego'], $this->trabalho, pdfComTexto('ORIG'));
    ($this->cega)(true);

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.config', $this->evento))->assertOk()
        ->assertSee('modelo sem identificação')->assertSee('1 trabalho(s) já enviado(s) ainda não têm a versão sem identificação');

    $this->w->anexarArquivoAnonimo($this->trabalho->fresh(), $this->gestor, pdfComTexto('CEGO'));
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.config', $this->evento))->assertDontSee('ainda não têm a versão sem identificação');
});

it('formulário mostra o segundo PDF só quando a avaliação cega está ligada, com o modelo sem identificação', function () {
    $evento = mkEvento($this->dono);
    TrabalhoConfig::where('evento_id', $evento->id)->update(['ativo' => true, 'submissao_inicio' => now()->subDay(), 'submissao_fim' => now()->addDays(5), 'template_anonimo_url' => 'https://exemplo.com/modelo-cego']);
    $url = route('trabalhos.create', $evento->slug);

    $this->actingAs($this->autor)->get($url)->assertOk()->assertDontSee('name="arquivo_anonimo"', false);

    TrabalhoConfig::where('evento_id', $evento->id)->update(['avaliacao_cega' => true]);
    $this->actingAs($this->autor)->get($url)->assertOk()->assertSee('name="arquivo_anonimo"', false)->assertSee('PDF sem identificação dos autores')->assertSee('https://exemplo.com/modelo-cego', false)->assertSee('PDF do trabalho completo, com os autores');
});
