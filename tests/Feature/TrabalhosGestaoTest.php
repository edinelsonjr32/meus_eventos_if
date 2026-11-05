<?php

use App\Exports\TrabalhosExport;
use App\Mail\AvaliadorAtribuidoMail;
use App\Mail\TrabalhoStatusMail;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use App\Models\User;
use App\Services\TrabalhoWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

function mkUser(string $role = 'user', ?string $email = null): User
{
    $u = User::factory()->create($email ? ['email' => $email] : []);
    $u->forceFill(['role' => $role])->save();

    return $u;
}

function mkPapel(User $u, Evento $e, string $papel): void
{
    DB::table('evento_user')->insert(['evento_id' => $e->id, 'user_id' => $u->id, 'role' => $papel, 'created_at' => now(), 'updated_at' => now()]);
}

function mkEvento(User $dono): Evento
{
    $e = Evento::create(['titulo' => 'Evento ' . uniqid(), 'slug' => 'e-' . uniqid(), 'data_inicio' => now(), 'data_fim' => now()->addDays(3), 'local' => 'X', 'criado_por' => $dono->id]);
    TrabalhoConfig::create(['evento_id' => $e->id, 'ativo' => true, 'submissao_inicio' => now()->subDay(), 'submissao_fim' => now()->addDays(5), 'avaliadores_por_trabalho' => 2]);

    return $e;
}

/** Cria e submete um trabalho pelo serviço (sem passar pelo formulário). */
function mkTrabalho(Evento $e, User $autor, array $extra = [], string $status = 'submetido'): Trabalho
{
    $w = app(TrabalhoWorkflow::class);
    $t = $w->salvar($e, $autor, array_merge([
        'titulo' => 'Trabalho ' . uniqid(), 'resumo' => 'Resumo', 'palavras_chave' => 'a, b, c', 'tipo_trabalho' => 'resumo',
        'modalidade_apresentacao' => 'oral',
        'autores' => [['nome_completo' => 'Autor ' . uniqid(), 'cpf' => '111.444.777-35', 'email' => $autor->email, 'tipo_autor' => 'autor_principal', 'apresentador' => true]],
    ], $extra));
    $w->submeter($t, $autor);

    if ($status !== 'submetido') {
        $t->update(['status' => $status]);
    }

    return $t->fresh();
}

beforeEach(function () {
    Mail::fake();
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->av1 = mkUser();
    $this->av2 = mkUser();
    mkPapel($this->av1, $this->evento, 'avaliador');
    mkPapel($this->av2, $this->evento, 'avaliador');
    $this->autor = mkUser();
    $this->trabalho = mkTrabalho($this->evento, $this->autor);
    $this->w = app(TrabalhoWorkflow::class);
});

// ---------- Triagem ----------
it('conduz o trabalho da triagem até a avaliação, exigindo avaliador atribuído', function () {
    $url = route('gestao.trabalhos.triagem', $this->trabalho);
    $this->actingAs($this->gestor);

    $this->post($url, ['para' => 'em_triagem'])->assertSessionHas('success');
    expect($this->trabalho->fresh()->status)->toBe('em_triagem');

    $this->post($url, ['para' => 'em_avaliacao'])->assertSessionHas('error'); // sem avaliador
    expect($this->trabalho->fresh()->status)->toBe('em_triagem');

    $this->post(route('gestao.trabalhos.avaliadores.store', $this->trabalho), ['avaliadores' => [$this->av1->id]])->assertSessionHas('success');
    $this->post($url, ['para' => 'em_avaliacao'])->assertSessionHas('success');

    $t = $this->trabalho->fresh();
    expect($t->status)->toBe('em_avaliacao')
        ->and($t->historico->pluck('para')->all())->toContain('em_triagem', 'em_avaliacao');
});

