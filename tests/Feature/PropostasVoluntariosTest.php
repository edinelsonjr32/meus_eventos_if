<?php

use App\Mail\CandidaturaDecididaMail;
use App\Models\Atividade;
use App\Models\ChamadaProposta;
use App\Models\ChamadaVoluntario;
use App\Models\DemandaVoluntario;
use App\Models\Equipe;
use App\Models\Inscricao;
use App\Models\Local;
use App\Models\Participante;
use App\Models\Proposta;
use App\Models\PropostaApoio;
use App\Models\Voluntario;
use App\Services\PropostaService;
use App\Services\VoluntarioService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->admin = mkUser('admin');
    $this->evento = mkEvento($this->admin);
    $this->lab = Local::create(['evento_id' => $this->evento->id, 'nome' => 'Lab 1', 'bloco' => 'Bloco A', 'tipo' => 'laboratorio', 'capacidade' => 30]);
    $this->sala = Local::create(['evento_id' => $this->evento->id, 'nome' => 'Sala 2', 'tipo' => 'sala', 'capacidade' => 40]);
    $this->autor = mkUser('user');
    Participante::create(['user_id' => $this->autor->id, 'nome_completo' => 'Prof. Autor', 'cpf' => '111.444.777-35', 'email' => $this->autor->email, 'tipo_vinculo' => 'servidor']);
    $this->chamada = ChamadaProposta::create(['evento_id' => $this->evento->id, 'titulo' => 'Minicursos', 'tipos_aceitos' => ['minicurso', 'oficina'], 'ativa' => true, 'max_por_usuario' => 2, 'criado_por' => $this->admin->id]);
    $this->dados = ['tipo' => 'minicurso', 'titulo' => 'Git na prática', 'resumo' => str_repeat('Aprender versionamento. ', 10), 'carga_horaria_prevista' => 4, 'vagas_sugeridas' => 30];
});

function aprovacao(array $extra = []): array
{
    return $extra + ['data_inicio' => now()->addDays(1)->setTime(9, 0), 'data_fim' => now()->addDays(1)->setTime(13, 0), 'local_id' => test()->lab->id, 'vagas' => 25, 'carga_horaria' => 4, 'equipamentos' => 'Projetor'];
}

it('exige usuário autenticado e participante com CPF para propor', function () {
    $this->get(route('propostas.minhas'))->assertRedirect(route('login'));

    $semCadastro = mkUser('user');
    expect(fn () => app(PropostaService::class)->enviar($semCadastro, $this->chamada, $this->dados))->toThrow(DomainException::class);
});

it('envia proposta pelo formulário, lista em Propostas de Curso/Oficina e respeita o limite por usuário', function () {
    $this->actingAs($this->autor)->get(route('propostas.create', $this->chamada))->assertOk();
    $this->post(route('propostas.store', $this->chamada), $this->dados + ['apoios' => [['nome' => 'Ajudante', 'email' => 'aj@x.com', 'cpf' => '529.982.247-25']]])->assertRedirect();

    $p = Proposta::first();
    expect($p->status)->toBe('enviada')->and($p->apoios)->toHaveCount(1);
    $this->get(route('propostas.minhas'))->assertOk()->assertSee('Git na prática');
    $this->get(route('propostas.show', $p))->assertOk()->assertSee('Git na prática')->assertSee('Ajudante');
    $this->get(route('propostas.edit', $p))->assertOk()->assertSee('Git na prática');

    app(PropostaService::class)->enviar($this->autor, $this->chamada, ['titulo' => 'Outra'] + $this->dados);
    expect(fn () => app(PropostaService::class)->enviar($this->autor, $this->chamada, ['titulo' => 'Terceira'] + $this->dados))->toThrow(DomainException::class, 'limite');
});

it('recusa proposta fora do prazo ou de tipo não aceito', function () {
    $this->chamada->update(['fim' => now()->subDay()]);
    expect(fn () => app(PropostaService::class)->enviar($this->autor, $this->chamada, $this->dados))->toThrow(DomainException::class);

    $this->chamada->update(['fim' => null, 'tipos_aceitos' => ['oficina']]);
    expect(fn () => app(PropostaService::class)->enviar($this->autor, $this->chamada->fresh(), $this->dados))->toThrow(DomainException::class);
});

it('aprovar vincula a programação com local, espaço, vagas e equipamentos, e avisa por e-mail', function () {
    $p = app(PropostaService::class)->enviar($this->autor, $this->chamada, $this->dados);

    $this->actingAs($this->admin)->post(route('admin.propostas.decisao', [$this->evento, $p]), ['decisao' => 'aprovada'] + array_map(fn ($v) => $v instanceof DateTimeInterface ? $v->format('Y-m-d\TH:i') : $v, aprovacao()))->assertSessionHas('success');

    $p->refresh();
    $a = Atividade::find($p->atividade_id);
    expect($p->status)->toBe('aprovada')->and($a->local_id)->toBe($this->lab->id)->and($a->local)->toBe('Lab 1 (Bloco A)')->and($a->vagas)->toBe(25)->and($a->equipamentos)->toBe('Projetor')->and($a->tipo)->toBe('minicurso');
    Mail::assertQueued(CandidaturaDecididaMail::class);
});

