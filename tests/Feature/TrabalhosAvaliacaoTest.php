<?php

use App\Mail\AvaliacaoLembreteMail;
use App\Mail\AvaliadorAtribuidoMail;
use App\Mail\TrabalhoDecisaoMail;
use App\Mail\TrabalhoReenviadoMail;
use App\Models\CriterioAvaliacao;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\TrabalhoAvaliacao;
use App\Models\TrabalhoConfig;
use App\Models\User;
use App\Services\AvaliacaoService;
use App\Services\TrabalhoWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/** Preenche o formulário de avaliação (2 critérios do helper: ids dinâmicos). */
function dadosAvaliacao(Evento $e, array $notas = [10, 5], array $extra = []): array
{
    $ids = $e->criterios()->pluck('id')->all();

    return array_merge([
        'notas' => array_combine($ids, $notas),
        'parecer' => 'Parecer detalhado do avaliador sobre o trabalho.',
        'recomendacao' => 'aprovar',
    ], $extra);
}

function concluirAvaliacao(TrabalhoAvaliacao $a, array $notas = [10, 5], array $extra = []): void
{
    app(AvaliacaoService::class)->concluir($a, $a->avaliador, dadosAvaliacao($a->trabalho->evento, $notas, $extra));
}

beforeEach(function () {
    Mail::fake();
    Storage::fake('trabalhos');

    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->av1 = mkUser();
    $this->av2 = mkUser();
    mkPapel($this->av1, $this->evento, 'avaliador');
    mkPapel($this->av2, $this->evento, 'avaliador');
    $this->autor = mkUser();

    $this->w = app(TrabalhoWorkflow::class);
    $this->svc = app(AvaliacaoService::class);

    // Trabalho já em avaliação com os dois avaliadores atribuídos
    $this->trabalho = mkTrabalho($this->evento, $this->autor, [
        'titulo' => 'Título secreto do trabalho',
        'autores' => [['nome_completo' => 'Fulana Identificável', 'cpf' => '111.444.777-35', 'email' => $this->autor->email, 'tipo_autor' => 'autor_principal', 'apresentador' => true]],
    ], 'em_triagem');
    $this->a1 = $this->w->atribuirAvaliador($this->trabalho, $this->av1, $this->gestor);
    $this->a2 = $this->w->atribuirAvaliador($this->trabalho, $this->av2, $this->gestor);
    $this->w->transicionar($this->trabalho->fresh(), $this->gestor, 'em_avaliacao');
    $this->trabalho = $this->trabalho->fresh();
    Mail::fake(); // zera o que foi enfileirado na preparação
});

// ---------- Tela do avaliador ----------
it('lista somente as avaliações do próprio avaliador', function () {
    $this->actingAs($this->av1)->get(route('avaliador.avaliacoes.index'))->assertOk()->assertSee($this->trabalho->codigo);

    $estranho = mkUser();
    $this->actingAs($estranho)->get(route('avaliador.avaliacoes.index'))->assertOk()->assertDontSee($this->trabalho->codigo);
});

it('outro usuário não abre, salva nem baixa a avaliação alheia', function () {
    $outro = $this->av2;
    $this->actingAs($outro);

    $this->get(route('avaliador.avaliacoes.show', $this->a1))->assertForbidden();
    $this->get(route('avaliador.avaliacoes.arquivo', $this->a1))->assertForbidden();
    $this->post(route('avaliador.avaliacoes.salvar', $this->a1), dadosAvaliacao($this->evento))->assertForbidden();
    $this->post(route('avaliador.avaliacoes.concluir', $this->a1), dadosAvaliacao($this->evento))->assertForbidden();
    $this->post(route('avaliador.avaliacoes.recusar', $this->a1), ['motivo' => 'conflito de interesse'])->assertForbidden();

    $this->actingAs($this->gestor)->get(route('avaliador.avaliacoes.show', $this->a1))->assertForbidden();
});

it('avaliação cega esconde autores e título do arquivo; sem cegueira mostra os autores', function () {
    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['avaliacao_cega' => true]);
    $this->actingAs($this->av1)->get(route('avaliador.avaliacoes.show', $this->a1))
        ->assertOk()->assertSee('Avaliação cega')->assertDontSee('Fulana Identificável')->assertSee('Título secreto do trabalho');

    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['avaliacao_cega' => false]);
    $this->actingAs($this->av1)->get(route('avaliador.avaliacoes.show', $this->a1))->assertOk()->assertSee('Fulana Identificável');
});