it('rejeita transições fora da matriz', function (string $de, string $para) {
    $this->trabalho->update(['status' => $de]);

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.triagem', $this->trabalho), ['para' => $para, 'justificativa' => 'x'])
        ->assertSessionHas('error');

    expect($this->trabalho->fresh()->status)->toBe($de);
})->with([
    'pular a triagem' => ['submetido', 'em_avaliacao'],
    'devolver sem triar' => ['submetido', 'devolvido_triagem'],
    'rejeitado é final' => ['rejeitado_triagem', 'em_triagem'],
    'retirado é final' => ['retirado', 'em_triagem'],
    'aprovado não volta' => ['aprovado', 'em_triagem'],
]);

it('exige justificativa para devolver e para rejeitar', function (string $para) {
    $this->trabalho->update(['status' => 'em_triagem']);

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.triagem', $this->trabalho), ['para' => $para, 'justificativa' => '   '])
        ->assertSessionHas('error');

    expect($this->trabalho->fresh()->status)->toBe('em_triagem');
    Mail::assertNotQueued(TrabalhoStatusMail::class);
})->with(['devolvido_triagem', 'rejeitado_triagem']);

it('devolução avisa os autores e libera a edição mesmo com a chamada encerrada', function () {
    $this->trabalho->update(['status' => 'em_triagem']);

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.triagem', $this->trabalho), [
        'para' => 'devolvido_triagem', 'justificativa' => 'Falta o PDF anonimizado.',
    ])->assertSessionHas('success');

    Mail::assertQueued(TrabalhoStatusMail::class, fn ($m) => $m->podeEditar && $m->justificativa === 'Falta o PDF anonimizado.' && $m->hasTo($this->autor->email));

    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['submissao_fim' => now()->subDay()]);

    $this->actingAs($this->autor)->get(route('trabalhos.edit', $this->trabalho))->assertOk();
});

it('rejeição na triagem avisa os autores e é definitiva', function () {
    $this->trabalho->update(['status' => 'em_triagem']);

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.triagem', $this->trabalho), ['para' => 'rejeitado_triagem', 'justificativa' => 'Fora do escopo.']);

    expect($this->trabalho->fresh()->status)->toBe('rejeitado_triagem');
    Mail::assertQueued(TrabalhoStatusMail::class, fn ($m) => !$m->podeEditar);

    $this->actingAs($this->autor)->get(route('trabalhos.edit', $this->trabalho))->assertForbidden();
});

// ---------- Avaliadores ----------
it('atribui avaliadores, avisa por e-mail e registra no histórico', function () {
    $this->trabalho->update(['status' => 'em_triagem']);

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.avaliadores.store', $this->trabalho), [
        'avaliadores' => [$this->av1->id, $this->av2->id], 'prazo' => now()->addDays(10)->format('Y-m-d'),
    ])->assertSessionHas('success');

    $t = $this->trabalho->fresh(['avaliacoes']);
    expect($t->avaliacoes)->toHaveCount(2)
        ->and($t->avaliacoes->first()->status)->toBe('pendente')
        ->and($t->avaliacoes->first()->prazo->isFuture())->toBeTrue()
        ->and($t->historico->pluck('justificativa')->implode('|'))->toContain($this->av1->name);

    Mail::assertQueued(AvaliadorAtribuidoMail::class, 2);
});

it('usa o prazo padrão da configuração quando nenhum é informado', function () {
    $prazo = now()->addDays(20)->startOfMinute();
    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['avaliacao_fim' => $prazo]);
    $this->trabalho->update(['status' => 'em_triagem']);

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.avaliadores.store', $this->trabalho), ['avaliadores' => [$this->av1->id]]);

    expect($this->trabalho->avaliacoes()->first()->prazo->equalTo($prazo))->toBeTrue();
});

it('só atribui em triagem ou avaliação', function () {
    // status "submetido" (ainda não triado)
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.avaliadores.store', $this->trabalho), ['avaliadores' => [$this->av1->id]])
        ->assertSessionHas('error');

    expect($this->trabalho->avaliacoes()->count())->toBe(0);
});

