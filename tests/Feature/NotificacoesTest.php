<?php

use App\Mail\MovimentacaoMail;
use App\Models\Atividade;
use App\Models\ChamadaProposta;
use App\Models\ChamadaVoluntario;
use App\Models\DemandaVoluntario;
use App\Models\Participante;
use App\Models\Voluntario;
use App\Services\PropostaService;
use App\Services\TrabalhoWorkflow;
use App\Services\VoluntarioService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->admin = mkUser('admin');
    $this->evento = mkEvento($this->admin);
    $this->autor = mkUser('user');
    Participante::create(['user_id' => $this->autor->id, 'nome_completo' => 'Autor Teste', 'cpf' => '111.444.777-35', 'email' => $this->autor->email, 'tipo_vinculo' => 'servidor']);
});

function movimentos(string $titulo)
{
    return Mail::queued(MovimentacaoMail::class, fn ($m) => str_contains($m->d['titulo'], $titulo));
}

it('trabalho: avisa o autor em cada movimentação, com link para o status no perfil', function () {
    $trabalho = mkTrabalho($this->evento, $this->autor);
    $gestor = mkUser('admin');
    $wf = app(TrabalhoWorkflow::class);

    $wf->transicionar($trabalho->fresh(), $gestor, 'em_triagem');
    expect(movimentos('em análise'))->toHaveCount(1);
    $m = movimentos('em análise')->first();
    expect($m->hasTo($this->autor->email))->toBeTrue()->and($m->d['href'])->toBe(route('trabalhos.show', $trabalho))->and($m->d['area_href'])->toBe(route('trabalhos.index'));
    expect($m->render())->toContain('Meus Trabalhos')->toContain(route('trabalhos.index'))->toContain('no seu perfil');

});

it('trabalho: retirada e encaminhamento para avaliação geram aviso', function () {
    $trabalho = mkTrabalho($this->evento, $this->autor);
    app(TrabalhoWorkflow::class)->retirar($trabalho->fresh(), $this->autor, 'Desisti');
    expect(movimentos('retirado'))->toHaveCount(1);
    expect(movimentos('retirado')->first()->d['citacao'])->toBe('Desisti');
});

it('proposta: avisa recebimento, análise, atualização, decisão e retirada, sempre indicando o perfil', function () {
    $chamada = ChamadaProposta::create(['evento_id' => $this->evento->id, 'titulo' => 'Minicursos', 'tipos_aceitos' => ['minicurso'], 'ativa' => true, 'max_por_usuario' => 3, 'criado_por' => $this->admin->id]);
    $svc = app(PropostaService::class);
    $dados = ['tipo' => 'minicurso', 'titulo' => 'Git', 'resumo' => str_repeat('Aprender versionamento. ', 10), 'carga_horaria_prevista' => 4, 'vagas_sugeridas' => 30];

    $p = $svc->enviar($this->autor, $chamada, $dados);
    expect(movimentos('Proposta recebida'))->toHaveCount(1);
    $m = movimentos('Proposta recebida')->first();
    expect($m->hasTo($this->autor->email))->toBeTrue()->and($m->d['href'])->toBe(route('propostas.show', $p))->and($m->render())->toContain('Propostas de Curso/Oficina');

    $svc->decidir($p, $this->admin, 'em_analise');
    expect(movimentos('em análise'))->toHaveCount(1);

    $svc->decidir($p->fresh(), $this->admin, 'ajustes', ['parecer' => 'Detalhe a metodologia']);
    Mail::assertQueued(\App\Mail\CandidaturaDecididaMail::class);

    $svc->atualizar($p->fresh(), ['titulo' => 'Git 2'] + $dados);
    expect(movimentos('Proposta atualizada'))->toHaveCount(1);

    $svc->retirar($p->fresh());
    expect(movimentos('Proposta retirada'))->toHaveCount(1);
});