it('avaliador baixa o PDF com nome neutro, e não acessa a página que expõe os autores', function () {
    $pdf = "%PDF-1.4\n%%EOF";
    Storage::disk('trabalhos')->put('x/arquivo.pdf', $pdf);
    $this->trabalho->arquivos()->create(['versao' => 1, 'tipo' => 'original', 'path' => 'x/arquivo.pdf', 'nome_original' => 'Autor Fulana.pdf', 'mime' => 'application/pdf', 'tamanho' => strlen($pdf), 'sha256' => hash('sha256', $pdf), 'enviado_por' => $this->autor->id]);

    $r = $this->actingAs($this->av1)->get(route('avaliador.avaliacoes.arquivo', $this->a1))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($r->headers->get('Content-Disposition'))->toContain("trabalho-{$this->trabalho->codigo}.pdf")->not->toContain('Fulana');

    $this->get(route('trabalhos.show', $this->trabalho))->assertForbidden();   // página com identificação dos autores
    $this->get(route('gestao.trabalhos.show', $this->trabalho))->assertForbidden();
});

// ---------- Preenchimento ----------
it('salva rascunho com notas (aceita vírgula decimal) e marca em andamento', function () {
    $ids = $this->evento->criterios()->pluck('id')->all();

    $this->actingAs($this->av1)->post(route('avaliador.avaliacoes.salvar', $this->a1), [
        'notas' => [$ids[0] => '7,5'], 'parecer' => 'rascunho', 'comentarios' => [$ids[0] => 'ok'],
    ])->assertSessionHas('success')->assertSessionHasNoErrors();

    $a = $this->a1->fresh(['notas']);
    expect($a->status)->toBe('em_andamento')->and($a->notas)->toHaveCount(1)->and($a->notas->first()->nota)->toBe(7.5)
        ->and($a->notas->first()->comentario)->toBe('ok')->and($a->nota_final)->toBeNull();
});

it('rejeita nota fora da escala do critério', function (string $nota) {
    $ids = $this->evento->criterios()->pluck('id')->all();

    $this->actingAs($this->av1)->post(route('avaliador.avaliacoes.salvar', $this->a1), ['notas' => [$ids[0] => $nota]])
        ->assertSessionHasErrors("notas.{$ids[0]}");
    expect($this->a1->fresh()->notas)->toHaveCount(0);
})->with(['negativa' => '-1', 'acima do máximo' => '10.5', 'texto' => 'abc']);

it('só conclui com todas as notas e recomendação (parecer é opcional)', function (array $alteracao, string $trecho) {
    $dados = array_merge(dadosAvaliacao($this->evento), $alteracao);
    if (isset($alteracao['notas'])) {
        $ids = $this->evento->criterios()->pluck('id')->all();
        $dados['notas'] = [$ids[0] => 8];
    }

    $this->actingAs($this->av1)->post(route('avaliador.avaliacoes.concluir', $this->a1), $dados)->assertSessionHas('error');
    expect(session('error'))->toContain($trecho);
    expect($this->a1->fresh()->status)->not->toBe('concluida');
})->with([
    'falta nota' => [['notas' => []], 'todos os critérios'],
    'sem recomendação' => [['recomendacao' => ''], 'recomendação'],
]);

it('conclui sem parecer escrito: o parecer é opcional', function () {
    $this->actingAs($this->av1)->post(route('avaliador.avaliacoes.concluir', $this->a1), dadosAvaliacao($this->evento, [8, 8], ['parecer' => '']))
        ->assertSessionHasNoErrors()->assertRedirect(route('avaliador.avaliacoes.index'));

    expect($this->a1->fresh()->status)->toBe('concluida');
});

it('calcula a nota ponderada (pesos 1 e 2: 10 e 5 → 6,67) e conclui', function () {
    $this->actingAs($this->av1)->post(route('avaliador.avaliacoes.concluir', $this->a1), dadosAvaliacao($this->evento, [10, 5]))
        ->assertRedirect(route('avaliador.avaliacoes.index'));

    $a = $this->a1->fresh();
    expect($a->status)->toBe('concluida')->and($a->nota_final)->toBe(6.67)->and($a->concluida_em)->not->toBeNull()
        ->and($a->recomendacao)->toBe('aprovar');
    expect($this->trabalho->fresh()->status)->toBe('em_avaliacao'); // falta o segundo avaliador
});