it('bloqueia atribuição de quem não é avaliador do evento e de duplicados', function () {
    $this->trabalho->update(['status' => 'em_triagem']);
    $comum = mkUser();

    expect(fn () => $this->w->atribuirAvaliador($this->trabalho, $comum, $this->gestor))->toThrow(DomainException::class, 'não é avaliador');

    $this->w->atribuirAvaliador($this->trabalho, $this->av1, $this->gestor);
    expect(fn () => $this->w->atribuirAvaliador($this->trabalho, $this->av1, $this->gestor))->toThrow(DomainException::class, 'já está atribuído');
});

it('bloqueia conflito de interesse por submissão, e-mail e CPF', function () {
    $this->trabalho->update(['status' => 'em_triagem']);

    // 1. o próprio submetente é avaliador
    mkPapel($this->autor, $this->evento, 'avaliador');
    expect($this->w->conflitoDeInteresse($this->trabalho, $this->autor))->toContain('responsável');

    // 2. avaliador cujo e-mail consta entre os autores
    $coautor = mkUser('user', 'coautor@exemplo.com');
    mkPapel($coautor, $this->evento, 'avaliador');
    $t2 = mkTrabalho($this->evento, $this->autor, ['autores' => [
        ['nome_completo' => 'A', 'cpf' => '111.444.777-35', 'email' => $this->autor->email, 'tipo_autor' => 'autor_principal', 'apresentador' => true],
        ['nome_completo' => 'B', 'cpf' => '529.982.247-25', 'email' => 'COAUTOR@exemplo.com', 'tipo_autor' => 'coautor'],
    ]], 'em_triagem');
    expect($this->w->conflitoDeInteresse($t2, $coautor))->toContain('e-mail');
    expect(fn () => $this->w->atribuirAvaliador($t2, $coautor, $this->gestor))->toThrow(DomainException::class, 'Conflito de interesse');

    // 3. avaliador com participante de mesmo CPF de um autor
    Participante::create(['user_id' => $this->av2->id, 'nome_completo' => 'Av2', 'cpf' => '529.982.247-25', 'email' => 'outro@x.com', 'tipo_vinculo' => 'servidor']);
    expect($this->w->conflitoDeInteresse($t2->fresh(), $this->av2->fresh()))->toContain('CPF');

    // 4. sem conflito
    expect($this->w->conflitoDeInteresse($this->trabalho, $this->av1))->toBeNull();
});

it('remove avaliação pendente, mas não uma já iniciada', function () {
    $this->trabalho->update(['status' => 'em_triagem']);
    $a1 = $this->w->atribuirAvaliador($this->trabalho, $this->av1, $this->gestor);
    $a2 = $this->w->atribuirAvaliador($this->trabalho, $this->av2, $this->gestor);
    $a2->update(['status' => 'em_andamento']);

    $this->actingAs($this->gestor)->delete(route('gestao.trabalhos.avaliadores.destroy', [$this->trabalho, $a1]))->assertSessionHas('success');
    $this->actingAs($this->gestor)->delete(route('gestao.trabalhos.avaliadores.destroy', [$this->trabalho, $a2]))->assertSessionHas('error');

    expect($this->trabalho->avaliacoes()->pluck('avaliador_id')->all())->toBe([$this->av2->id]);
});

it('não remove avaliação de outro trabalho pela rota', function () {
    $outro = mkTrabalho($this->evento, $this->autor, [], 'em_triagem');
    $a = $this->w->atribuirAvaliador($outro, $this->av1, $this->gestor);

    $this->actingAs($this->gestor)->delete(route('gestao.trabalhos.avaliadores.destroy', [$this->trabalho, $a]))->assertNotFound();
});