it('bloqueia conflito de espaço/horário, a menos que o admin ignore', function () {
    $this->chamada->update(['max_por_usuario' => 5]);
    $svc = app(PropostaService::class);
    $p1 = $svc->enviar($this->autor, $this->chamada, $this->dados);
    $p2 = $svc->enviar($this->autor, $this->chamada, ['titulo' => 'Docker'] + $this->dados);
    $svc->decidir($p1, $this->admin, 'aprovada', aprovacao());

    expect(fn () => $svc->decidir($p2, $this->admin, 'aprovada', aprovacao()))->toThrow(DomainException::class, 'Conflito');
    expect($svc->decidir($p2, $this->admin, 'aprovada', aprovacao(['local_id' => $this->sala->id]))->status)->toBe('aprovada');
    $p3 = $svc->enviar($this->autor, $this->chamada, ['titulo' => 'K8s'] + $this->dados);
    expect(fn () => $svc->decidir($p3, $this->admin, 'aprovada', aprovacao(['local_id' => $this->sala->id])))->toThrow(DomainException::class, 'Conflito');
    expect($svc->decidir($p3, $this->admin, 'aprovada', aprovacao(['local_id' => $this->sala->id, 'ignorar_conflito' => true]))->status)->toBe('aprovada');
});

it('ajustes e reprovação exigem mensagem; ajustes permitem editar e reenviar', function () {
    $svc = app(PropostaService::class);
    $p = $svc->enviar($this->autor, $this->chamada, $this->dados);

    expect(fn () => $svc->decidir($p, $this->admin, 'ajustes'))->toThrow(DomainException::class);
    $svc->decidir($p, $this->admin, 'ajustes', ['parecer' => 'Detalhe a metodologia']);

    $this->actingAs($this->autor)->put(route('propostas.update', $p), ['titulo' => 'Git avançado'] + $this->dados)->assertRedirect();
    expect($p->fresh()->status)->toBe('enviada')->and($p->fresh()->titulo)->toBe('Git avançado');
});

it('outro usuário não vê nem edita proposta alheia', function () {
    $p = app(PropostaService::class)->enviar($this->autor, $this->chamada, $this->dados);
    $this->actingAs(mkUser('user'))->get(route('propostas.show', $p))->assertForbidden();
    $this->put(route('propostas.update', $p), $this->dados)->assertForbidden();
});

it('usuário comum não acessa a gestão de propostas nem de voluntários', function () {
    $this->actingAs($this->autor)->get(route('admin.propostas.index', $this->evento))->assertRedirect();
    $this->get(route('admin.voluntarios.index', $this->evento))->assertRedirect();
});

it('admin adiciona pessoa de apoio como participante do evento, sem duplicar', function () {
    $svc = app(PropostaService::class);
    $p = $svc->enviar($this->autor, $this->chamada, $this->dados, [['nome' => 'Ajudante', 'email' => 'aj@x.com', 'cpf' => '529.982.247-25']]);
    $apoio = $p->apoios->first();

    $svc->adicionarApoioAoEvento($apoio, $this->admin);
    expect(Inscricao::where('evento_id', $this->evento->id)->count())->toBe(1)->and($apoio->fresh()->noEvento())->toBeTrue();
    expect(fn () => $svc->adicionarApoioAoEvento($apoio->fresh(), $this->admin))->toThrow(DomainException::class);

    $semCpf = PropostaApoio::create(['proposta_id' => $p->id, 'nome' => 'Sem CPF']);
    expect(fn () => $svc->adicionarApoioAoEvento($semCpf, $this->admin))->toThrow(DomainException::class);
});

it('certificado de ministrante só depois da atividade terminar', function () {
    $svc = app(PropostaService::class);
    $p = $svc->enviar($this->autor, $this->chamada, $this->dados);
    $svc->decidir($p, $this->admin, 'aprovada', aprovacao());

    expect(fn () => $svc->emitirCertificadoMinistrante($this->evento, $this->autor, $this->admin))->toThrow(DomainException::class);

    Atividade::find($p->fresh()->atividade_id)->update(['data_inicio' => now()->subDays(2), 'data_fim' => now()->subDays(2)->addHours(4)]);
    $eq = $svc->emitirCertificadoMinistrante($this->evento, $this->autor, $this->admin);

    expect($eq->funcao)->toBe('Ministrante')->and($eq->carga_horaria_trabalho)->toBe(4)->and($eq->hash_validacao)->not->toBeNull();
    $this->actingAs($this->autor)->get(route('propostas.certificado', $this->evento->id))->assertRedirect();
});

