<?php

use App\Mail\CertificadoTrabalhoMail;
use App\Models\Atividade;
use App\Models\CertificadoTrabalho;
use App\Models\Evento;
use App\Models\Frequencia;
use App\Models\Participante;
use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use App\Services\ApresentacaoService;
use App\Services\CertificadoTrabalhoService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

function mkAtividade(Evento $e, string $titulo = 'Sessão de Pôsteres'): Atividade
{
    return Atividade::create([
        'evento_id' => $e->id, 'titulo' => $titulo, 'tipo' => 'outro', 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHours(2),
        'carga_horaria' => 2, 'vagas' => 50, 'token_frequencia' => uniqid(),
    ]);
}

function presenca(Participante $p, Atividade $a): Frequencia
{
    return Frequencia::create(['participante_id' => $p->id, 'atividade_id' => $a->id, 'data_registro' => now(), 'hash_validacao' => uniqid(), 'tipo_participacao' => 'palestrante']);
}

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('certificado');

    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->autorUser = mkUser();

    $this->trabalho = mkTrabalho($this->evento, $this->autorUser, [
        'titulo' => 'Robótica educacional',
        'autores' => [
            ['nome_completo' => 'Ana Principal', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => true],
            ['nome_completo' => 'Caio Coautor', 'cpf' => '529.982.247-25', 'email' => 'caio@x.com', 'tipo_autor' => 'coautor', 'apresentador' => false],
            ['nome_completo' => 'Olga Orientadora', 'cpf' => '168.995.350-09', 'email' => 'olga@x.com', 'tipo_autor' => 'orientador', 'apresentador' => false],
        ],
    ], 'aprovado');

    $this->cert = app(CertificadoTrabalhoService::class);
    $this->apr = app(ApresentacaoService::class);
    Mail::fake();
});

// ---------- Elegibilidade ----------
it('só é elegível se aprovado e (quando exigido) apresentado', function () {
    expect($this->cert->motivoInelegivel($this->trabalho))->toContain('apresentação');

    $this->apr->marcar($this->trabalho, true, $this->gestor);
    expect($this->cert->motivoInelegivel($this->trabalho->fresh()))->toBeNull();

    Trabalho::whereKey($this->trabalho->id)->update(['status' => 'reprovado']);
    expect($this->cert->motivoInelegivel($this->trabalho->fresh()))->toContain('não aprovado');
});

it('sem exigência de apresentação, aprovado basta', function () {
    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['exige_apresentacao_para_certificado' => false]);

    expect($this->cert->motivoInelegivel($this->trabalho->fresh()))->toBeNull();
});

// ---------- Emissão ----------
it('emite um certificado por autor com função correta, hash único e e-mail', function () {
    $this->apr->marcar($this->trabalho, true, $this->gestor);

    $novos = $this->cert->emitir($this->trabalho->fresh(), $this->gestor);

    expect($novos)->toHaveCount(3)
        ->and($novos->pluck('tipo')->all())->toBe(['autor_principal', 'coautor', 'orientador'])
        ->and($novos->first()->apresentador)->toBeTrue()->and($novos->first()->funcaoRotulo())->toBe('Autor principal e apresentador')
        ->and($novos->pluck('hash_validacao')->unique())->toHaveCount(3)
        ->and($novos->first()->hash_validacao)->toHaveLength(20);

    Mail::assertQueued(CertificadoTrabalhoMail::class, 3);
    Mail::assertQueued(CertificadoTrabalhoMail::class, fn ($m) => $m->hasTo('caio@x.com'));
});

it('é idempotente: reemitir não duplica nem reenvia e-mail', function () {
    $this->apr->marcar($this->trabalho, true, $this->gestor);
    $this->cert->emitir($this->trabalho->fresh(), $this->gestor);
    Mail::fake();

    expect($this->cert->emitir($this->trabalho->fresh(), $this->gestor))->toHaveCount(0);
    expect(CertificadoTrabalho::count())->toBe(3);
    Mail::assertNothingQueued();
});

it('autor adicionado depois recebe apenas o certificado que faltava', function () {
    $this->apr->marcar($this->trabalho, true, $this->gestor);
    $this->cert->emitir($this->trabalho->fresh(), $this->gestor);

    $this->trabalho->autores()->create(['ordem' => 4, 'nome_completo' => 'Novo', 'cpf' => '935.411.347-80', 'email' => 'novo@x.com', 'tipo_autor' => 'coautor']);

    expect($this->cert->emitir($this->trabalho->fresh(), $this->gestor))->toHaveCount(1);
});

