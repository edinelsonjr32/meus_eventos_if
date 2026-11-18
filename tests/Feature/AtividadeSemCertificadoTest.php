<?php

use App\Models\Atividade;
use App\Models\Frequencia;
use App\Models\Participante;

beforeEach(function () {
    $this->admin = mkUser('admin');
    $this->evento = mkEvento($this->admin);
    $this->actingAs($this->admin);
    $this->dados = ['titulo' => 'Final de vôlei', 'tipo' => 'jogo', 'data_inicio' => now()->addDay()->format('Y-m-d\TH:i'), 'carga_horaria' => 2];
});

function novaAtividade(array $extra): Atividade
{
    return Atividade::create($extra + ['titulo' => 'Jogo', 'tipo' => 'jogo', 'carga_horaria' => 2, 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHours(2), 'token_frequencia' => \Illuminate\Support\Str::random(10)]);
}

function presencaEm(Atividade $a): Frequencia
{
    $p = Participante::create(['nome_completo' => 'Atleta Teste', 'cpf' => '529.982.247-25', 'email' => 'atleta@x.com', 'tipo_vinculo' => 'externo']);

    return Frequencia::create(['participante_id' => $p->id, 'atividade_id' => $a->id, 'data_registro' => now(), 'hash_validacao' => (string) \Illuminate\Support\Str::uuid(), 'tipo_participacao' => 'ouvinte']);
}

it('atividade gera certificado por padrão e pode ser criada sem certificado (jogo)', function () {
    $this->post(route('admin.eventos.atividades.store', $this->evento), $this->dados)->assertSessionHas('success');
    expect(Atividade::first()->emiteCertificado())->toBeTrue();

    $this->post(route('admin.eventos.atividades.store', $this->evento), array_merge($this->dados, ['titulo' => 'Jogo 2', 'gera_certificado' => '0']))->assertSessionHas('success');
    $jogo = Atividade::where('titulo', 'Jogo 2')->first();
    expect($jogo->emiteCertificado())->toBeFalse()->and($jogo->tipo)->toBe('jogo');
});

it('tipo da atividade é livre (gincana, filmes, texto próprio) e exibido de forma amigável', function () {
    foreach (['Gincana de robótica', 'Exibição de filme', 'exposicao'] as $i => $tipo) {
        $this->post(route('admin.eventos.atividades.store', $this->evento), array_merge($this->dados, ['titulo' => "Atv $i", 'tipo' => $tipo, 'gera_certificado' => '0']))->assertSessionHasNoErrors();
    }
    expect(Atividade::pluck('tipo')->all())->toBe(['Gincana de robótica', 'Exibição de filme', 'exposicao']);
    expect(Atividade::where('tipo', 'exposicao')->first()->tipoRotulo())->toBe('Exposição')
        ->and(novaAtividade(['evento_id' => $this->evento->id, 'tipo' => 'mesa_redonda'])->tipoRotulo())->toBe('Mesa Redonda');
    $this->post(route('admin.eventos.atividades.store', $this->evento), array_merge($this->dados, ['tipo' => '']))->assertSessionHasErrors('tipo');
    $this->get(route('admin.eventos.show', $this->evento))->assertOk()->assertSee('Gincana de robótica');
});

it('edita a opção de gerar certificado', function () {
    $a = novaAtividade(['evento_id' => $this->evento->id]);
    $this->put(route('admin.atividades.update', $a), $this->dados + ['gera_certificado' => '0'])->assertSessionHasNoErrors();
    expect($a->fresh()->emiteCertificado())->toBeFalse();
    $this->put(route('admin.atividades.update', $a), $this->dados + ['gera_certificado' => '1'])->assertSessionHasNoErrors();
    expect($a->fresh()->emiteCertificado())->toBeTrue();
});

it('sem certificado: frequência registrada, mas validação e download não existem', function () {
    $a = novaAtividade(['evento_id' => $this->evento->id, 'gera_certificado' => false]);
    $f = presencaEm($a);

    $this->get(route('certificado.download', $f->hash_validacao))->assertNotFound();
    $this->get(route('certificado.validar', $f->hash_validacao))->assertSee('Não Encontrado');
    $this->get(route('admin.atividades.participantes', $a))->assertOk()->assertDontSee(route('certificado.download', $f->hash_validacao));
    expect(Frequencia::comCertificado()->count())->toBe(0);
});

it('com certificado: validação e lista mostram o certificado', function () {
    $a = novaAtividade(['evento_id' => $this->evento->id]);
    $f = presencaEm($a);

    $this->get(route('certificado.validar', $f->hash_validacao))->assertSee('Autêntico');
    $this->get(route('admin.atividades.participantes', $a))->assertSee(route('certificado.download', $f->hash_validacao));
    expect(Frequencia::comCertificado()->count())->toBe(1);
});

it('evento pode não aceitar inscrições e não aparecer no portal', function () {
    $this->evento->update(['slug' => 'jogos-if', 'data_inicio' => now()->addDays(2), 'data_fim' => now()->addDays(5), 'configuracoes' => ['inscricoes_abertas' => false, 'exibir_no_portal' => false]]);
    expect($this->evento->fresh()->aceitaInscricoes())->toBeFalse()->and($this->evento->fresh()->exibidoNoPortal())->toBeFalse();

    auth()->logout();
    $this->get(route('evento.publico.show', 'jogos-if'))->assertOk()->assertDontSee('Quero me inscrever')->assertDontSee('id="inscricao"', false)->assertSee('Evento aberto');
    $this->get('/')->assertOk()->assertDontSee($this->evento->titulo);

    $user = mkUser('participante');
    $this->actingAs($user)->post(route('evento.publico.store', 'jogos-if'), [])->assertRedirect(route('evento.publico.show', 'jogos-if'))->assertSessionHas('error');
    expect(\App\Models\Inscricao::count())->toBe(0);

    // padrão: aceita inscrições e aparece no portal
    $this->evento->update(['configuracoes' => []]);
    expect($this->evento->fresh()->aceitaInscricoes())->toBeTrue();
    $this->get('/')->assertSee($this->evento->titulo);
    $this->get(route('evento.publico.show', 'jogos-if'))->assertSee('Quero me inscrever');
});

it('edita as opções de visibilidade e inscrição do evento', function () {
    $dados = ['titulo' => $this->evento->titulo, 'descricao' => 'x', 'data_inicio' => $this->evento->data_inicio->format('Y-m-d\TH:i'), 'data_fim' => $this->evento->data_fim->format('Y-m-d\TH:i'), 'local' => $this->evento->local, 'cor_fundo' => '#10b981'];
    $this->put(route('admin.eventos.update', $this->evento), $dados + ['exibir_no_portal' => '0', 'inscricoes_abertas' => '0'])->assertSessionHasNoErrors();
    expect($this->evento->fresh()->exibidoNoPortal())->toBeFalse()->and($this->evento->fresh()->aceitaInscricoes())->toBeFalse();
    $this->get(route('admin.eventos.edit', $this->evento))->assertOk()->assertSee('Evento visível publicamente');
});