it('não permite editar depois de concluída nem quando o trabalho saiu da avaliação', function () {
    concluirAvaliacao($this->a1);
    $this->actingAs($this->av1)->post(route('avaliador.avaliacoes.salvar', $this->a1), ['parecer' => 'mudei'])->assertForbidden();
    $this->actingAs($this->av1)->get(route('avaliador.avaliacoes.show', $this->a1))->assertOk()->assertSee('não pode mais ser editada');

    Trabalho::whereKey($this->trabalho->id)->update(['status' => 'avaliado']);
    $this->actingAs($this->av2)->post(route('avaliador.avaliacoes.salvar', $this->a2), ['parecer' => 'tarde demais'])->assertForbidden();
});

it('avaliador recusa a avaliação e a gestão vê o motivo', function () {
    $this->actingAs($this->av2)->post(route('avaliador.avaliacoes.recusar', $this->a2), ['motivo' => 'Conflito de interesse'])->assertRedirect(route('avaliador.avaliacoes.index'));

    expect($this->a2->fresh()->status)->toBe('recusada');
    $this->actingAs($this->av2)->get(route('avaliador.avaliacoes.arquivo', $this->a2))->assertForbidden();
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $this->trabalho))->assertOk()->assertSee('Conflito de interesse');

    $this->actingAs($this->av1)->post(route('avaliador.avaliacoes.recusar', $this->a1), ['motivo' => ''])->assertSessionHasErrors('motivo');
});

// ---------- Consolidação ----------
it('com todos os avaliadores concluídos o trabalho vai para "avaliado" com a nota média', function () {
    concluirAvaliacao($this->a1, [10, 10]);   // 10,00
    concluirAvaliacao($this->a2, [5, 5]);     // 5,00

    $t = $this->trabalho->fresh();
    expect($t->status)->toBe('avaliado')->and($t->nota_final)->toBe(7.5);
});

it('divergência alta é detectada e novo avaliador reabre a avaliação', function () {
    concluirAvaliacao($this->a1, [10, 10]);
    concluirAvaliacao($this->a2, [4, 4]);

    $t = $this->trabalho->fresh();
    expect($this->svc->divergencia($t))->toBe(6.0)->and($this->svc->divergenciaAlta($t))->toBeTrue();

    $av3 = mkUser();
    mkPapel($av3, $this->evento, 'avaliador');
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.avaliadores.store', $t), ['avaliadores' => [$av3->id]])->assertSessionHas('success');

    expect($t->fresh()->status)->toBe('em_avaliacao');
    Mail::assertQueued(AvaliadorAtribuidoMail::class);
});

it('divergência baixa não gera alerta', function () {
    concluirAvaliacao($this->a1, [8, 8]);
    concluirAvaliacao($this->a2, [7, 7]);

    expect($this->svc->divergenciaAlta($this->trabalho->fresh()))->toBeFalse();
});

it('gestão encerra a avaliação antes da hora, mas só com ao menos uma concluída', function () {
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.encerrar', $this->trabalho))->assertSessionHas('error');
    expect($this->trabalho->fresh()->status)->toBe('em_avaliacao');

    concluirAvaliacao($this->a1, [9, 9]);
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.encerrar', $this->trabalho))->assertSessionHas('success');

    $t = $this->trabalho->fresh();
    expect($t->status)->toBe('avaliado')->and($t->nota_final)->toBe(9.0);
});

// ---------- Decisão ----------
function trabalhoAvaliado(): Trabalho
{
    concluirAvaliacao(test()->a1, [9, 9]);
    concluirAvaliacao(test()->a2, [8, 8]);

    return test()->trabalho->fresh();
}

it('aprova, avisa os autores e guarda a decisão', function () {
    $t = trabalhoAvaliado();
    Mail::fake();

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.decisao', $t), ['decisao' => 'aprovado'])->assertSessionHas('success');

    $t->refresh();
    expect($t->status)->toBe('aprovado')->and($t->decisao)->toBe('aprovado')->and($t->decidido_por)->toBe($this->gestor->id)
        ->and($t->nota_final)->toBe(8.5);
    Mail::assertQueued(TrabalhoDecisaoMail::class, fn ($m) => $m->decisao === 'aprovado' && $m->hasTo($this->autor->email));
});