it('não emite para trabalho inelegível', function () {
    expect(fn () => $this->cert->emitir($this->trabalho, $this->gestor))->toThrow(DomainException::class, 'apresentação');
    expect(CertificadoTrabalho::count())->toBe(0);
});

// ---------- Apresentação ----------
it('marca e desmarca a apresentação, só de trabalho aprovado, com registro interno', function () {
    $this->apr->marcar($this->trabalho, true, $this->gestor);
    $t = $this->trabalho->fresh();
    expect($t->apresentado)->toBeTrue()->and($t->apresentacao_origem)->toBe('manual')->and($t->apresentado_em)->not->toBeNull();
    expect($t->historico->last()->de)->toBe($t->historico->last()->para); // interno: não aparece ao autor

    $this->apr->marcar($t, false, $this->gestor);
    expect($t->fresh()->apresentado)->toBeFalse()->and($t->fresh()->apresentacao_origem)->toBeNull();

    $outro = mkTrabalho($this->evento, $this->autorUser, [], 'em_avaliacao');
    expect(fn () => $this->apr->marcar($outro, true, $this->gestor))->toThrow(DomainException::class, 'aprovados');
});

it('define a sessão apenas com atividade do mesmo evento', function () {
    $mesma = mkAtividade($this->evento);
    $estranha = mkAtividade(mkEvento($this->dono));

    $this->apr->definirSessao($this->trabalho, $mesma);
    expect($this->trabalho->fresh()->atividade_id)->toBe($mesma->id);

    expect(fn () => $this->apr->definirSessao($this->trabalho, $estranha))->toThrow(DomainException::class, 'mesmo evento');
    $this->apr->definirSessao($this->trabalho, null);
    expect($this->trabalho->fresh()->atividade_id)->toBeNull();
});