it('voluntário: avisa inscrição, vínculo e desvínculo de atividade, desistência e certificado', function () {
    $ch = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'Voluntários', 'ativa' => true]);
    $svc = app(VoluntarioService::class);
    $hoje = now()->format('Y-m-d');
    $v = $svc->inscrever($this->autor, $ch, ['motivacao' => 'Quero ajudar', 'telefone' => '(93) 91234-5678', 'disponibilidade' => [$hoje => ['manha']]]);
    expect(movimentos('Inscrição de voluntário recebida'))->toHaveCount(1);

    $svc->decidir($v, $this->admin, 'aprovado');
    $d = DemandaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'Recepção', 'data_inicio' => now()->setTime(9, 0), 'data_fim' => now()->setTime(11, 0), 'local' => 'Hall', 'carga_horaria' => 2, 'responsavel_nome' => 'Servidor Fulano']);
    $svc->alocar($v->fresh(), $d);
    $m = movimentos('vinculado')->first();
    expect($m->d['detalhes'])->toMatchArray(['Atividade' => 'Recepção', 'Local' => 'Hall'])->and($m->d['detalhes']['Quem procurar'])->toContain('Servidor Fulano')->and($m->render())->toContain('Voluntariado');

    $svc->desalocar($v->fresh(), $d);
    expect(movimentos('desvinculado'))->toHaveCount(1);
    $svc->desalocar($v->fresh(), $d);   // já removido: não avisa de novo
    expect(movimentos('desvinculado'))->toHaveCount(1);

    $svc->alocar($v->fresh(), $d, true);
    $svc->emitirCertificado($v->fresh(), $this->admin);
    expect(movimentos('certificado de voluntário'))->toHaveCount(1);
    $svc->emitirCertificado($v->fresh(), $this->admin);   // reemissão não repete o aviso
    expect(movimentos('certificado de voluntário'))->toHaveCount(1);

    $svc->desistir($v->fresh());
    expect(movimentos('cancelada'))->toHaveCount(1);
});

it('inscrição de voluntário exige WhatsApp com DDD', function () {
    $ch = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'V', 'ativa' => true]);
    $this->actingAs($this->autor)->get(route('voluntariado.create', $ch))->assertOk()->assertSee('WhatsApp (com DDD) *')->assertSee('name="telefone"', false);
    $base = ['motivacao' => 'Quero ajudar no evento todo', 'disponibilidade' => [now()->format('Y-m-d') => ['manha']]];
    $this->post(route('voluntariado.store', $ch), $base)->assertSessionHasErrors('telefone');
    $this->post(route('voluntariado.store', $ch), $base + ['telefone' => '(93) 91234-5678'])->assertSessionHas('success');
});

it('grupo de WhatsApp: valida o link, envia e-mail só aos aprovados e mostra o botão ao voluntário', function () {
    $ch = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'V', 'ativa' => true]);
    $svc = app(VoluntarioService::class);
    $hoje = now()->format('Y-m-d');
    $aprovado = $svc->inscrever($this->autor, $ch, ['motivacao' => 'Quero ajudar', 'telefone' => '(93) 91234-5678', 'disponibilidade' => [$hoje => ['manha']]]);
    $svc->decidir($aprovado, $this->admin, 'aprovado');
    $outro = mkUser('user');
    Participante::create(['user_id' => $outro->id, 'nome_completo' => 'Outro', 'cpf' => '529.982.247-25', 'email' => $outro->email, 'tipo_vinculo' => 'externo']);
    $svc->inscrever($outro, $ch, ['motivacao' => 'Quero ajudar', 'telefone' => '(93) 98888-7777', 'disponibilidade' => [$hoje => ['manha']]]);   // continua em análise
    Mail::fake();   // zera os e-mails da preparação

    $this->actingAs($this->admin);
    $url = route('admin.voluntarios.chamadas.update', [$this->evento, $ch]);
    $this->put($url, ['titulo' => 'V', 'grupo_whatsapp_url' => 'https://exemplo.com/grupo'])->assertSessionHasErrors('grupo_whatsapp_url');

    // sem link cadastrado: não envia
    $this->post(route('admin.voluntarios.chamadas.grupo', [$this->evento, $ch]))->assertSessionHas('error');

    $this->put($url, ['titulo' => 'V', 'grupo_whatsapp_url' => 'https://chat.whatsapp.com/AbC123xyz'])->assertSessionHasNoErrors();
    $this->post(route('admin.voluntarios.chamadas.grupo', [$this->evento, $ch]))->assertSessionHas('success', 'Link do grupo enviado por e-mail a 1 voluntário(s) aprovado(s).');
    expect(movimentos('grupo de WhatsApp'))->toHaveCount(1);
    $m = movimentos('grupo de WhatsApp')->first();
    expect($m->hasTo($this->autor->email))->toBeTrue()->and($m->d['href'])->toBe('https://chat.whatsapp.com/AbC123xyz')->and($m->d['botao'])->toBe('Entrar no grupo de WhatsApp');
    expect($ch->fresh()->grupo_whatsapp_enviado_em)->not->toBeNull();

    // o voluntário aprovado vê o botão do grupo; quem está em análise, não
    $this->actingAs($this->autor)->get(route('voluntariado.meu'))->assertSee('https://chat.whatsapp.com/AbC123xyz', false)->assertSee('Entrar no grupo');
    $this->actingAs($outro)->get(route('voluntariado.meu'))->assertDontSee('https://chat.whatsapp.com/AbC123xyz', false);
});

