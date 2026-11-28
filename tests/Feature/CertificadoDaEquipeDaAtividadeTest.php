<?php

use App\Models\Atividade;
use App\Models\AtividadeEquipe;
use App\Models\ChamadaProposta;
use App\Models\Participante;
use App\Models\Proposta;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__ . '/../Helpers/TrabalhosHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->dono = mkUser('admin', 'chefe@ifpa.edu.br');
    $this->evento = mkEvento($this->dono);
    $this->autor = mkUser('user', 'autor@gmail.com');
    $this->ana = Participante::create(['nome_completo' => 'Ana Proponente', 'cpf' => '529.982.247-25', 'email' => 'autor@gmail.com', 'tipo_vinculo' => 'servidor', 'user_id' => $this->autor->id]);
    $this->atividade = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Oficina de Solos', 'tipo' => 'oficina', 'responsavel' => 'Ana Proponente',
        'token_frequencia' => \Illuminate\Support\Str::random(10), 'data_inicio' => now()->subHour(), 'data_fim' => now()->addHour(), 'carga_horaria' => 4]);
    $chamada = ChamadaProposta::create(['evento_id' => $this->evento->id, 'titulo' => 'Chamada', 'slug' => 'chamada-x', 'ativa' => true, 'inicio' => now()->subDay(), 'fim' => now()->addDay(), 'tipos' => ['oficina']]);
    $this->proposta = Proposta::create(['chamada_id' => $chamada->id, 'evento_id' => $this->evento->id, 'user_id' => $this->autor->id, 'participante_id' => $this->ana->id, 'tipo' => 'oficina',
        'titulo' => 'Oficina', 'resumo' => 'Resumo', 'carga_horaria_prevista' => 4, 'vagas_sugeridas' => 20, 'status' => 'aprovada', 'atividade_id' => $this->atividade->id]);
    $this->beto = $this->proposta->apoios()->create(['nome' => 'Beto Ajudante', 'email' => 'beto@gmail.com', 'cpf' => '111.444.777-35', 'funcao' => 'ajudante']);
    $this->url = route('admin.atividades.equipe.presenca', $this->atividade);
});

it('a presença lançada libera o certificado, que aparece na conta de quem tem o mesmo e-mail', function () {
    $beto = mkUser('user', 'beto@gmail.com');
    $this->actingAs($beto)->get(route('inscrito.dashboard'))->assertOk()->assertDontSee('Meus certificados de ministrante e ajudante');

    $this->actingAs($this->dono)->get(route('admin.atividades.edit', $this->atividade))->assertSee('Presença e certificados da equipe')->assertSee('Lançar presença');
    $this->post($this->url, ['chave' => "apoio:{$this->beto->id}"])->assertSessionHas('success');
    $registro = AtividadeEquipe::sole();
    expect($registro->funcao)->toBe('ajudante')->and($registro->certificadoEmitido())->toBeFalse();

    // ainda não emitido: o código não vale
    $this->get(route('certificado.atividade.download', $registro->hash_validacao))->assertNotFound();

    $this->actingAs($beto)->get(route('inscrito.dashboard'))->assertSee('Meus certificados de ministrante e ajudante')->assertSee('Oficina de Solos');
    $this->get(route('certificado.atividade.meu', $registro))->assertRedirect(route('certificado.atividade.download', $registro->hash_validacao));
    $this->get(route('certificado.atividade.download', $registro->hash_validacao))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('certificado.atividade.validar', $registro->hash_validacao))->assertSee('Certificado autêntico')->assertSee('Ajudante');
    $this->get(route('certificado.validar', $registro->hash_validacao))->assertRedirect(route('certificado.atividade.validar', $registro->hash_validacao));
});

it('o proponente recebe como ministrante e outra pessoa não acessa o certificado dele', function () {
    $this->actingAs($this->dono)->post($this->url, ['chave' => 'proponente'])->assertSessionHas('success');
    $registro = AtividadeEquipe::sole();
    expect($registro->funcao)->toBe('ministrante')->and($registro->participante_id)->toBe($this->ana->id);

    $this->actingAs(mkUser('user', 'curioso@gmail.com'))->get(route('certificado.atividade.meu', $registro))->assertNotFound();
    $this->actingAs($this->autor)->get(route('inscrito.dashboard'))->assertSee('Oficina de Solos');
});

it('não lança presença antes de começar, sem CPF ou por quem não administra', function () {
    $this->actingAs($this->autor)->post($this->url, ['chave' => 'proponente'])->assertRedirect();
    $this->atividade->update(['data_inicio' => now()->addDay(), 'data_fim' => now()->addDays(2)]);
    $this->actingAs($this->dono)->post($this->url, ['chave' => 'proponente'])->assertSessionHas('error');

    $this->atividade->update(['data_inicio' => now()->subHour()]);
    $this->beto->update(['cpf' => null]);
    $this->post($this->url, ['chave' => "apoio:{$this->beto->id}"])->assertSessionHas('error');
    expect(AtividadeEquipe::count())->toBe(0);
});

it('emite pelo administrador, acompanha a troca de atribuição, impede excluir a atividade e permite desfazer', function () {
    $this->actingAs($this->dono)->post($this->url, ['chave' => "apoio:{$this->beto->id}"]);
    $registro = AtividadeEquipe::sole();

    $this->post(route('admin.atividades.equipe.certificado', [$this->atividade, $registro]))->assertSessionHas('success');
    expect($registro->refresh()->certificadoEmitido())->toBeTrue();

    $this->post(route('admin.propostas.apoio-funcao', [$this->evento->id, $this->beto->id]), ['funcao' => 'ministrante']);
    expect($registro->refresh()->funcao)->toBe('ministrante');

    $this->delete(route('admin.atividades.destroy', $this->atividade))->assertSessionHas('error');
    expect(Atividade::count())->toBe(1);

    $this->delete(route('admin.atividades.equipe.destroy', [$this->atividade, $registro]))->assertSessionHas('success');
    $this->get(route('certificado.atividade.download', $registro->hash_validacao))->assertNotFound();
});

it('atividade sem certificado não emite', function () {
    $this->atividade->update(['gera_certificado' => false]);
    $this->actingAs($this->dono)->post($this->url, ['chave' => 'proponente']);
    $registro = AtividadeEquipe::sole();

    $this->post(route('admin.atividades.equipe.certificado', [$this->atividade, $registro]))->assertSessionHas('error');
    $this->actingAs($this->autor)->get(route('inscrito.dashboard'))->assertDontSee('Meus certificados de ministrante e ajudante');
});