it('exige mensagem para reprovar e mensagem + prazo futuro para ressalvas', function () {
    $t = trabalhoAvaliado();
    $url = route('gestao.trabalhos.decisao', $t);
    $this->actingAs($this->gestor);

    $this->post($url, ['decisao' => 'reprovado'])->assertSessionHas('error');
    $this->post($url, ['decisao' => 'aprovado_com_ressalvas', 'justificativa' => 'Ajustar a metodologia.'])->assertSessionHas('error');
    $this->post($url, ['decisao' => 'aprovado_com_ressalvas', 'justificativa' => 'x', 'prazo_correcao' => now()->subDay()->format('Y-m-d')])->assertSessionHasErrors('prazo_correcao');
    $this->post($url, ['decisao' => 'inexistente'])->assertSessionHasErrors('decisao');

    expect($t->fresh()->status)->toBe('avaliado');
});

it('só decide trabalho avaliado ou reenviado', function () {
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.decisao', $this->trabalho), ['decisao' => 'aprovado'])->assertSessionHas('error');
    expect($this->trabalho->fresh()->status)->toBe('em_avaliacao');
});

it('somente quem gere trabalhos decide', function () {
    $t = trabalhoAvaliado();

    foreach ([$this->av1, $this->autor, mkUser('admin')] as $u) {
        $this->actingAs($u)->post(route('gestao.trabalhos.decisao', $t), ['decisao' => 'aprovado'])->assertForbidden();
        $this->actingAs($u)->post(route('gestao.trabalhos.encerrar', $t))->assertForbidden();
    }
    expect($t->fresh()->status)->toBe('avaliado');
});

// ---------- O que o autor enxerga ----------
it('autor só vê parecer depois da decisão, sem identificar avaliadores nem registros internos', function () {
    $t = trabalhoAvaliado();

    $this->actingAs($this->autor)->get(route('trabalhos.show', $t))->assertOk()
        ->assertDontSee('Parecer detalhado do avaliador')->assertDontSee('Resultado da avaliação');

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.decisao', $t), ['decisao' => 'aprovado', 'justificativa' => 'Parabéns pelo trabalho!']);

    $pagina = $this->actingAs($this->autor)->get(route('trabalhos.show', $t->fresh()))->assertOk()
        ->assertSee('Resultado da avaliação')->assertSee('Parecer detalhado do avaliador')->assertSee('Parabéns pelo trabalho!')->assertSee('8,50');

    foreach ([$this->av1->name, $this->av2->name, $this->gestor->name, 'Avaliador atribuído', 'Avaliação concluída'] as $sigiloso) {
        $pagina->assertDontSee($sigiloso);
    }
});

it('respeita a configuração de ocultar nota e pareceres do autor', function () {
    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['autor_ve_notas' => false, 'autor_ve_pareceres' => false]);
    $t = trabalhoAvaliado();
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.decisao', $t), ['decisao' => 'aprovado']);

    $this->actingAs($this->autor)->get(route('trabalhos.show', $t->fresh()))->assertOk()
        ->assertSee('Resultado da avaliação')->assertDontSee('Parecer detalhado do avaliador')->assertDontSee('Nota final');
});

it('parecer confidencial nunca chega ao autor, mas a gestão vê', function () {
    concluirAvaliacao($this->a1, [9, 9], ['parecer_confidencial' => 'Suspeita de plágio parcial']);
    concluirAvaliacao($this->a2, [8, 8]);
    $t = $this->trabalho->fresh();
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.decisao', $t), ['decisao' => 'aprovado']);

    $this->actingAs($this->autor)->get(route('trabalhos.show', $t->fresh()))->assertDontSee('Suspeita de plágio');
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $t->fresh()))->assertSee('Suspeita de plágio parcial');
});