it('listagem de voluntários em CSV com WhatsApp, atividades e filtros; protege contra fórmulas', function () {
    $ch = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'V', 'ativa' => true]);
    $svc = app(VoluntarioService::class);
    $hoje = now()->format('Y-m-d');
    $v = $svc->inscrever($this->autor, $ch, ['motivacao' => 'Quero ajudar', 'telefone' => '(93) 91234-5678', 'disponibilidade' => [$hoje => ['manha']]]);
    $svc->decidir($v, $this->admin, 'aprovado');
    $d = DemandaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => '=SOMA(1;1)', 'carga_horaria' => 3]);
    $svc->alocar($v->fresh(), $d);

    $r = $this->actingAs($this->admin)->get(route('admin.voluntarios.listagem', $this->evento));
    $r->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $csv = $r->streamedContent();
    expect($csv)->toContain('Nome;E-mail;WhatsApp')->toContain('(93) 91234-5678')->toContain('https://wa.me/5593912345678')->toContain("'=SOMA(1;1)")->toContain('Aprovado');

    // filtro por situação: quem não está aprovado não entra
    expect($this->get(route('admin.voluntarios.listagem', [$this->evento, 'status' => 'reprovado']))->streamedContent())->not->toContain('(93) 91234-5678');

    // gestão sem permissão não baixa
    $this->actingAs(mkUser('user'))->get(route('admin.voluntarios.listagem', $this->evento))->assertRedirect();
});

it('usuário exclui a própria proposta (se não aprovada), com apoios; aprovada e de outra pessoa não', function () {
    $chamada = ChamadaProposta::create(['evento_id' => $this->evento->id, 'titulo' => 'Minicursos', 'tipos_aceitos' => ['minicurso'], 'ativa' => true, 'max_por_usuario' => 5, 'criado_por' => $this->admin->id]);
    $svc = app(PropostaService::class);
    $dados = ['tipo' => 'minicurso', 'titulo' => 'Git', 'resumo' => str_repeat('Aprender versionamento. ', 10), 'carga_horaria_prevista' => 4, 'vagas_sugeridas' => 30];
    $apoio = [['nome' => 'Ajudante', 'email' => 'aj@x.com', 'cpf' => '529.982.247-25']];

    $p = $svc->enviar($this->autor, $chamada, $dados, $apoio);
    $this->actingAs($this->autor)->get(route('propostas.show', $p))->assertSee('Excluir proposta');
    $this->actingAs(mkUser('user'))->delete(route('propostas.excluir', $p))->assertForbidden();

    $this->actingAs($this->autor)->delete(route('propostas.excluir', $p))->assertRedirect(route('propostas.minhas'))->assertSessionHas('success');
    expect(\App\Models\Proposta::count())->toBe(0)->and(\App\Models\PropostaApoio::count())->toBe(0);

    // aprovada (já na programação): não exclui
    $lab = \App\Models\Local::create(['evento_id' => $this->evento->id, 'nome' => 'Lab', 'tipo' => 'laboratorio']);
    $p2 = $svc->enviar($this->autor, $chamada, ['titulo' => 'Docker'] + $dados);
    $svc->decidir($p2, $this->admin, 'aprovada', ['data_inicio' => now()->addDay()->setTime(9, 0), 'data_fim' => now()->addDay()->setTime(13, 0), 'local_id' => $lab->id, 'vagas' => 20, 'carga_horaria' => 4]);
    $this->actingAs($this->autor)->get(route('propostas.show', $p2))->assertDontSee('Excluir proposta')->assertSee('fale com a organização');
    $this->delete(route('propostas.excluir', $p2))->assertSessionHas('error');
    expect(\App\Models\Proposta::count())->toBe(1);

    // em andamento (enviada) pode; depois de retirada também
    $p3 = $svc->enviar($this->autor, $chamada, ['titulo' => 'K8s'] + $dados);
    $svc->retirar($p3);
    $this->delete(route('propostas.excluir', $p3))->assertSessionHas('success');
});