it('voluntário se inscreve, é aprovado, vinculado a demanda (sem choque de horário) e recebe certificado', function () {
    $ch = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'Voluntários', 'ativa' => true, 'vagas' => 1]);
    $svc = app(VoluntarioService::class);

    $this->actingAs($this->autor)->get(route('voluntariado.create', $ch))->assertOk();
    $this->post(route('voluntariado.store', $ch), ['motivacao' => 'Quero ajudar na organização do evento', 'disponibilidade' => [now()->format('Y-m-d') => ['manha', 'tarde']]])->assertRedirect();
    $v = Voluntario::first();
    expect($v->status)->toBe('inscrito');
    expect(fn () => $svc->inscrever($this->autor, $ch, ['motivacao' => 'x', 'disponibilidade' => [now()->format('Y-m-d') => ['manha']]]))->toThrow(DomainException::class);

    $d1 = DemandaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'Credenciamento', 'data_inicio' => now()->setTime(8, 0), 'data_fim' => now()->setTime(12, 0), 'local' => 'Hall', 'vagas' => 2, 'carga_horaria' => 4, 'responsavel_nome' => 'Servidor Fulano', 'responsavel_contato' => '9999']);
    $d2 = DemandaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'Apoio sala', 'data_inicio' => now()->setTime(10, 0), 'data_fim' => now()->setTime(12, 0), 'vagas' => 2, 'carga_horaria' => 4]);

    expect(fn () => $svc->alocar($v, $d1))->toThrow(DomainException::class, 'aprovados');
    $svc->decidir($v, $this->admin, 'aprovado');
    $svc->alocar($v->fresh(), $d1);
    expect(fn () => $svc->alocar($v->fresh(), $d2))->toThrow(DomainException::class, 'Conflito');

    $this->get(route('voluntariado.meu'))->assertOk()->assertSee('Credenciamento')->assertSee('Servidor Fulano');

    $eq = $svc->emitirCertificado($v->fresh(), $this->admin);
    expect($eq->funcao)->toBe('Voluntário')->and($eq->carga_horaria_trabalho)->toBe(4);
    $this->get(route('voluntariado.certificado', $v))->assertRedirect();
    $this->actingAs(mkUser('user'))->get(route('voluntariado.certificado', $v))->assertForbidden();
});

it('respeita o limite de vagas da chamada de voluntários e remove vínculos ao reprovar', function () {
    $ch = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'V', 'ativa' => true, 'vagas' => 1]);
    $svc = app(VoluntarioService::class);
    $outro = mkUser('user');
    Participante::create(['user_id' => $outro->id, 'nome_completo' => 'Outro', 'cpf' => '529.982.247-25', 'email' => $outro->email, 'tipo_vinculo' => 'externo']);

    $v1 = $svc->inscrever($this->autor, $ch, ['motivacao' => 'a', 'disponibilidade' => [now()->format('Y-m-d') => ['manha']]]);
    $v2 = $svc->inscrever($outro, $ch, ['motivacao' => 'a', 'disponibilidade' => [now()->format('Y-m-d') => ['manha']]]);
    $svc->decidir($v1, $this->admin, 'aprovado');
    expect(fn () => $svc->decidir($v2, $this->admin, 'aprovado'))->toThrow(DomainException::class);

    $d = DemandaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'D', 'carga_horaria' => 2]);
    $svc->alocar($v1->fresh(), $d);
    $svc->decidir($v1->fresh(), $this->admin, 'reprovado', 'Sem vaga');
    expect($v1->fresh()->demandas()->count())->toBe(0);
});

it('telas de gestão abrem para o admin e o evento mostra as chamadas abertas', function () {
    $this->actingAs($this->admin)->get(route('admin.propostas.index', $this->evento))->assertOk();
    $this->get(route('admin.voluntarios.index', $this->evento))->assertOk();
    $this->get(route('admin.eventos.show', $this->evento->id))->assertOk()->assertSee('Minicursos');
    $this->get(route('evento.publico.show', $this->evento->slug))->assertOk()->assertSee('Propor Minicurso/Oficina');
});

it('valida o formulário de proposta: campos, laboratório, apoios e duplicidade', function () {
    $this->actingAs($this->autor);
    $envia = fn (array $extra = []) => $this->post(route('propostas.store', $this->chamada), array_merge($this->dados, $extra));

    $envia(['titulo' => 'ab'])->assertSessionHasErrors('titulo');
    $envia(['resumo' => 'curto'])->assertSessionHasErrors('resumo');
    $envia(['carga_horaria_prevista' => 41])->assertSessionHasErrors('carga_horaria_prevista');
    $envia(['vagas_sugeridas' => 0])->assertSessionHasErrors('vagas_sugeridas');
    $envia(['tipo' => 'palestra'])->assertSessionHasErrors('tipo');
    $envia(['requer_laboratorio' => 1, 'espaco_solicitado' => ''])->assertSessionHasErrors('espaco_solicitado');

    // apoio: nome, e-mail e CPF obrigatórios e válidos; sem repetição; proponente não pode ser apoio
    $envia(['apoios' => [['nome' => 'Fulano', 'email' => 'f@x.com']]])->assertSessionHasErrors('apoios.0.cpf');
    $envia(['apoios' => [['nome' => 'Fulano', 'email' => 'f@x.com', 'cpf' => '111.111.111-11']]])->assertSessionHasErrors('apoios.0.cpf');
    $envia(['apoios' => [['nome' => 'Ana', 'email' => 'a@x.com', 'cpf' => '529.982.247-25'], ['nome' => 'Beto', 'email' => 'b@x.com', 'cpf' => '529.982.247-25']]])->assertSessionHasErrors('apoios');
    $envia(['apoios' => [['nome' => 'Eu mesmo', 'email' => 'outro@x.com', 'cpf' => '111.444.777-35']]])->assertSessionHasErrors('apoios');
    expect(Proposta::count())->toBe(0);

    // laboratório desmarcado descarta o espaço; título repetido é barrado
    $envia(['requer_laboratorio' => 0, 'espaco_solicitado' => 'Lab'])->assertSessionHasNoErrors();
    expect(Proposta::first()->espaco_solicitado)->toBeNull();
    $envia(['titulo' => 'GIT na prática'])->assertSessionHasErrors('titulo');
});