it('mostra a carga de cada avaliador, menor primeiro', function () {
    $this->trabalho->update(['status' => 'em_triagem']);
    $this->w->atribuirAvaliador($this->trabalho, $this->av1, $this->gestor);

    $lista = $this->w->avaliadoresDoEvento($this->evento);
    expect($lista->first()->id)->toBe($this->av2->id)->and($lista->first()->carga)->toBe(0)
        ->and($lista->last()->carga)->toBe(1);
});

// ---------- Lote ----------
it('atribui avaliadores em lote ignorando trabalhos de outro evento', function () {
    $t2 = mkTrabalho($this->evento, $this->autor, [], 'em_triagem');
    $this->trabalho->update(['status' => 'em_triagem']);

    $outroEvento = mkEvento($this->dono);
    $estranho = mkTrabalho($outroEvento, $this->autor, [], 'em_triagem');

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.lote', $this->evento), [
        'acao' => 'atribuir', 'trabalhos' => [$this->trabalho->id, $t2->id, $estranho->id], 'avaliadores' => [$this->av1->id],
    ])->assertSessionHas('success');

    expect($this->trabalho->avaliacoes()->count())->toBe(1)->and($t2->avaliacoes()->count())->toBe(1)
        ->and($estranho->avaliacoes()->count())->toBe(0);
});

it('inicia a triagem em lote e informa os que não puderam avançar', function () {
    $ja = mkTrabalho($this->evento, $this->autor, [], 'em_avaliacao');

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.lote', $this->evento), [
        'acao' => 'triagem', 'trabalhos' => [$this->trabalho->id, $ja->id],
    ])->assertSessionHas('success')->assertSessionHas('erros_lote');

    expect($this->trabalho->fresh()->status)->toBe('em_triagem')->and($ja->fresh()->status)->toBe('em_avaliacao');
});

// ---------- Autorização ----------
it('apenas quem gere trabalhos do evento usa as ações de gestão', function (string $quem) {
    $usuario = match ($quem) {
        'avaliador' => $this->av1,
        'autor' => $this->autor,
        'gestor de outro evento' => tap(mkUser(), fn ($u) => mkPapel($u, mkEvento($this->dono), 'gestor_trabalhos')),
        'admin de outro evento' => mkUser('admin'),
    };
    $this->actingAs($usuario);

    $this->post(route('gestao.trabalhos.triagem', $this->trabalho), ['para' => 'em_triagem'])->assertForbidden();
    $this->post(route('gestao.trabalhos.avaliadores.store', $this->trabalho), ['avaliadores' => [$this->av1->id]])->assertForbidden();
    $this->post(route('gestao.trabalhos.lote', $this->evento), ['acao' => 'triagem', 'trabalhos' => [$this->trabalho->id]])->assertForbidden();
    $this->get(route('gestao.trabalhos.exportar', $this->evento))->assertForbidden();

    expect($this->trabalho->fresh()->status)->toBe('submetido');
})->with(['avaliador', 'autor', 'gestor de outro evento', 'admin de outro evento']);

it('dono e co-administrador também gerem a triagem', function () {
    $this->actingAs($this->dono)->post(route('gestao.trabalhos.triagem', $this->trabalho), ['para' => 'em_triagem'])->assertSessionHas('success');

    $co = mkUser('admin');
    mkPapel($co, $this->evento, 'editor');
    Trabalho::whereKey($this->trabalho->id)->update(['status' => 'submetido']);
    $this->actingAs($co)->post(route('gestao.trabalhos.triagem', $this->trabalho), ['para' => 'em_triagem'])->assertSessionHas('success');
});

// ---------- Painel e fila ----------
it('mostra o painel de triagem e avaliadores ao gestor, não ao autor', function () {
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $this->trabalho))
        ->assertOk()->assertSee('Triagem')->assertSee('Iniciar triagem')->assertSee('Avaliadores');

    $this->actingAs($this->autor)->get(route('trabalhos.show', $this->trabalho))
        ->assertOk()->assertDontSee('Iniciar triagem');
});

