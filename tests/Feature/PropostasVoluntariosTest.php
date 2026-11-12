<?php

use App\Mail\CandidaturaDecididaMail;
use App\Models\Atividade;
use App\Models\ChamadaProposta;
use App\Models\ChamadaVoluntario;
use App\Models\DemandaVoluntario;
use App\Models\Equipe;
use App\Models\Inscricao;
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
    $this->autor = mkUser('user');
    Participante::create(['user_id' => $this->autor->id, 'nome_completo' => 'Prof. Autor', 'cpf' => '111.444.777-35', 'email' => $this->autor->email, 'tipo_vinculo' => 'servidor']);
    $this->chamada = ChamadaProposta::create(['evento_id' => $this->evento->id, 'titulo' => 'Minicursos', 'tipos_aceitos' => ['minicurso', 'oficina'], 'ativa' => true, 'max_por_usuario' => 2, 'criado_por' => $this->admin->id]);
    $this->dados = ['tipo' => 'minicurso', 'titulo' => 'Git na prática', 'resumo' => str_repeat('Aprender versionamento. ', 10), 'carga_horaria_prevista' => 4, 'vagas_sugeridas' => 30];
});

function aprovacao(array $extra = []): array
{
    return $extra + ['data_inicio' => now()->addDays(1)->setTime(9, 0), 'data_fim' => now()->addDays(1)->setTime(13, 0), 'local' => 'Bloco A', 'espaco' => 'Lab 1', 'vagas' => 25, 'carga_horaria' => 4, 'equipamentos' => 'Projetor'];
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
    expect($p->status)->toBe('aprovada')->and($a->espaco)->toBe('Lab 1')->and($a->vagas)->toBe(25)->and($a->equipamentos)->toBe('Projetor')->and($a->tipo)->toBe('minicurso');
    Mail::assertQueued(CandidaturaDecididaMail::class);
});

it('bloqueia conflito de espaço/horário, a menos que o admin ignore', function () {
    $svc = app(PropostaService::class);
    $p1 = $svc->enviar($this->autor, $this->chamada, $this->dados);
    $p2 = $svc->enviar($this->autor, $this->chamada, ['titulo' => 'Docker'] + $this->dados);
    $svc->decidir($p1, $this->admin, 'aprovada', aprovacao());

    expect(fn () => $svc->decidir($p2, $this->admin, 'aprovada', aprovacao(['local' => 'Bloco B'])))->toThrow(DomainException::class, 'Conflito');
    expect($svc->decidir($p2, $this->admin, 'aprovada', aprovacao(['local' => 'Bloco B', 'ignorar_conflito' => true]))->status)->toBe('aprovada');
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
    $this->post(route('voluntariado.store', $ch), ['motivacao' => 'Quero ajudar na organização do evento', 'disponibilidade' => 'Manhãs'])->assertRedirect();
    $v = Voluntario::first();
    expect($v->status)->toBe('inscrito');
    expect(fn () => $svc->inscrever($this->autor, $ch, ['motivacao' => 'x', 'disponibilidade' => 'y']))->toThrow(DomainException::class);

    $d1 = DemandaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'Credenciamento', 'data_inicio' => now()->subDay()->setTime(8, 0), 'data_fim' => now()->subDay()->setTime(12, 0), 'local' => 'Hall', 'vagas' => 2, 'carga_horaria' => 4, 'responsavel_nome' => 'Servidor Fulano', 'responsavel_contato' => '9999']);
    $d2 = DemandaVoluntario::create(['evento_id' => $this->evento->id, 'titulo' => 'Apoio sala', 'data_inicio' => now()->subDay()->setTime(10, 0), 'data_fim' => now()->subDay()->setTime(14, 0), 'vagas' => 2, 'carga_horaria' => 4]);

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

    $v1 = $svc->inscrever($this->autor, $ch, ['motivacao' => 'a', 'disponibilidade' => 'b']);
    $v2 = $svc->inscrever($outro, $ch, ['motivacao' => 'a', 'disponibilidade' => 'b']);
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
    $ok = ['decisao' => 'aprovada', 'data_inicio' => $dia->copy()->setTime(9, 0)->format('Y-m-d\TH:i'), 'data_fim' => $dia->copy()->setTime(13, 0)->format('Y-m-d\TH:i'), 'local' => 'Bloco A', 'vagas' => 20, 'carga_horaria' => 4];

    $decide(['decisao' => 'aprovada'])->assertSessionHasErrors(['data_inicio', 'data_fim', 'local', 'vagas', 'carga_horaria']);
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