it('presença do apresentador na sessão confirma a apresentação automaticamente', function () {
    $sessao = mkAtividade($this->evento);
    $this->apr->definirSessao($this->trabalho, $sessao);
    $ana = Participante::create(['nome_completo' => 'Ana Principal', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_vinculo' => 'aluno']);

    presenca($ana, $sessao);

    $t = $this->trabalho->fresh();
    expect($t->apresentado)->toBeTrue()->and($t->apresentacao_origem)->toBe('frequencia');
});

it('presença de quem não é apresentador, em outra sessão ou de trabalho não aprovado não confirma', function () {
    $sessao = mkAtividade($this->evento);
    $outraSessao = mkAtividade($this->evento, 'Outra sessão');
    $this->apr->definirSessao($this->trabalho, $sessao);

    $caio = Participante::create(['nome_completo' => 'Caio', 'cpf' => '529.982.247-25', 'email' => 'caio@x.com', 'tipo_vinculo' => 'aluno']);
    $ana = Participante::create(['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_vinculo' => 'aluno']);

    presenca($caio, $sessao);          // coautor que não apresenta
    presenca($ana, $outraSessao);      // apresentadora, mas em outra sessão
    expect($this->trabalho->fresh()->apresentado)->toBeFalse();

    Trabalho::whereKey($this->trabalho->id)->update(['status' => 'avaliado']);
    presenca($ana, $sessao);           // sessão certa, mas trabalho não aprovado
    expect($this->trabalho->fresh()->apresentado)->toBeFalse();
});

it('definir a sessão já confirma a apresentação se o apresentador tem presença registrada', function () {
    $sessao = mkAtividade($this->evento);
    $ana = Participante::create(['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_vinculo' => 'aluno']);
    presenca($ana, $sessao);                       // presença registrada ANTES de definir a sessão

    $this->apr->definirSessao($this->trabalho, $sessao);

    $t = $this->trabalho->fresh();
    expect($t->apresentado)->toBeTrue()->and($t->apresentacao_origem)->toBe('frequencia');
});

it('sincroniza o evento com as presenças já registradas (sem passar por definirSessao)', function () {
    $sessao = mkAtividade($this->evento);
    $ana = Participante::create(['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_vinculo' => 'aluno']);
    presenca($ana, $sessao);
    Trabalho::whereKey($this->trabalho->id)->update(['atividade_id' => $sessao->id]);   // vínculo direto, sem o serviço
    expect($this->trabalho->fresh()->apresentado)->toBeFalse();

    expect($this->apr->sincronizarEvento($this->evento))->toBe(1);
    expect($this->trabalho->fresh()->apresentado)->toBeTrue();
    expect($this->apr->sincronizarEvento($this->evento))->toBe(0);
});

// ---------- Gestão (rotas) ----------
it('gestor marca apresentação, define sessão e emite certificados pelo painel', function () {
    $sessao = mkAtividade($this->evento);
    $this->actingAs($this->gestor);

    $this->post(route('gestao.trabalhos.apresentacao', $this->trabalho), ['acao' => 'sessao', 'atividade_id' => $sessao->id])->assertSessionHas('success');
    $this->post(route('gestao.trabalhos.certificados.emitir', $this->trabalho))->assertSessionHas('error'); // ainda não apresentou
    $this->post(route('gestao.trabalhos.apresentacao', $this->trabalho), ['acao' => 'marcar'])->assertSessionHas('success');
    $this->post(route('gestao.trabalhos.certificados.emitir', $this->trabalho))->assertSessionHas('success');

    expect($this->trabalho->fresh()->certificados)->toHaveCount(3);
    $this->get(route('gestao.trabalhos.show', $this->trabalho))->assertOk()->assertSee('Certificados')->assertSee('Autor principal e apresentador');
});

it('lote: marca apresentados, ignora trabalhos de outro evento e emite todos elegíveis', function () {
    $outroEvento = mkEvento($this->dono);
    $estranho = mkTrabalho($outroEvento, $this->autorUser, [], 'aprovado');
    $segundo = mkTrabalho($this->evento, $this->autorUser, [], 'aprovado');
    $this->actingAs($this->gestor);

    $this->post(route('gestao.trabalhos.certificados.lote', $this->evento), ['acao' => 'apresentado', 'trabalhos' => [$this->trabalho->id, $estranho->id]])->assertSessionHas('success');
    expect($this->trabalho->fresh()->apresentado)->toBeTrue()->and($estranho->fresh()->apresentado)->toBeFalse();

    $this->post(route('gestao.trabalhos.certificados.lote', $this->evento), ['acao' => 'emitir_todos'])->assertSessionHas('success')->assertSessionHas('erros_lote');
    expect($this->trabalho->fresh()->certificados)->toHaveCount(3)->and($segundo->fresh()->certificados)->toHaveCount(0);

    $sessao = mkAtividade($this->evento);
    $this->post(route('gestao.trabalhos.certificados.lote', $this->evento), ['acao' => 'sessao', 'trabalhos' => [$segundo->id], 'atividade_id' => $sessao->id]);
    expect($segundo->fresh()->atividade_id)->toBe($sessao->id);

    $this->post(route('gestao.trabalhos.certificados.lote', $this->evento), ['acao' => 'sessao', 'trabalhos' => [$segundo->id], 'atividade_id' => mkAtividade($outroEvento)->id])->assertSessionHas('error');
    $this->post(route('gestao.trabalhos.certificados.lote', $this->evento), ['acao' => 'apresentado'])->assertSessionHas('error'); // nada selecionado
});

it('página de apresentações lista aprovados e filtra por apresentado', function () {
    $this->apr->marcar($this->trabalho, true, $this->gestor);
    $naoApr = mkTrabalho($this->evento, $this->autorUser, ['titulo' => 'Ainda não apresentou'], 'aprovado');

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.certificados.index', $this->evento))->assertOk()
        ->assertSee('Robótica educacional')->assertSee('Ainda não apresentou');
    $this->get(route('gestao.trabalhos.certificados.index', [$this->evento, 'apresentado' => 'nao']))->assertDontSee('Robótica educacional')->assertSee('Ainda não apresentou');
    $this->get(route('gestao.trabalhos.certificados.index', [$this->evento, 'apresentado' => 'sim']))->assertSee('Robótica educacional')->assertDontSee('Ainda não apresentou');
});

it('somente quem gere trabalhos usa as rotas de apresentação e certificado', function (string $quem) {
    $this->apr->marcar($this->trabalho, true, $this->gestor);
    $this->cert->emitir($this->trabalho->fresh(), $this->gestor);
    $c = $this->trabalho->certificados()->first();

    $usuario = match ($quem) {
        'autor' => $this->autorUser,
        'avaliador' => tap(mkUser(), fn ($u) => mkPapel($u, $this->evento, 'avaliador')),
        'gestor de outro evento' => tap(mkUser(), fn ($u) => mkPapel($u, mkEvento($this->dono), 'gestor_trabalhos')),
        'admin de outro evento' => mkUser('admin'),
    };
    $this->actingAs($usuario);

    $this->get(route('gestao.trabalhos.certificados.index', $this->evento))->assertForbidden();
    $this->post(route('gestao.trabalhos.certificados.lote', $this->evento), ['acao' => 'emitir_todos'])->assertForbidden();
    $this->post(route('gestao.trabalhos.apresentacao', $this->trabalho), ['acao' => 'desmarcar'])->assertForbidden();
    $this->post(route('gestao.trabalhos.certificados.emitir', $this->trabalho))->assertForbidden();
    $this->post(route('gestao.trabalhos.certificados.revogar', $c), ['motivo' => 'x'])->assertForbidden();

    expect($this->trabalho->fresh()->apresentado)->toBeTrue()->and($c->fresh()->revogado())->toBeFalse();
})->with(['autor', 'avaliador', 'gestor de outro evento', 'admin de outro evento']);

// ---------- Revogação ----------
it('revoga certificado com motivo, uma única vez', function () {
    $this->apr->marcar($this->trabalho, true, $this->gestor);
    $this->cert->emitir($this->trabalho->fresh(), $this->gestor);
    $c = $this->trabalho->certificados()->first();
    $this->actingAs($this->gestor);

    $this->post(route('gestao.trabalhos.certificados.revogar', $c), ['motivo' => ''])->assertSessionHasErrors('motivo');
    $this->post(route('gestao.trabalhos.certificados.revogar', $c), ['motivo' => 'Emitido por engano'])->assertSessionHas('success');
    $this->post(route('gestao.trabalhos.certificados.revogar', $c), ['motivo' => 'de novo'])->assertSessionHas('error');

    expect($c->fresh()->revogado())->toBeTrue()->and($c->fresh()->revogado_motivo)->toBe('Emitido por engano');
});

// ---------- Público: validação e download ----------
function certificadoEmitido(): CertificadoTrabalho
{
    test()->apr->marcar(test()->trabalho, true, test()->gestor);
    test()->cert->emitir(test()->trabalho->fresh(), test()->gestor);

    return test()->trabalho->certificados()->first();
}

it('valida certificado autêntico publicamente, com CPF mascarado', function () {
    $c = certificadoEmitido();

    $this->get(route('certificado.trabalho.validar', $c->hash_validacao))->assertOk()
        ->assertSee('Certificado autêntico')->assertSee('Ana Principal')->assertSee('Robótica educacional')->assertSee('***.444.***-**')
        ->assertDontSee('111.444.777-35');
    $this->get(route('certificado.trabalho.validar', strtolower($c->hash_validacao)))->assertOk()->assertSee('Certificado autêntico');
});

it('validação informa código inexistente e certificado revogado', function () {
    $c = certificadoEmitido();

    $this->get(route('certificado.trabalho.validar', 'NAOEXISTE123'))->assertOk()->assertSee('Documento não encontrado');

    $c->update(['revogado_em' => now()]);
    $this->get(route('certificado.trabalho.validar', $c->hash_validacao))->assertOk()->assertSee('Certificado revogado')->assertDontSee('Ana Principal');
});

it('baixa o PDF, conta o download e bloqueia revogado ou inexistente', function () {
    $c = certificadoEmitido();

    $r = $this->get(route('certificado.trabalho.download', $c->hash_validacao))->assertOk();
    expect($r->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(substr($r->getContent(), 0, 5))->toBe('%PDF-')
        ->and($c->fresh()->downloads)->toBe(1);

    $this->get(route('certificado.trabalho.download', 'NAOEXISTE123'))->assertNotFound();

    $c->update(['revogado_em' => now()]);
    $this->get(route('certificado.trabalho.download', $c->hash_validacao))->assertStatus(410);
});

it('limita requisições às rotas públicas de certificado', function () {
    $c = certificadoEmitido();

    foreach (range(1, 30) as $i) {
        $this->get(route('certificado.trabalho.validar', $c->hash_validacao))->assertOk();
    }
    $this->get(route('certificado.trabalho.validar', $c->hash_validacao))->assertStatus(429);
});

// ---------- Segurança do texto ----------
it('escapa dados variáveis e remove tags perigosas do modelo', function () {
    $this->trabalho->autores()->first()->update(['nome_completo' => '<img src="http://interno/x.png"><script>alert(1)</script> & Cia']);
    $this->trabalho->update(['titulo' => '<b onclick="x">Título</b>']);
    TrabalhoConfig::where('evento_id', $this->evento->id)->update([
        'cert_trab_corpo' => '<b>{AUTOR}</b> em <i>{TITULO_TRABALHO}</i><img src="http://externo/pixel.png"><script>x()</script><iframe src="y"></iframe> {EVENTO}',
        'cert_trab_cabecalho' => '<script>alert(2)</script>Cabeçalho',
    ]);
    $c = certificadoEmitido();

    $t = $this->cert->textos($c->fresh());

    expect($t['corpo'])->not->toContain('<img')->not->toContain('<script')->not->toContain('<iframe')->not->toContain('<b onclick')
        ->and($t['corpo'])->toContain('<b>')->toContain('<i>')->toContain('&lt;IMG')->toContain('&amp; CIA')
        ->and($t['cabecalho'])->not->toContain('<script')->toContain('&lt;script&gt;');
});

it('usa o texto padrão quando a configuração está vazia e preenche as variáveis', function () {
    $c = certificadoEmitido();

    $t = $this->cert->textos($c->fresh());

    expect($t['corpo'])->toContain('ANA PRINCIPAL')->toContain('111.444.777-35')->toContain('Robótica educacional')->toContain($this->trabalho->codigo)
        ->toContain($this->evento->titulo)->toContain('autor principal e apresentador')->toContain('Coautoria: Caio Coautor, Olga Orientadora')
        ->and($t['corpo'])->not->toContain('{');
});

it('o certificado existente também escapa dados vindos de formulários públicos', function () {
    $evento = $this->evento;
    $atividade = mkAtividade($evento);
    $p = Participante::create(['nome_completo' => '<img src="http://interno/x"><b>Hack</b>', 'cpf' => '935.411.347-80', 'email' => 'h@x.com', 'tipo_vinculo' => 'externo']);
    $freq = Frequencia::create(['participante_id' => $p->id, 'atividade_id' => $atividade->id, 'data_registro' => now(), 'hash_validacao' => 'HASHXSS', 'tipo_participacao' => 'ouvinte']);
    $evento->update(['cert_corpo' => '{PARTICIPANTE} <img src="http://externo/p.png"> {ATIVIDADE}']);

    $capturado = null;
    // PDF real mínimo (o método precisa devolver o tipo da biblioteca); só capturamos os dados da view
    $pdfReal = app('dompdf.wrapper')->loadHTML('<p>x</p>');
    \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($view, $dados) use (&$capturado, $pdfReal) {
        $capturado = $dados;

        return $pdfReal;
    });

    $this->get(route('certificado.download', 'HASHXSS'))->assertOk();

    expect($capturado['textoCorpo'])->not->toContain('<img')->toContain('&lt;IMG')->toContain('Sessão de Pôsteres');
});

// ---------- Autor ----------
it('autor vê e baixa os certificados emitidos; os revogados somem', function () {
    $c = certificadoEmitido();
    $this->actingAs($this->autorUser);

    $this->get(route('trabalhos.show', $this->trabalho))->assertOk()->assertSee('Certificados')->assertSee(route('certificado.trabalho.download', $c->hash_validacao), false);

    $c->update(['revogado_em' => now()]);
    $this->get(route('trabalhos.show', $this->trabalho))->assertDontSee(route('certificado.trabalho.download', $c->hash_validacao), false);
});

it('outro usuário não vê os certificados na página do trabalho', function () {
    $c = certificadoEmitido();

    $this->actingAs(mkUser())->get(route('trabalhos.show', $this->trabalho))->assertForbidden();
});

// ---------- Configuração ----------
it('salva os textos do certificado e a exigência de apresentação', function () {
    $base = ['max_autores' => 5, 'max_arquivo_mb' => 10, 'resumo_min_palavras' => 50, 'resumo_max_palavras' => 250, 'tipos_permitidos' => ['resumo'],
        'avaliadores_por_trabalho' => 2, 'nota_minima_aprovacao' => 7, 'divergencia_maxima' => 3];

    $this->actingAs($this->gestor)->put(route('gestao.trabalhos.config.update', $this->evento), array_merge($base, [
        'exige_apresentacao_para_certificado' => 0, 'cert_trab_cabecalho' => 'Meu cabeçalho', 'cert_trab_corpo' => 'Texto {AUTOR}', 'cert_trab_rodape' => 'Rodapé',
    ]))->assertSessionHasNoErrors();

    $c = TrabalhoConfig::where('evento_id', $this->evento->id)->first();
    expect($c->exige_apresentacao_para_certificado)->toBeFalse()->and($c->cert_trab_corpo)->toBe('Texto {AUTOR}')->and($c->cert_trab_cabecalho)->toBe('Meu cabeçalho');
});