it('valida a decisão: campos da aprovação, janela do evento, carga horária e mensagem obrigatória', function () {
    $p = app(PropostaService::class)->enviar($this->autor, $this->chamada, $this->dados);
    $this->actingAs($this->admin);
    $decide = fn (array $d) => $this->post(route('admin.propostas.decisao', [$this->evento, $p]), $d);
    $dia = $this->evento->data_inicio->copy()->addDay();
    $ok = ['decisao' => 'aprovada', 'data_inicio' => $dia->copy()->setTime(9, 0)->format('Y-m-d\TH:i'), 'data_fim' => $dia->copy()->setTime(13, 0)->format('Y-m-d\TH:i'), 'local_id' => $this->lab->id, 'vagas' => 20, 'carga_horaria' => 4];

    $decide(['decisao' => 'aprovada'])->assertSessionHasErrors(['data_inicio', 'data_fim', 'local_id', 'vagas', 'carga_horaria']);
    $decide(['decisao' => 'ajustes'])->assertSessionHasErrors('parecer');
    $decide(['decisao' => 'reprovada', 'parecer' => ''])->assertSessionHasErrors('parecer');
    $decide(array_merge($ok, ['data_fim' => $ok['data_inicio']]))->assertSessionHasErrors('data_fim');
    $decide(array_merge($ok, ['carga_horaria' => 10]))->assertSessionHasErrors('carga_horaria');
    $decide(array_merge($ok, ['data_inicio' => now()->subYear()->format('Y-m-d\TH:i'), 'data_fim' => now()->subYear()->addHours(4)->format('Y-m-d\TH:i')]))->assertSessionHasErrors('data_inicio');
    expect($p->fresh()->status)->toBe('enviada');

    $decide($ok)->assertSessionHasNoErrors();
    expect($p->fresh()->status)->toBe('aprovada');

    // capacidade não pode ficar abaixo dos inscritos
    $a = Atividade::find($p->fresh()->atividade_id);
    $outro = Participante::create(['nome_completo' => 'Inscrito 2', 'cpf' => '529.982.247-25', 'email' => 'i2@x.com', 'tipo_vinculo' => 'externo']);
    foreach ([Participante::first()->id, $outro->id] as $pid) {
        \Illuminate\Support\Facades\DB::table('atividade_inscricoes')->insert(['atividade_id' => $a->id, 'participante_id' => $pid, 'created_at' => now(), 'updated_at' => now()]);
    }
    $decide(array_merge($ok, ['vagas' => 1]))->assertSessionHas('error');
    expect($a->fresh()->vagas)->toBe(20);
    $decide(array_merge($ok, ['vagas' => 2]))->assertSessionHasNoErrors();
    expect($a->fresh()->vagas)->toBe(2);
    $decide(array_merge($ok, ['vagas' => 0]))->assertSessionHasErrors('vagas');
});

it('proposta retirada não pode ser decidida e apoio só entra no evento com proposta aprovada', function () {
    $svc = app(PropostaService::class);
    $p = $svc->enviar($this->autor, $this->chamada, $this->dados, [['nome' => 'Ajudante', 'email' => 'aj@x.com', 'cpf' => '529.982.247-25']]);

    $this->actingAs($this->admin)->post(route('admin.propostas.apoio-evento', [$this->evento, $p->apoios->first()]))->assertSessionHas('error');
    expect(Inscricao::count())->toBe(0);

    $svc->retirar($p);
    expect(fn () => $svc->retirar($p->fresh()))->toThrow(DomainException::class);
    expect(fn () => $svc->decidir($p->fresh(), $this->admin, 'aprovada', aprovacao()))->toThrow(DomainException::class, 'retirada');
});

it('proponente não edita proposta com a chamada encerrada, exceto quando a organização pediu ajustes', function () {
    $svc = app(PropostaService::class);
    $p = $svc->enviar($this->autor, $this->chamada, $this->dados);
    $this->chamada->update(['fim' => now()->subDay()]);
    expect(fn () => $svc->atualizar($p->fresh(), ['titulo' => 'Novo'] + $this->dados))->toThrow(DomainException::class, 'encerrada');

    $svc->decidir($p->fresh(), $this->admin, 'ajustes', ['parecer' => 'Ajuste a metodologia']);
    expect($svc->atualizar($p->fresh(), ['titulo' => 'Novo título'] + $this->dados)->status)->toBe('enviada');
});

