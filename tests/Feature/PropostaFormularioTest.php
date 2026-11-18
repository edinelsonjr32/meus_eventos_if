<?php

use App\Models\Atividade;
use App\Models\ChamadaProposta;
use App\Models\Local;
use App\Models\Participante;
use App\Models\Proposta;
use App\Services\PropostaService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->admin = mkUser('admin');
    $this->evento = mkEvento($this->admin);
    $this->autor = mkUser('user');
    Participante::create(['user_id' => $this->autor->id, 'nome_completo' => 'Prof. Autor', 'cpf' => '111.444.777-35', 'email' => $this->autor->email, 'tipo_vinculo' => 'servidor']);
    $this->chamada = ChamadaProposta::create(['evento_id' => $this->evento->id, 'titulo' => 'Minicursos', 'tipos_aceitos' => ['minicurso', 'oficina'], 'ativa' => true, 'max_por_usuario' => 5, 'criado_por' => $this->admin->id]);
    $this->hoje = now()->format('Y-m-d');
    $this->base = [
        'tipo' => 'oficina', 'titulo' => 'Oficina de Git', 'resumo' => str_repeat('Aprender versionamento na prática. ', 5), 'carga_horaria_prevista' => 4, 'vagas_sugeridas' => 25,
        'minicurriculo' => 'Professora de computação, com dez anos de experiência em ensino técnico.', 'contato_whatsapp' => '93912345678', 'disponibilidade' => [$this->hoje => ['manha']],
    ];
});

it('exige minicurrículo, WhatsApp com DDD e disponibilidade; formata o telefone e guarda os campos novos', function () {
    $this->actingAs($this->autor);
    $envia = fn (array $x = []) => $this->post(route('propostas.store', $this->chamada), array_merge($this->base, $x));

    $envia(['minicurriculo' => ''])->assertSessionHasErrors('minicurriculo');
    $envia(['minicurriculo' => 'curto'])->assertSessionHasErrors('minicurriculo');
    $envia(['contato_whatsapp' => ''])->assertSessionHasErrors('contato_whatsapp');
    $envia(['contato_whatsapp' => '12345'])->assertSessionHasErrors('contato_whatsapp');
    $envia(['disponibilidade' => []])->assertSessionHasErrors('disponibilidade');
    $envia(['disponibilidade' => ['2030-01-01' => ['manha']]])->assertSessionHasErrors('disponibilidade');   // dia fora do evento
    $envia(['disponibilidade' => [$this->hoje => ['madrugada']]])->assertSessionHasErrors('disponibilidade');   // período inexistente é descartado
    $envia(['pre_requisitos' => str_repeat('x', 2001)])->assertSessionHasErrors('pre_requisitos');
    expect(Proposta::count())->toBe(0);

    $envia(['pre_requisitos' => 'Lógica básica', 'materiais_participante' => 'Notebook'])->assertSessionHasNoErrors();
    $p = Proposta::first();
    expect($p->contato_whatsapp)->toBe('(93) 91234-5678')->and($p->pre_requisitos)->toBe('Lógica básica')->and($p->materiais_participante)->toBe('Notebook')
        ->and($p->minicurriculo)->toContain('Professora')->and($p->disponibilidade_dias)->toBe([$this->hoje => ['manha']]);

    // páginas do proponente e do admin mostram os campos novos
    $this->get(route('propostas.show', $p))->assertSee('Minicurrículo')->assertSee('(93) 91234-5678')->assertSee('Pré-requisitos')->assertSee(now()->format('d/m') . ': manhã', false);
    $this->actingAs($this->admin)->get(route('admin.propostas.show', [$this->evento, $p]))->assertSee('https://wa.me/5593912345678', false)->assertSee('O proponente pode ministrar');
});