// ---------- Aprovado com ressalvas: segue o fluxo e a versão final é enviada no fim ----------
it('aprovado com ressalvas segue o fluxo (sem nova aprovação) e o autor envia a versão final', function () {
    $t = trabalhoAvaliado();
    Mail::fake();

    // 1. gestão aprova com ressalvas: o trabalho já fica aprovado, com a versão final pendente
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.decisao', $t), [
        'decisao' => 'aprovado_com_ressalvas', 'justificativa' => 'Reforçar a metodologia.', 'prazo_correcao' => now()->addDays(7)->format('Y-m-d'),
    ])->assertSessionHas('success');
    $t->refresh();
    expect($t->status)->toBe('aprovado')->and($t->versao_final_pendente)->toBeTrue()->and($t->prazo_correcao->isFuture())->toBeTrue();
    Mail::assertQueued(TrabalhoDecisaoMail::class, fn ($m) => $m->decisao === 'aprovado_com_ressalvas');

    // 2. quem não é autor não envia; PDF inválido é recusado
    $this->actingAs($this->av1)->post(route('trabalhos.versao-final', $t), ['arquivo' => UploadedFile::fake()->createWithContent('v2.pdf', "%PDF-1.4\n%%EOF")])->assertForbidden();
    $this->actingAs($this->autor)->post(route('trabalhos.versao-final', $t), ['arquivo' => UploadedFile::fake()->createWithContent('falso.pdf', 'texto qualquer')])->assertSessionHas('error');
    expect($t->fresh()->versao_final_pendente)->toBeTrue();

    // 3. o autor envia a versão final: não volta para a gestão aprovar de novo
    $this->actingAs($this->autor)->post(route('trabalhos.versao-final', $t), ['arquivo' => UploadedFile::fake()->createWithContent('v2.pdf', "%PDF-1.4\n%%EOF")])->assertSessionHas('success');
    $t->refresh();
    expect($t->status)->toBe('aprovado')->and($t->versao_final_pendente)->toBeFalse()->and($t->versao_final_enviada_em)->not->toBeNull()
        ->and($t->arquivoAtual->tipo)->toBe('corrigido');
    Mail::assertQueued(TrabalhoReenviadoMail::class);

    // 4. não dá para enviar de novo
    $this->actingAs($this->autor)->post(route('trabalhos.versao-final', $t), ['arquivo' => UploadedFile::fake()->createWithContent('v3.pdf', "%PDF-1.4\n%%EOF")])->assertSessionHas('error');
});

it('trabalho com versão final pendente fica fora dos Trabalhos Publicados até o autor enviar', function () {
    $t = trabalhoAvaliado();
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.decisao', $t), [
        'decisao' => 'aprovado_com_ressalvas', 'justificativa' => 'Ajustar.', 'prazo_correcao' => now()->addDays(3)->format('Y-m-d'),
    ]);
    $t->refresh()->update(['apresentado' => true, 'autoriza_publicacao' => true]);

    expect(\App\Models\Anais::elegiveis($t->evento)->whereKey($t->id)->exists())->toBeFalse();
    $t->update(['versao_final_pendente' => false]);
    expect(\App\Models\Anais::elegiveis($t->evento)->whereKey($t->id)->exists())->toBeTrue();
});

it('só reavalia trabalho reenviado', function () {
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.reavaliar', $this->trabalho))->assertSessionHas('error');
});

it('prazo da versão final vencido bloqueia o envio até a gestão prorrogar', function () {
    $t = trabalhoAvaliado();
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.decisao', $t), [
        'decisao' => 'aprovado_com_ressalvas', 'justificativa' => 'Ajustar.', 'prazo_correcao' => now()->addDay()->format('Y-m-d'),
    ]);
    Trabalho::whereKey($t->id)->update(['prazo_correcao' => now()->subHour()]);
    $pdf = fn () => UploadedFile::fake()->createWithContent('f.pdf', "%PDF-1.4\n%%EOF");

    $this->actingAs($this->autor)->post(route('trabalhos.versao-final', $t), ['arquivo' => $pdf()])->assertSessionHas('error');
    expect($t->fresh()->versao_final_pendente)->toBeTrue();

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.prorrogar', $t), ['prazo_correcao' => now()->addDays(3)->format('Y-m-d')])->assertSessionHas('success');
    $this->actingAs($this->autor)->post(route('trabalhos.versao-final', $t), ['arquivo' => $pdf()])->assertSessionHas('success');

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.prorrogar', $t), ['prazo_correcao' => now()->subDay()->format('Y-m-d')])->assertSessionHasErrors('prazo_correcao');
});

