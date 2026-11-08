<?php

use App\Models\Atividade;
use App\Models\CriterioApresentacao;
use App\Models\Participante;
use App\Services\PendenciasTrabalho;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->autor = mkUser('user', 'autor@x.com');
    $this->svc = app(PendenciasTrabalho::class);
});

function pend($ctx, $t): array
{
    return $ctx->svc->calcular(collect([$t->fresh(['autores', 'avaliacoes', 'avaliacoesApresentacao', 'evento.trabalhoConfig', 'certificados'])]))[$t->id];
}

function aprovadoComApresentador($ctx, ?array $autores = null, string $titulo = 'Aprovado')
{
    return mkTrabalho($ctx->evento, $ctx->autor, ['titulo' => $titulo, 'autores' => $autores ?? [
        ['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => true],
        ['nome_completo' => 'Caio', 'cpf' => '529.982.247-25', 'email' => 'caio@x.com', 'tipo_autor' => 'coautor'],
    ]], 'aprovado');
}

it('aprovado sem apresentador com conta vinculada fica "Aguardando vincular apresentador"', function () {
    $t = aprovadoComApresentador($this);
    expect(pend($this, $t))->toContain('vincular_apresentador')->toContain('vincular_sessao');

    // a conta da Ana passa a existir (participante ligado a um usuário com o mesmo CPF): vinculado
    $ana = mkUser('user', 'ana@x.com');
    Participante::create(['user_id' => $ana->id, 'nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_vinculo' => 'externo']);
    expect(pend($this, $t))->not->toContain('vincular_apresentador');
});

it('apresentador marcado mas sem conta (participante sem usuário) continua pendente; com e-mail igual ao de conta, vincula', function () {
    $t = aprovadoComApresentador($this);
    Participante::create(['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_vinculo' => 'externo']);   // sem user_id
    expect(pend($this, $t))->toContain('vincular_apresentador');

    Participante::where('cpf', '111.444.777-35')->update(['user_id' => mkUser('user', 'ana@x.com')->id]);
    expect(pend($this, $t))->not->toContain('vincular_apresentador');
});

it('sem nenhum autor marcado como apresentador também aguarda vincular', function () {
    $t = aprovadoComApresentador($this, [['nome_completo' => 'Só autor', 'cpf' => '111.444.777-35', 'email' => 'so@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => false]]);
    expect(pend($this, $t))->toContain('vincular_apresentador');
});

it('sessão definida remove a pendência de sessão', function () {
    $t = aprovadoComApresentador($this);
    $s = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Feira', 'tipo' => 'outro', 'data_inicio' => now(), 'data_fim' => now()->addHour(), 'carga_horaria' => 1, 'vagas' => 5, 'token_frequencia' => uniqid()]);
    expect(pend($this, $t))->toContain('vincular_sessao');
    $t->update(['atividade_id' => $s->id]);
    expect(pend($this, $t))->not->toContain('vincular_sessao');
});

it('pendências por status: triagem, avaliado, reenviado, devolvido, apresentação', function () {
    expect(pend($this, mkTrabalho($this->evento, $this->autor, ['titulo' => 'T'], 'em_triagem')))->toBe(['atribuir_avaliadores']);
    expect(pend($this, mkTrabalho($this->evento, $this->autor, ['titulo' => 'A'], 'avaliado')))->toBe(['aguardando_decisao']);
    expect(pend($this, mkTrabalho($this->evento, $this->autor, ['titulo' => 'R'], 'resubmetido')))->toBe(['aguardando_reenvio_aprovacao']);
    expect(pend($this, mkTrabalho($this->evento, $this->autor, ['titulo' => 'D'], 'devolvido_triagem')))->toBe(['aguardando_autor']);
    expect(pend($this, mkTrabalho($this->evento, $this->autor, ['titulo' => 'Sub'], 'submetido')))->toBe([]);

    CriterioApresentacao::create(['evento_id' => $this->evento->id, 'nome' => 'D', 'peso' => 1, 'nota_max' => 10, 'ordem' => 0]);
    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => true]);
    $t = aprovadoComApresentador($this, null, 'Com apresentação');
    expect(pend($this, $t))->toContain('atribuir_avaliadores_apresentacao');
});

it('concluído não tem pendências', function () {
    $t = aprovadoComApresentador($this);
    $t->update(['apresentado' => true]);
    app(\App\Services\CertificadoTrabalhoService::class)->emitir($t->fresh(), $this->gestor);
    expect(pend($this, $t))->toBe([]);
});

it('a fila mostra as tags de pendência e filtra por elas', function () {
    $semAp = aprovadoComApresentador($this, null, 'Sem apresentador ligado');
    $ana = mkUser('user', 'ana@x.com');
    Participante::create(['user_id' => $ana->id, 'nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_vinculo' => 'externo']);
    $ok = aprovadoComApresentador($this, [['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => true]], 'Com apresentador ligado');
    // o primeiro trabalho também usa a Ana (CPF igual): troca o apresentador dele por quem não tem conta
    $semAp->autores()->where('nome_completo', 'Ana')->update(['cpf' => '123.456.789-09', 'email' => 'nova@x.com']);

    $html = $this->actingAs($this->gestor)->get(route('gestao.trabalhos.index', $this->evento))->assertOk()->assertSee('Aguardando vincular apresentador')->assertSee('Aguardando definir sessão')->getContent();
    expect($html)->toContain('Qualquer pendência');

    $r = $this->get(route('gestao.trabalhos.index', [$this->evento, 'pendencia' => 'vincular_apresentador']))->assertOk()->getContent();
    expect($r)->toContain($semAp->codigo)->not->toContain($ok->codigo);
    $this->get(route('gestao.trabalhos.index', [$this->evento, 'pendencia' => 'inexistente']))->assertOk();   // valor inválido é ignorado
});

it('o painel mostra as pendências e permite vincular um autor como apresentador', function () {
    $t = aprovadoComApresentador($this, [
        ['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => false],
        ['nome_completo' => 'Caio', 'cpf' => '529.982.247-25', 'email' => 'caio@x.com', 'tipo_autor' => 'coautor', 'apresentador' => false],
    ]);

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.show', $t))->assertOk()->assertSee('Pendências')->assertSee('Aguardando vincular apresentador')->assertSee('Nenhum apresentador definido');

    $caio = $t->autores()->where('nome_completo', 'Caio')->first();
    $this->post(route('gestao.trabalhos.apresentador', $t), ['autor_id' => $caio->id])->assertSessionHas('success');
    expect($caio->fresh()->apresentador)->toBeTrue()->and($t->fresh()->historico->last()->justificativa)->toContain('Apresentador definido: Caio');

    // autor de outro trabalho é recusado; autor (perfil) não pode
    $outro = aprovadoComApresentador($this, null, 'Outro');
    $this->post(route('gestao.trabalhos.apresentador', $t), ['autor_id' => $outro->autores->first()->id])->assertSessionHas('error');
    $this->actingAs($this->autor)->post(route('gestao.trabalhos.apresentador', $t), ['autor_id' => $caio->id])->assertForbidden();
});