it('marca avaliador em conflito como indisponível no painel', function () {
    $this->trabalho->update(['status' => 'em_triagem']);
    mkPapel($this->autor, $this->evento, 'avaliador');

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $this->trabalho))
        ->assertOk()->assertSee('conflito');
});

// ---------- Exportação ----------
it('exporta apenas trabalhos submetidos, respeitando filtros', function () {
    Excel::fake();
    Excel::matchByRegex();
    $rascunho = $this->w->salvar($this->evento, $this->autor, ['titulo' => 'Rascunho oculto', 'autores' => []]);
    $outro = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Outro tipo', 'tipo_trabalho' => 'artigo_completo']);

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.exportar', ['evento' => $this->evento, 'formato' => 'xlsx', 'tipo' => 'artigo_completo']))->assertOk();

    Excel::assertDownloaded(
        '/^trabalhos-.*\.xlsx$/',
        fn (TrabalhosExport $export) => $export->collection()->pluck('id')->all() === [$outro->id]
    );

    $this->get(route('gestao.trabalhos.exportar', ['evento' => $this->evento, 'formato' => 'csv']))->assertOk();
    Excel::assertDownloaded(
        '/^trabalhos-.*\.csv$/',
        fn (TrabalhosExport $export) => $export->collection()->pluck('id')->sort()->values()->all() === collect([$this->trabalho->id, $outro->id])->sort()->values()->all()
            && !$export->collection()->pluck('id')->contains($rascunho->id)
    );
});

it('gera o arquivo CSV de verdade com as colunas esperadas', function () {
    $resposta = $this->actingAs($this->gestor)->get(route('gestao.trabalhos.exportar', ['evento' => $this->evento, 'formato' => 'csv']));
    $resposta->assertOk();

    $conteudo = file_get_contents($resposta->baseResponse->getFile()->getPathname());
    expect($conteudo)->toContain('Protocolo')->toContain($this->trabalho->codigo)->toContain($this->trabalho->titulo);
});

it('neutraliza injeção de fórmulas na exportação', function () {
    $t = mkTrabalho($this->evento, $this->autor, ['titulo' => '=HYPERLINK("http://x","clique")']);
    $linha = (new TrabalhosExport(collect([$t->load(['autores', 'area', 'avaliacoes'])])))->map($t);

    expect($linha[1])->toBe("'=HYPERLINK(\"http://x\",\"clique\")")
        ->and(TrabalhosExport::seguro('+55 91'))->toBe("'+55 91")
        ->and(TrabalhosExport::seguro('@cmd'))->toBe("'@cmd")
        ->and(TrabalhosExport::seguro('Texto normal'))->toBe('Texto normal')
        ->and(TrabalhosExport::seguro(10))->toBe(10);
});

// ---------- Configuração ----------
it('valida e salva os novos campos de avaliação da chamada', function () {
    $base = ['max_autores' => 5, 'max_arquivo_mb' => 10, 'resumo_min_palavras' => 50, 'resumo_max_palavras' => 250, 'tipos_permitidos' => ['resumo']];

    $this->actingAs($this->gestor)->put(route('gestao.trabalhos.config.update', $this->evento), array_merge($base, ['avaliadores_por_trabalho' => 0]))
        ->assertSessionHasErrors('avaliadores_por_trabalho');

    $this->actingAs($this->gestor)->put(route('gestao.trabalhos.config.update', $this->evento), array_merge($base, ['avaliadores_por_trabalho' => 3, 'avaliacao_fim' => '2026-12-20T18:00']))
        ->assertSessionHasNoErrors();

    $c = TrabalhoConfig::where('evento_id', $this->evento->id)->first();
    expect($c->avaliadores_por_trabalho)->toBe(3)->and($c->avaliacao_fim->format('Y-m-d'))->toBe('2026-12-20');
});