it('valida a chamada: tipo obrigatório, período coerente e descrição em texto simples', function () {
    $this->actingAs($this->admin);
    $base = ['titulo' => 'Chamada 2', 'tipos_aceitos' => ['minicurso'], 'max_por_usuario' => 2];

    $this->post(route('admin.propostas.chamadas.store', $this->evento), array_merge($base, ['tipos_aceitos' => []]))->assertSessionHasErrors('tipos_aceitos');
    $this->post(route('admin.propostas.chamadas.store', $this->evento), array_merge($base, ['inicio' => '2026-12-10T10:00', 'fim' => '2026-12-01T10:00']))->assertSessionHasErrors('fim');
    $this->post(route('admin.propostas.chamadas.store', $this->evento), array_merge($base, ['max_por_usuario' => '']))->assertSessionHasErrors('max_por_usuario');

    $this->post(route('admin.propostas.chamadas.store', $this->evento), array_merge($base, ['descricao' => "Prazo & regras\n<script>x</script>linha 2"]))->assertSessionHasNoErrors();
    $c = ChamadaProposta::where('titulo', 'Chamada 2')->first();
    expect($c->descricao)->toContain('Prazo & regras')->and($c->descricao)->not->toContain('<p>')->and($c->descricao)->not->toContain('<script>');
});

it('proposta já decidida mostra o botão "Mudar status" e esconde o formulário até clicar', function () {
    $svc = app(PropostaService::class);
    $p = $svc->enviar($this->autor, $this->chamada, $this->dados);
    $this->actingAs($this->admin);

    // pendente: formulário direto, sem botão
    $this->get(route('admin.propostas.show', [$this->evento, $p]))->assertOk()->assertDontSee('Mudar status')->assertSee('Registrar decisão');

    $svc->decidir($p, $this->admin, 'aprovada', aprovacao());
    $this->get(route('admin.propostas.show', [$this->evento, $p]))->assertOk()->assertSee('Mudar status')->assertSee('aberto: false', false);

    $svc->decidir($p->fresh(), $this->admin, 'reprovada', ['parecer' => 'Fora do escopo']);
    $this->get(route('admin.propostas.show', [$this->evento, $p]))->assertSee('Mudar status');

    $svc->retirar(Proposta::find($p->id)->fill(['status' => 'enviada']));
    $this->get(route('admin.propostas.show', [$this->evento, $p]))->assertDontSee('Registrar decisão')->assertSee('retirada pelo proponente');
});

it('admin vincula voluntário aprovado a uma atividade pelo cartão da atividade e a lista mostra o vínculo', function () {
    $ch = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'V', 'ativa' => true]);
    $svc = app(VoluntarioService::class);
    $v = $svc->inscrever($this->autor, $ch, ['motivacao' => 'Quero ajudar no evento', 'disponibilidade' => [now()->format('Y-m-d') => ['manha']]]);
    $d = DemandaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'Recepção', 'vagas' => 2, 'carga_horaria' => 3]);
    $this->actingAs($this->admin);

    // ainda não aprovado: não é oferecido e a ação é recusada
    $this->get(route('admin.voluntarios.index', $this->evento))->assertOk()->assertSee('Aprove voluntários');
    $this->post(route('admin.voluntarios.demandas.alocar', [$this->evento, $d]), ['voluntario_id' => $v->id])->assertSessionHas('error');

    $svc->decidir($v, $this->admin, 'aprovado');
    $this->post(route('admin.voluntarios.demandas.alocar', [$this->evento, $d]), ['voluntario_id' => $v->id])->assertSessionHas('success')->assertSessionHas('aba', 'atividades');
    expect($d->voluntarios()->count())->toBe(1);

    // pelo cartão do voluntário o card permanece aberto
    $outra = DemandaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'Apoio', 'carga_horaria' => 2]);
    $this->post(route('admin.voluntarios.alocar', [$this->evento, $v]), ['demanda_id' => $outra->id])->assertSessionHas('abrir', $v->id);

    $this->delete(route('admin.voluntarios.desalocar', [$this->evento, $v, $d]), ['aba' => 'atividades'])->assertSessionHas('success');
    expect($d->voluntarios()->count())->toBe(0);

    // voluntário de outro evento não entra
    $outroEv = mkEvento($this->admin);
    $dOutro = DemandaVoluntario::create(['evento_id' => $outroEv->id, 'titulo' => 'X', 'carga_horaria' => 1]);
    $this->post(route('admin.voluntarios.demandas.alocar', [$this->evento, $dOutro]), ['voluntario_id' => $v->id])->assertNotFound();
});