// ---------- Critérios ----------
it('exige critérios para encaminhar à avaliação', function () {
    $e = mkEvento($this->dono);
    mkPapel($this->gestor, $e, 'gestor_trabalhos');
    mkPapel($this->av1, $e, 'avaliador');
    $e->criterios()->delete();

    $t = mkTrabalho($e, $this->autor, [], 'em_triagem');
    $this->w->atribuirAvaliador($t, $this->av1, $this->gestor);

    expect(fn () => $this->w->transicionar($t->fresh(), $this->gestor, 'em_avaliacao'))->toThrow(DomainException::class, 'critérios');
});

it('configura critérios (criar, editar, remover); critérios com notas ficam fixos e são desativados ao remover', function () {
    $base = ['max_autores' => 5, 'max_arquivo_mb' => 10, 'resumo_min_palavras' => 50, 'resumo_max_palavras' => 250, 'tipos_permitidos' => ['pesquisa'],
        'avaliadores_por_trabalho' => 2, 'nota_minima_aprovacao' => 7, 'divergencia_maxima' => 3];
    $url = route('gestao.trabalhos.config.update', $this->evento);
    $this->actingAs($this->gestor);

    $existentes = $this->evento->criterios()->get();
    $this->a1->notas()->delete();

    // sem notas lançadas: edita um, remove o outro, cria um novo
    $this->put($url, $base + ['criterios' => [
        ['id' => $existentes[0]->id, 'nome' => 'Conteúdo (novo nome)', 'peso' => 3, 'nota_max' => 5],
        ['nome' => 'Apresentação', 'peso' => 1, 'nota_max' => 10],
    ]])->assertSessionHasNoErrors();

    $lista = $this->evento->criterios()->get();
    expect($lista->pluck('nome')->all())->toBe(['Conteúdo (novo nome)', 'Apresentação'])
        ->and($lista->first()->peso)->toBe(3.0)->and($lista->first()->id)->toBe($existentes[0]->id);

    // com nota lançada: o critério usado fica fixo (nome/peso/máx.), pode ser removido (desativado) e novos podem ser criados
    $usado = $lista[0];
    $this->a1->notas()->create(['criterio_id' => $usado->id, 'nota' => 4]);

    // 1) tentar alterar nome/peso do critério usado: ignorado, descrição/ordem passam
    $this->put($url, $base + ['criterios' => [
        ['id' => $usado->id, 'nome' => 'Tentativa', 'peso' => 9, 'nota_max' => 1, 'descricao' => 'nova descrição'],
        ['id' => $lista[1]->id, 'nome' => 'Apresentação', 'peso' => 1, 'nota_max' => 10],
    ]])->assertSessionHasNoErrors();
    $usado->refresh();
    expect($usado->nome)->toBe('Conteúdo (novo nome)')->and($usado->peso)->toBe(3.0)->and($usado->descricao)->toBe('nova descrição');

    // 2) remover o critério usado: fica desativado (as notas continuam), o não usado é apagado, um novo entra
    $this->put($url, $base + ['criterios' => [['nome' => 'Clareza', 'peso' => 1, 'nota_max' => 10]]])->assertSessionHas('warning');
    expect($this->evento->criterios()->pluck('nome', 'ativo')->all())->toHaveKey(0)   // o desativado
        ->and($this->evento->criterios()->where('ativo', true)->pluck('nome')->all())->toBe(['Clareza'])
        ->and(\App\Models\CriterioAvaliacao::whereKey($usado->id)->value('ativo'))->toBeFalse()
        ->and(\App\Models\CriterioAvaliacao::whereKey($lista[1]->id)->exists())->toBeFalse()
        ->and($this->a1->notas()->where('criterio_id', $usado->id)->exists())->toBeTrue();

    // 3) a tela não lista o desativado e marca os usados com cadeado
    $this->get(route('gestao.trabalhos.config', $this->evento))->assertOk()->assertSee('Clareza', false)->assertDontSee('(novo nome)', false);
});