it('usuário exclui a própria inscrição de voluntário, menos se aprovado ou já certificado', function () {
    $ch = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'V', 'ativa' => true]);
    $svc = app(VoluntarioService::class);
    $disp = [now()->format('Y-m-d') => ['manha']];
    $v = $svc->inscrever($this->autor, $ch, ['motivacao' => 'Quero ajudar', 'telefone' => '(93) 91234-5678', 'disponibilidade' => $disp]);

    $this->actingAs($this->autor)->get(route('voluntariado.meu'))->assertSee('Excluir inscrição');
    $this->actingAs(mkUser('user'))->delete(route('voluntariado.excluir', $v))->assertForbidden();

    // aprovado: precisa desistir antes
    $svc->decidir($v, $this->admin, 'aprovado');
    $this->actingAs($this->autor)->get(route('voluntariado.meu'))->assertDontSee('Excluir inscrição');
    $this->delete(route('voluntariado.excluir', $v))->assertSessionHas('error');
    expect(Voluntario::count())->toBe(1);

    $this->post(route('voluntariado.desistir', $v));
    $this->delete(route('voluntariado.excluir', $v))->assertRedirect(route('voluntariado.meu'))->assertSessionHas('success');
    expect(Voluntario::count())->toBe(0);

    // pode se inscrever de novo depois de excluir
    $svc->inscrever($this->autor, $ch, ['motivacao' => 'Voltei', 'telefone' => '(93) 91234-5678', 'disponibilidade' => $disp]);
    expect(Voluntario::count())->toBe(1);
});

it('autor exclui o próprio trabalho em rascunho, retirado ou reprovado; em andamento ou aprovado, não', function () {
    $this->actingAs($this->autor);
    $rascunho = mkTrabalho($this->evento, $this->autor, [], 'rascunho');
    $this->get(route('trabalhos.show', $rascunho))->assertSee('Excluir trabalho');
    $this->actingAs(mkUser('user'))->delete(route('trabalhos.excluir', $rascunho))->assertForbidden();

    $this->actingAs($this->autor)->delete(route('trabalhos.excluir', $rascunho))->assertRedirect(route('trabalhos.index'))->assertSessionHas('success');
    expect(\App\Models\Trabalho::find($rascunho->id))->toBeNull()->and(\App\Models\Trabalho::withTrashed()->find($rascunho->id))->not->toBeNull();
    expect(\App\Models\Trabalho::withTrashed()->find($rascunho->id)->historico()->where('justificativa', 'Excluído pelo autor')->exists())->toBeTrue();

    foreach (['submetido', 'em_avaliacao', 'aprovado'] as $status) {
        $t = mkTrabalho($this->evento, $this->autor, [], $status);
        $this->delete(route('trabalhos.excluir', $t))->assertForbidden();
        expect(\App\Models\Trabalho::find($t->id))->not->toBeNull();
    }

    foreach (['retirado', 'reprovado'] as $status) {
        $t = mkTrabalho($this->evento, $this->autor, [], $status);
        $this->get(route('trabalhos.index'))->assertSee(route('trabalhos.excluir', $t), false);
        $this->delete(route('trabalhos.excluir', $t))->assertSessionHas('success');
        expect(\App\Models\Trabalho::find($t->id))->toBeNull();
    }
});