it('disponibilidade é escolhida em dias e períodos (não texto): valida, filtra dias inválidos e mostra o resumo', function () {
    $ch = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'V', 'ativa' => true]);
    $hoje = now()->format('Y-m-d');
    $this->actingAs($this->autor)->get(route('voluntariado.create', $ch))->assertOk()->assertSee('name="disponibilidade[' . $hoje . '][]"', false)->assertDontSee('<textarea name="disponibilidade"', false);

    $envia = fn (array $d) => $this->post(route('voluntariado.store', $ch), ['motivacao' => 'Quero ajudar no evento todo'] + $d);
    $envia([])->assertSessionHasErrors('disponibilidade');
    $envia(['disponibilidade' => 'toda manhã'])->assertSessionHasErrors('disponibilidade');
    $envia(['disponibilidade' => [$hoje => ['madrugada']]])->assertSessionHasErrors('disponibilidade.*.*');
    expect(Voluntario::count())->toBe(0);

    // dia fora do evento é descartado; se sobrar nada, recusa
    $envia(['disponibilidade' => ['2030-01-01' => ['manha']]])->assertSessionHas('error');
    $envia(['disponibilidade' => [$hoje => ['tarde', 'manha'], '2030-01-01' => ['noite']]])->assertSessionHas('success');

    $v = Voluntario::first();
    expect($v->disponibilidade_dias)->toBe([$hoje => ['manha', 'tarde']]);
    $this->get(route('voluntariado.meu'))->assertSee('Sua disponibilidade')->assertSee(now()->format('d/m') . ': manhã, tarde');
});

it('vincular voluntário a demanda fora da disponibilidade informada é barrado, a menos que o admin ignore', function () {
    $ch = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'V', 'ativa' => true]);
    $svc = app(VoluntarioService::class);
    $hoje = now()->format('Y-m-d');
    $v = $svc->inscrever($this->autor, $ch, ['motivacao' => 'Quero ajudar', 'disponibilidade' => [$hoje => ['manha'], now()->addDay()->format('Y-m-d') => ['tarde']]]);
    $svc->decidir($v, $this->admin, 'aprovado');
    $mk = fn (string $t, $ini, $fim) => DemandaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => $t, 'data_inicio' => $ini, 'data_fim' => $fim, 'carga_horaria' => 2]);

    // dentro: manhã de hoje; fronteira exata (12h) não invade a tarde
    $svc->alocar($v->fresh(), $mk('Manhã ok', now()->setTime(9, 0), now()->setTime(12, 0)));
    // tarde de hoje: não marcada
    $tarde = $mk('Tarde hoje', now()->setTime(14, 0), now()->setTime(16, 0));
    expect(fn () => $svc->alocar($v->fresh(), $tarde))->toThrow(DomainException::class, 'Fora da disponibilidade');
    // atravessa manhã e tarde de hoje
    $meio = $mk('Cruza', now()->setTime(11, 0), now()->setTime(15, 0));
    expect(fn () => $svc->alocar($v->fresh(), $meio))->toThrow(DomainException::class, now()->format('d/m') . ' (tarde)');
    // outro dia: tarde marcada, manhã não
    expect(fn () => $svc->alocar($v->fresh(), $mk('Amanhã manhã', now()->addDay()->setTime(8, 0), now()->addDay()->setTime(10, 0))))->toThrow(DomainException::class);
    $svc->alocar($v->fresh(), $mk('Amanhã tarde', now()->addDay()->setTime(14, 0), now()->addDay()->setTime(16, 0)));
    // trecho no almoço (12–14h) ou depois das 22h está fora do horário de disponibilidade
    expect(fn () => $svc->alocar($v->fresh(), $mk('Almoço', now()->setTime(12, 0), now()->setTime(14, 0))))->toThrow(DomainException::class, 'fora do horário de disponibilidade');
    expect(fn () => $svc->alocar($v->fresh(), $mk('Madrugada', now()->setTime(6, 0), now()->setTime(8, 0))))->toThrow(DomainException::class, 'fora do horário');
    // demanda sem horário não tem o que comparar
    $svc->alocar($v->fresh(), DemandaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'Sem horário', 'carga_horaria' => 1]));

    // admin ignora conscientemente
    $svc->alocar($v->fresh(), $tarde, true);
    expect($v->fresh()->demandas()->count())->toBe(4);
    expect(Voluntario::PERIODOS['manha']['faixa'])->toBe('08h–12h')->and(Voluntario::PERIODOS['tarde']['faixa'])->toBe('14h–18h')->and(Voluntario::PERIODOS['noite']['faixa'])->toBe('18h–22h');

    // a tela sinaliza as opções fora da disponibilidade
    $outra = $mk('Noite', now()->setTime(19, 0), now()->setTime(21, 0));
    $this->actingAs($this->admin)->get(route('admin.voluntarios.index', $this->evento))->assertSee('Noite (' . now()->format('d/m') . ' 19:00) ⚠ fora da disponibilidade', false);
    $this->post(route('admin.voluntarios.demandas.alocar', [$this->evento, $outra]), ['voluntario_id' => $v->id])->assertSessionHas('error');
});

it('máscaras automáticas estão nas telas de formulário e o telefone do voluntário exige DDD', function () {
    $ch = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'V', 'ativa' => true]);
    $this->actingAs($this->autor);
    $this->get(route('voluntariado.create', $ch))->assertOk()->assertSee('__mascarasAutomaticas', false);
    $this->get(route('evento.publico.show', $this->evento->slug))->assertOk()->assertSee('__mascarasAutomaticas', false);

    $base = ['motivacao' => 'Quero ajudar no evento todo', 'disponibilidade' => [now()->format('Y-m-d') => ['manha']]];
    $this->post(route('voluntariado.store', $ch), $base + ['telefone' => '12345'])->assertSessionHasErrors('telefone');
    $this->post(route('voluntariado.store', $ch), $base + ['telefone' => '(93) 91234-5678'])->assertSessionHasNoErrors();
    expect(Voluntario::first()->telefone)->toBe('(93) 91234-5678');
});