it('aprovação confere a disponibilidade do proponente e permite confirmar assim mesmo', function () {
    $svc = app(PropostaService::class);
    $p = $svc->enviar($this->autor, $this->chamada, $this->base);   // disponível só pela manhã de hoje
    $lab = Local::create(['evento_id' => $this->evento->id, 'nome' => 'Lab', 'tipo' => 'laboratorio']);
    $hoje8 = now()->setTime(8, 0);
    $aprovar = fn (array $x) => $svc->decidir($p->fresh(), $this->admin, 'aprovada', $x + ['local_id' => $lab->id, 'vagas' => 20, 'carga_horaria' => 4]);

    // à tarde: fora do que ele marcou
    expect(fn () => $aprovar(['data_inicio' => now()->setTime(14, 0), 'data_fim' => now()->setTime(18, 0)]))->toThrow(DomainException::class, 'Fora da disponibilidade informada pelo proponente');
    expect(Atividade::count())->toBe(0);

    // dentro da manhã: aprova
    expect($aprovar(['data_inicio' => $hoje8, 'data_fim' => $hoje8->copy()->addHours(4)])->status)->toBe('aprovada');

    // confirmando assim mesmo (outra proposta, horário fora)
    $p2 = $svc->enviar($this->autor, $this->chamada, ['titulo' => 'Outra'] + $this->base);
    $r = $svc->decidir($p2, $this->admin, 'aprovada', ['data_inicio' => now()->setTime(14, 0), 'data_fim' => now()->setTime(18, 0), 'local_id' => $lab->id, 'vagas' => 20, 'carga_horaria' => 4, 'ignorar_conflito' => true]);
    expect($r->status)->toBe('aprovada');
});

it('formulário traz validação no navegador, rascunho, sessão viva, contadores e proteção contra duplo envio', function () {
    $r = $this->actingAs($this->autor)->get(route('propostas.create', $this->chamada))->assertOk();
    $r->assertSee('formProposta', false)->assertSee('novalidate', false)->assertSee('FormSeguro', false)->assertSee('proposta-rascunho:c' . $this->chamada->id . ':' . $this->autor->id, false)
        ->assertSee('Minicurrículo', false)->assertSee('WhatsApp para contato', false)->assertSee('Dias e períodos em que você pode ministrar', false)
        ->assertSee('name="disponibilidade[' . $this->hoje . '][]"', false)->assertSee(':disabled="enviando"', false)->assertSee('/3000 (mínimo 80)', false);

    // edição usa a chave própria do rascunho
    $p = app(PropostaService::class)->enviar($this->autor, $this->chamada, $this->base);
    $this->get(route('propostas.edit', $p))->assertOk()->assertSee('proposta-rascunho:e' . $p->id . ':' . $this->autor->id, false)->assertSee('Oficina de Git', false);

    // visitante vê o formulário bloqueado e sem rascunho
    auth()->logout(); $this->app['auth']->forgetGuards();
    $this->get(route('propostas.create', $this->chamada))->assertOk()->assertSee('Entre ou crie sua conta')->assertSee('<fieldset disabled', false)->assertDontSee('proposta-rascunho:', false);
});

it('erros do servidor voltam mapeados por campo (inclusive pessoas de apoio) mantendo o que foi digitado', function () {
    $r = $this->actingAs($this->autor)->from(route('propostas.create', $this->chamada))
        ->post(route('propostas.store', $this->chamada), array_merge($this->base, ['minicurriculo' => '', 'apoios' => [['nome' => 'Ana Apoio', 'email' => 'ana@x.com', 'cpf' => '111.111.111-11']]]))
        ->assertRedirect(route('propostas.create', $this->chamada));
    $r->assertSessionHasErrors(['minicurriculo', 'apoios.0.cpf']);

    $pagina = $this->get(route('propostas.create', $this->chamada))->assertOk();
    $pagina->assertSee('apoio_0_cpf', false)->assertSee('Oficina de Git', false)->assertSee('Ana Apoio', false)->assertSee('id="alerta-erros"', false);
});

it('depois de enviar, a tela da proposta apaga o rascunho do navegador', function () {
    $this->actingAs($this->autor)->post(route('propostas.store', $this->chamada), $this->base)->assertSessionHasNoErrors();
    $p = Proposta::firstOrFail();
    $this->get(route('propostas.show', $p))->assertOk()->assertSee("localStorage.removeItem('proposta-rascunho:'", false);
});