it('valida critérios inválidos na configuração', function () {
    $base = ['max_autores' => 5, 'max_arquivo_mb' => 10, 'resumo_min_palavras' => 50, 'resumo_max_palavras' => 250, 'tipos_permitidos' => ['pesquisa'],
        'avaliadores_por_trabalho' => 2, 'nota_minima_aprovacao' => 7, 'divergencia_maxima' => 3];

    $this->actingAs($this->gestor)->put(route('gestao.trabalhos.config.update', $this->evento), $base + ['criterios' => [['nome' => '', 'peso' => 0, 'nota_max' => -1]]])
        ->assertSessionHasErrors(['criterios.0.nome', 'criterios.0.peso', 'criterios.0.nota_max']);
});

// ---------- Painel da gestão ----------
it('painel da gestão mostra pareceres, notas por critério e a sugestão de decisão', function () {
    $t = trabalhoAvaliado();

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $t))->assertOk()
        ->assertSee('Resultado das avaliações')->assertSee('Decisão final')->assertSee($this->av1->name)->assertSee('Parecer detalhado do avaliador')
        ->assertSee('Sugestão pela nota: aprovar');
});

// ---------- Lembretes ----------
it('lembra avaliadores com prazo próximo ou vencido, no máximo uma vez por dia', function () {
    $this->a1->update(['prazo' => now()->addDay()]);       // vence amanhã
    $this->a2->update(['prazo' => now()->addDays(10)]);    // longe

    $this->artisan('trabalhos:lembrar-avaliadores')->assertSuccessful();
    Mail::assertQueued(AvaliacaoLembreteMail::class, 1);
    Mail::assertQueued(AvaliacaoLembreteMail::class, fn ($m) => $m->hasTo($this->av1->email));

    $this->artisan('trabalhos:lembrar-avaliadores')->assertSuccessful();   // mesmo dia: não repete
    Mail::assertQueued(AvaliacaoLembreteMail::class, 1);

    $this->a1->update(['lembrete_enviado_em' => now()->subDay()]);         // no dia seguinte envia de novo
    $this->artisan('trabalhos:lembrar-avaliadores');
    Mail::assertQueued(AvaliacaoLembreteMail::class, 2);
});

it('não lembra quem já concluiu, nem trabalhos fora da avaliação', function () {
    $this->a1->update(['prazo' => now()->subDay()]);
    concluirAvaliacao($this->a1);
    $this->a2->update(['prazo' => now()->subDay()]);
    Trabalho::whereKey($this->trabalho->id)->update(['status' => 'avaliado']);

    $this->artisan('trabalhos:lembrar-avaliadores')->assertSuccessful();
    Mail::assertNotQueued(AvaliacaoLembreteMail::class);
});

// ---------- Meus Trabalhos: eventos com chamada ----------
it('Meus Trabalhos lista eventos com chamada e o botão de submeter', function () {
    $this->actingAs($this->autor)->get(route('trabalhos.index'))->assertOk()
        ->assertSee($this->evento->titulo)->assertSee('Chamada aberta')
        ->assertSee(route('trabalhos.create', $this->evento->slug), false);
});

it('Meus Trabalhos desabilita a submissão em chamada encerrada, em breve ou com limite atingido', function () {
    $this->actingAs($this->autor);

    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['submissao_fim' => now()->subDay()]);
    $this->get(route('trabalhos.index'))->assertOk()->assertSee('Encerrada')->assertDontSee(route('trabalhos.create', $this->evento->slug), false);

    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['submissao_inicio' => now()->addDay(), 'submissao_fim' => now()->addDays(5)]);
    $this->get(route('trabalhos.index'))->assertSee('Em breve');

    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['submissao_inicio' => now()->subDay(), 'max_trabalhos_por_usuario' => 1]);
    $this->get(route('trabalhos.index'))->assertSee('Limite de trabalhos atingido');
});

it('Meus Trabalhos não lista eventos sem chamada ativa', function () {
    $semChamada = Evento::create(['titulo' => 'Evento sem chamada', 'slug' => 's-' . uniqid(), 'data_inicio' => now(), 'data_fim' => now()->addDay(), 'local' => 'Z', 'criado_por' => $this->dono->id]);
    $inativa = mkEvento($this->dono);
    $inativa->update(['titulo' => 'Chamada desativada']);
    TrabalhoConfig::where('evento_id', $inativa->id)->update(['ativo' => false]);

    $this->actingAs($this->autor)->get(route('trabalhos.index'))->assertOk()
        ->assertDontSee('Evento sem chamada')->assertDontSee('Chamada desativada')->assertSee($this->evento->titulo);
});