it('criar atividade do evento já cria a demanda de voluntários, que acompanha mudanças e some com a atividade', function () {
    $this->actingAs($this->admin);
    $inicio = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');

    $this->post(route('admin.eventos.atividades.store', $this->evento), ['titulo' => 'Palestra Magna', 'tipo' => 'palestra', 'responsavel' => 'Profa. Maria', 'local_id' => $this->sala->id, 'data_inicio' => $inicio, 'carga_horaria' => 2, 'vagas' => 30])->assertSessionHas('success');
    $a = Atividade::where('titulo', 'Palestra Magna')->first();
    $d = DemandaVoluntario::where('atividade_id', $a->id)->first();
    expect($d)->not->toBeNull()->and($d->automatica)->toBeTrue()->and($d->titulo)->toBe('Apoio: Palestra Magna')
        ->and($d->local_id)->toBe($this->sala->id)->and($d->local)->toBe('Sala 2')->and($d->responsavel_nome)->toBe('Profa. Maria')
        ->and($d->carga_horaria)->toBe(2.0)->and($d->data_inicio->format('H:i'))->toBe('09:00')->and($d->data_fim->format('H:i'))->toBe('11:00');

    // atividade da aprovação de proposta também gera
    $p = app(PropostaService::class)->enviar($this->autor, $this->chamada, $this->dados);
    app(PropostaService::class)->decidir($p, $this->admin, 'aprovada', aprovacao(['data_inicio' => now()->addDays(2)->setTime(9, 0), 'data_fim' => now()->addDays(2)->setTime(13, 0)]));
    expect(DemandaVoluntario::where('atividade_id', $p->fresh()->atividade_id)->exists())->toBeTrue();

    // mudanças na atividade refletem na demanda (título só se o admin não o renomeou)
    $this->put(route('admin.atividades.update', $a), ['titulo' => 'Palestra Magna II', 'tipo' => 'palestra', 'responsavel' => 'Prof. João', 'local_id' => $this->lab->id, 'data_inicio' => $inicio, 'carga_horaria' => 3, 'vagas' => 20])->assertSessionHasNoErrors();
    $d->refresh();
    expect($d->titulo)->toBe('Apoio: Palestra Magna II')->and($d->local)->toBe('Lab 1 (Bloco A)')->and($d->responsavel_nome)->toBe('Prof. João')->and($d->carga_horaria)->toBe(3.0)->and($d->data_fim->format('H:i'))->toBe('12:00');
    $d->update(['titulo' => 'Recepção do auditório']);
    $this->put(route('admin.atividades.update', $a), ['titulo' => 'Outro nome', 'tipo' => 'palestra', 'data_inicio' => $inicio, 'carga_horaria' => 3, 'vagas' => 20, 'local_id' => $this->lab->id])->assertSessionHasNoErrors();
    expect($d->fresh()->titulo)->toBe('Recepção do auditório');

    // o voluntário vê a atividade já na lista de vincular; excluir a atividade remove a demanda automática
    $a->delete();
    expect(DemandaVoluntario::where('atividade_id', $a->id)->count())->toBe(0);
});

it('"Gerar das atividades do evento" cria só as demandas que faltam', function () {
    $this->actingAs($this->admin);
    $ini = now()->addDay()->setTime(9, 0);
    $a = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Antiga', 'tipo' => 'palestra', 'carga_horaria' => 2, 'data_inicio' => $ini, 'data_fim' => $ini->copy()->addHours(2), 'token_frequencia' => 'tokantiga1']);
    DemandaVoluntario::where('atividade_id', $a->id)->delete();   // como as atividades criadas antes deste recurso

    $this->post(route('admin.voluntarios.demandas.gerar', $this->evento))->assertSessionHas('success');
    expect(DemandaVoluntario::where('atividade_id', $a->id)->count())->toBe(1);
    $this->post(route('admin.voluntarios.demandas.gerar', $this->evento))->assertSessionHas('error');
    expect(DemandaVoluntario::where('atividade_id', $a->id)->count())->toBe(1);
});

it('uma atividade aceita vários voluntários: define a quantidade, vincula vários de uma vez e respeita o limite', function () {
    $this->actingAs($this->admin);
    $dia = now()->addDay()->setTime(9, 0);
    $this->post(route('admin.eventos.atividades.store', $this->evento), ['titulo' => 'Feira', 'tipo' => 'oficina', 'data_inicio' => $dia->format('Y-m-d\TH:i'), 'carga_horaria' => 2, 'vagas' => 40, 'voluntarios_necessarios' => 3])->assertSessionHas('success');
    $at = Atividade::where('titulo', 'Feira')->first();
    $d = DemandaVoluntario::where('atividade_id', $at->id)->first();
    expect($at->voluntarios_necessarios)->toBe(3)->and($d->vagas)->toBe(3);

    // padrão 2 quando o campo não é enviado; 0 = sem limite
    $this->post(route('admin.eventos.atividades.store', $this->evento), ['titulo' => 'Sem campo', 'tipo' => 'oficina', 'data_inicio' => $dia->copy()->addDay()->format('Y-m-d\TH:i'), 'carga_horaria' => 1, 'vagas' => 10]);
    expect(DemandaVoluntario::where('titulo', 'Apoio: Sem campo')->value('vagas'))->toBe(2);
    $this->post(route('admin.eventos.atividades.store', $this->evento), ['titulo' => 'Livre', 'tipo' => 'oficina', 'data_inicio' => $dia->copy()->addDays(2)->format('Y-m-d\TH:i'), 'carga_horaria' => 1, 'vagas' => 10, 'voluntarios_necessarios' => 0]);
    expect(DemandaVoluntario::where('titulo', 'Apoio: Livre')->value('vagas'))->toBeNull();

    // quatro voluntários aprovados, todos disponíveis no dia
    $ch = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'V', 'ativa' => true]);
    $svc = app(VoluntarioService::class);
    $ids = [];
    foreach (range(1, 4) as $i) {
        $u = mkUser('user');
        Participante::create(['user_id' => $u->id, 'nome_completo' => "Voluntário {$i}", 'cpf' => ['935.411.347-80', '529.982.247-25', '390.533.447-05', '168.995.350-09'][$i - 1], 'email' => $u->email, 'tipo_vinculo' => 'externo']);
        $v = $svc->inscrever($u, $ch, ['motivacao' => 'Quero ajudar', 'disponibilidade' => [$dia->format('Y-m-d') => ['manha']]]);
        $svc->decidir($v, $this->admin, 'aprovado');
        $ids[] = $v->id;
    }

    // vincula 2 de uma vez
    $this->post(route('admin.voluntarios.demandas.alocar', [$this->evento, $d]), ['voluntario_ids' => [$ids[0], $ids[1]]])->assertSessionHas('success', '2 voluntários vinculados à atividade.');
    expect($d->voluntarios()->count())->toBe(2);

    // o terceiro cabe; o quarto estoura o limite de 3 e é informado, sem desfazer os demais
    $r = $this->post(route('admin.voluntarios.demandas.alocar', [$this->evento, $d]), ['voluntario_ids' => [$ids[2], $ids[3]]]);
    $r->assertSessionHas('success')->assertSessionHas('error');
    expect($d->voluntarios()->count())->toBe(3);
    $this->get(route('admin.voluntarios.index', $this->evento))->assertOk()->assertSee('3/3 voluntário(s) · completa');

    // sem escolher ninguém
    $this->post(route('admin.voluntarios.demandas.alocar', [$this->evento, $d]), [])->assertSessionHasErrors('voluntario_ids');

    // editar a quantidade na atividade atualiza a demanda
    $this->put(route('admin.atividades.update', $at), ['titulo' => 'Feira', 'tipo' => 'oficina', 'data_inicio' => $dia->format('Y-m-d\TH:i'), 'carga_horaria' => 2, 'vagas' => 40, 'voluntarios_necessarios' => 5])->assertSessionHasNoErrors();
    expect($d->fresh()->vagas)->toBe(5);
});

it('a tela de edição da atividade permite ver, adicionar e remover os voluntários vinculados', function () {
    $this->actingAs($this->admin);
    $dia = now()->addDay()->setTime(9, 0);
    $this->post(route('admin.eventos.atividades.store', $this->evento), ['titulo' => 'Mostra', 'tipo' => 'oficina', 'data_inicio' => $dia->format('Y-m-d\TH:i'), 'carga_horaria' => 2, 'vagas' => 20, 'voluntarios_necessarios' => 2]);
    $at = Atividade::where('titulo', 'Mostra')->first();
    $d = DemandaVoluntario::where('atividade_id', $at->id)->first();

    $ch = ChamadaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'V', 'ativa' => true]);
    $svc = app(VoluntarioService::class);
    $v = $svc->inscrever($this->autor, $ch, ['motivacao' => 'Quero ajudar', 'disponibilidade' => [$dia->format('Y-m-d') => ['manha']]]);
    $svc->decidir($v, $this->admin, 'aprovado');

    $this->get(route('admin.atividades.edit', $at))->assertOk()->assertSee('Voluntários da atividade')->assertSee('Adicionar voluntários')->assertSee('Nenhum voluntário vinculado');
    $this->post(route('admin.voluntarios.demandas.alocar', [$this->evento, $d]), ['voluntario_ids' => [$v->id]])->assertSessionHas('success');
    $this->get(route('admin.atividades.edit', $at))->assertSee('Remover')->assertSee($this->autor->name);

    $this->delete(route('admin.voluntarios.desalocar', [$this->evento, $v, $d]))->assertSessionHas('success');
    expect($d->voluntarios()->count())->toBe(0);

    // atividade antiga sem demanda oferece criá-la
    $d->delete();
    $this->get(route('admin.atividades.edit', $at))->assertSee('Criar demanda de voluntários');
});
