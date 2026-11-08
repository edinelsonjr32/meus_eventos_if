<?php

use App\Models\CertificadoPremiacao;
use App\Models\Premiacao;
use App\Services\PremiacaoService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->evento->trabalhoConfig->update(['avaliacao_apresentacao_ativa' => true, 'peso_apresentacao' => 50, 'tipos_permitidos' => ['pesquisa', 'extensao']]);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->autor = mkUser('user', 'autor@x.com');
    $this->svc = app(PremiacaoService::class);
});

/** Trabalho concluído e elegível: aprovado no texto e na apresentação, com notas (geral = média de texto e apresentação, peso 50%). */
function premiavel($ctx, string $tipo, string $titulo, float $texto, float $apres, array $extra = [])
{
    $t = mkTrabalho($ctx->evento, $ctx->autor, array_merge(['titulo' => $titulo, 'tipo_trabalho' => $tipo, 'autores' => [
        ['nome_completo' => "Autor {$titulo}", 'cpf' => '111.444.777-35', 'email' => 'a@x.com', 'tipo_autor' => 'autor_principal', 'apresentador' => true],
        ['nome_completo' => "Co {$titulo}", 'cpf' => '529.982.247-25', 'email' => 'c@x.com', 'tipo_autor' => 'coautor'],
    ]], $extra), 'aprovado');
    $t->update(['nota_final' => $texto, 'nota_apresentacao' => $apres, 'resultado_apresentacao' => 'aprovado', 'apresentado' => true]);

    return $t->fresh();
}

it('as categorias premiadas são os tipos de trabalho aceitos no evento', function () {
    expect($this->svc->categorias($this->evento))->toBe(['pesquisa' => 'Pesquisa', 'extensao' => 'Extensão']);

    $this->evento->trabalhoConfig->update(['tipos_permitidos' => ['relato_experiencia']]);
    expect(array_keys($this->svc->categorias($this->evento->fresh())))->toBe(['relato_experiencia']);
});

it('ranking da categoria: só aprovados nas duas partes com nota, ordenados pela nota geral, com desempate e marca de empate', function () {
    $a = premiavel($this, 'pesquisa', 'A', 8, 10);   // geral 9
    $b = premiavel($this, 'pesquisa', 'B', 9, 9);    // geral 9 (empate com A); texto maior => B antes
    $c = premiavel($this, 'pesquisa', 'C', 6, 6);    // geral 6
    premiavel($this, 'extensao', 'Outra categoria', 10, 10);
    mkTrabalho($this->evento, $this->autor, ['titulo' => 'Em avaliação', 'tipo_trabalho' => 'pesquisa'], 'em_avaliacao');
    $semAp = premiavel($this, 'pesquisa', 'Sem nota de apresentação', 9, 9);
    $semAp->update(['nota_apresentacao' => null, 'resultado_apresentacao' => null]);          // aprovado só no texto: fora
    $reprovAp = premiavel($this, 'pesquisa', 'Reprovado na apresentação', 9, 2);
    $reprovAp->update(['resultado_apresentacao' => 'reprovado']);                              // fora

    $r = $this->svc->ranking($this->evento, 'pesquisa');
    expect($r->pluck('titulo')->all())->toBe(['B', 'A', 'C'])
        ->and($r->firstWhere('titulo', 'B')->nota_geral_calculada)->toBe(9.0)
        ->and($r->firstWhere('titulo', 'A')->empate)->toBeTrue()->and($r->firstWhere('titulo', 'B')->empate)->toBeTrue()->and($r->firstWhere('titulo', 'C')->empate)->toBeFalse();
});

it('sugestão: os três primeiros de cada categoria', function () {
    foreach ([['P1', 9], ['P2', 8], ['P3', 7], ['P4', 6]] as [$n, $v]) { premiavel($this, 'pesquisa', $n, $v, $v); }
    premiavel($this, 'extensao', 'E1', 5, 5);

    $s = $this->svc->sugestao($this->evento);
    expect(array_keys($s))->toBe(['pesquisa', 'extensao'])->and(count($s['pesquisa']))->toBe(3)->and(count($s['extensao']))->toBe(1);
    expect(\App\Models\Trabalho::find($s['pesquisa'][1])->titulo)->toBe('P1')->and(\App\Models\Trabalho::find($s['pesquisa'][3])->titulo)->toBe('P3');
});

it('gestão apura pelo ranking, ajusta manualmente e os certificados já ficam prontos para impressão', function () {
    $p1 = premiavel($this, 'pesquisa', 'P1', 9, 9); $p2 = premiavel($this, 'pesquisa', 'P2', 8, 8); $p3 = premiavel($this, 'pesquisa', 'P3', 7, 7);

    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.premiacao.sugerir', $this->evento))->assertSessionHas('success');
    $prem = Premiacao::where('evento_id', $this->evento->id)->orderBy('colocacao')->get();
    expect($prem->pluck('trabalho_id')->all())->toBe([$p1->id, $p2->id, $p3->id])->and($prem->first()->nota_geral)->toBe(9.0)
        ->and(CertificadoPremiacao::count())->toBe(6);   // 2 autores × 3 trabalhos

    // ajuste manual: troca 1º e 2º
    $hashAntigo = $prem->firstWhere('colocacao', 3)->certificados->first()->hash_validacao;
    $this->put(route('gestao.trabalhos.premiacao.salvar', $this->evento), ['escolhas' => ['pesquisa' => [1 => $p2->id, 2 => $p1->id, 3 => $p3->id]]])->assertSessionHas('success');
    $depois = Premiacao::where('evento_id', $this->evento->id)->orderBy('colocacao')->get();
    expect($depois->pluck('trabalho_id')->all())->toBe([$p2->id, $p1->id, $p3->id])
        ->and($depois->firstWhere('colocacao', 3)->certificados->first()->hash_validacao)->toBe($hashAntigo)   // quem não mudou mantém o certificado
        ->and(CertificadoPremiacao::count())->toBe(6);

    // retirar um lugar apaga o certificado dele
    $this->put(route('gestao.trabalhos.premiacao.salvar', $this->evento), ['escolhas' => ['pesquisa' => [1 => $p2->id]]])->assertSessionHas('success');
    expect(Premiacao::count())->toBe(1)->and(CertificadoPremiacao::count())->toBe(2);
});

it('valida as escolhas: categoria errada, trabalho repetido ou inelegível', function () {
    $pesq = premiavel($this, 'pesquisa', 'Pesq', 9, 9);
    $ext = premiavel($this, 'extensao', 'Ext', 9, 9);
    $fora = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Em triagem', 'tipo_trabalho' => 'pesquisa'], 'em_triagem');
    $this->actingAs($this->gestor);

    $this->put(route('gestao.trabalhos.premiacao.salvar', $this->evento), ['escolhas' => ['pesquisa' => [1 => $ext->id]]])->assertSessionHas('error');         // outra categoria
    $this->put(route('gestao.trabalhos.premiacao.salvar', $this->evento), ['escolhas' => ['pesquisa' => [1 => $pesq->id, 2 => $pesq->id]]])->assertSessionHas('error'); // repetido
    $this->put(route('gestao.trabalhos.premiacao.salvar', $this->evento), ['escolhas' => ['pesquisa' => [1 => $fora->id]]])->assertSessionHas('error');             // inelegível
    expect(Premiacao::count())->toBe(0);
});

it('o certificado de premiação não aparece para o autor até a divulgação; a gestão imprime sempre', function () {
    $t = premiavel($this, 'pesquisa', 'Campeão', 9, 9);
    $this->svc->aplicarSugestao($this->evento, $this->gestor);
    $cert = CertificadoPremiacao::first();

    // antes de divulgar: o autor não vê nada do prêmio, nem baixa o certificado
    $this->actingAs($this->autor)->get(route('trabalhos.show', $t))->assertOk()->assertDontSee('Premiação')->assertDontSee(route('certificado.premiacao.download', $cert->hash_validacao), false);
    $this->get(route('trabalhos.index'))->assertDontSee('Premiado');
    $this->get(route('certificado.premiacao.download', $cert->hash_validacao))->assertForbidden();

    // a gestão imprime (um, vários, por categoria)
    $this->actingAs($this->gestor);
    foreach ([route('gestao.trabalhos.premiacao.certificado', $cert), route('gestao.trabalhos.premiacao.imprimir', $this->evento), route('gestao.trabalhos.premiacao.imprimir', [$this->evento, 'tipo' => 'pesquisa']), route('certificado.premiacao.download', $cert->hash_validacao)] as $url) {
        $r = $this->get($url)->assertOk();
        expect($r->headers->get('content-type'))->toContain('application/pdf');
    }

    // divulga: agora o autor vê o selo e o botão do certificado, e baixa
    $this->post(route('gestao.trabalhos.premiacao.divulgar', $this->evento))->assertSessionHas('success');
    $this->actingAs($this->autor)->get(route('trabalhos.show', $t))->assertSee('Premiação')->assertSee('Premiado · 1º lugar')->assertSee(route('certificado.premiacao.download', $cert->hash_validacao), false);
    $this->get(route('trabalhos.index'))->assertSee('Premiado · 1º lugar');
    expect($this->get(route('certificado.premiacao.download', $cert->hash_validacao))->assertOk()->headers->get('content-type'))->toContain('application/pdf');
    expect($cert->fresh()->downloads)->toBeGreaterThan(0);

    // ocultar de novo esconde do autor
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.premiacao.ocultar', $this->evento));
    $this->actingAs($this->autor)->get(route('trabalhos.show', $t))->assertDontSee('Premiado · 1º lugar');
    $this->get(route('certificado.premiacao.download', $cert->hash_validacao))->assertForbidden();
});

it('só divulga depois de apurar; coautor vê só o próprio certificado, o responsável vê todos', function () {
    $this->actingAs($this->gestor)->post(route('gestao.trabalhos.premiacao.divulgar', $this->evento))->assertSessionHas('error');

    $t = premiavel($this, 'pesquisa', 'Dupla', 9, 9);
    $this->svc->aplicarSugestao($this->evento, $this->gestor);
    $this->svc->divulgar($this->evento);

    $co = mkUser('user', 'c@x.com');   // e-mail do coautor
    $html = $this->actingAs($co)->get(route('trabalhos.show', $t))->assertOk()->getContent();
    expect(substr_count($html, 'certificado-premiacao/download'))->toBe(1);
    $html = $this->actingAs($this->autor)->get(route('trabalhos.show', $t))->getContent();
    expect(substr_count($html, 'certificado-premiacao/download'))->toBe(2);
});

it('validação pública do certificado de premiação (autêntico, revogado, inexistente)', function () {
    premiavel($this, 'pesquisa', 'Validar', 9, 9);
    $this->svc->aplicarSugestao($this->evento, $this->gestor);
    $cert = CertificadoPremiacao::first();

    $this->get(route('certificado.premiacao.validar', $cert->hash_validacao))->assertOk()->assertSee('Certificado de premiação autêntico')->assertSee('1º lugar')->assertSee('Pesquisa')->assertDontSee('111.444.777-35');
    $cert->update(['revogado_em' => now()]);
    $this->get(route('certificado.premiacao.validar', $cert->hash_validacao))->assertSee('Certificado revogado');
    $this->get(route('certificado.premiacao.validar', 'NAOEXISTE'))->assertSee('Documento não encontrado');
});

it('o texto do certificado é escapado e usa as variáveis; o modelo é configurável', function () {
    $t = premiavel($this, 'pesquisa', 'Título <script>x</script>', 9.5, 9.5);
    $this->svc->aplicarSugestao($this->evento, $this->gestor);
    $cert = CertificadoPremiacao::first();

    $txt = $this->svc->textos($cert)['corpo'];
    expect($txt)->toContain('1º lugar')->toContain('pesquisa')->toContain('9,50')->not->toContain('<script>')->toContain('&lt;script&gt;');

    $this->actingAs($this->gestor)->put(route('gestao.trabalhos.premiacao.modelo', $this->evento), ['cert_premio_corpo' => 'Parabéns {AUTOR} pelo {COLOCACAO} em {CATEGORIA}!', 'cert_premio_rodape' => 'Rodapé X'])->assertSessionHas('success');
    $cert = $cert->fresh();
    expect($this->svc->textos($cert)['corpo'])->toContain('Parabéns')->toContain('1º lugar')->and($this->svc->textos($cert)['rodape'])->toBe('Rodapé X');
});

it('só a gestão acessa a premiação e a impressão', function () {
    premiavel($this, 'pesquisa', 'X', 9, 9);
    $this->svc->aplicarSugestao($this->evento, $this->gestor);
    $cert = CertificadoPremiacao::first();

    foreach ([$this->autor, mkUser('user', 'av@x.com')] as $u) {
        $this->actingAs($u)->get(route('gestao.trabalhos.premiacao.index', $this->evento))->assertForbidden();
        $this->post(route('gestao.trabalhos.premiacao.sugerir', $this->evento))->assertForbidden();
        $this->post(route('gestao.trabalhos.premiacao.divulgar', $this->evento))->assertForbidden();
        $this->get(route('gestao.trabalhos.premiacao.imprimir', $this->evento))->assertForbidden();
        $this->get(route('gestao.trabalhos.premiacao.certificado', $cert))->assertForbidden();
    }
});

it('a tela de premiação mostra as categorias do evento, os concorrentes e as ações', function () {
    premiavel($this, 'pesquisa', 'Concorrente A', 9, 9);
    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.premiacao.index', $this->evento))->assertOk()
        ->assertSee('Premiação')->assertSee('Pesquisa')->assertSee('Extensão')->assertDontSee('Projeto Integrador')
        ->assertSee('Apurar pelo ranking')->assertSee('Divulgar aos autores')->assertSee('Visível só para a gestão')->assertSee('Concorrente A')->assertSee('Modelo do certificado de premiação');
});

it('no ranking só concorre à colocação (medalha) quem foi aprovado nas duas partes; os demais ficam provisórios', function () {
    $ok = premiavel($this, 'pesquisa', 'Elegível', 8, 8);
    $andamento = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Em andamento', 'tipo_trabalho' => 'pesquisa'], 'em_avaliacao');
    $andamento->update(['nota_final' => 10]);   // nota alta, mas ainda sem aprovação
    $a = $andamento->avaliacoes()->create(['avaliador_id' => $this->gestor->id, 'rodada' => 1, 'status' => 'concluida', 'nota_final' => 10, 'recomendacao' => 'aprovar', 'concluida_em' => now()]);

    $linhas = app(\App\Services\RelatorioNotas::class)->resumo($this->evento)->keyBy(fn ($l) => $l['trabalho']->titulo);
    expect($linhas['Elegível']['elegivel_premio'])->toBeTrue()->and($linhas['Elegível']['posicao_categoria'])->toBe(1)
        ->and($linhas['Em andamento']['elegivel_premio'])->toBeFalse()->and($linhas['Em andamento']['posicao_categoria'])->toBeNull();

    $this->actingAs($this->gestor)->get(route('gestao.trabalhos.ranking', $this->evento))->assertOk()->assertSee('provisório');
});

it('apurar de novo várias vezes não repete trabalhos nem certificados; a impressão traz uma página por autor premiado', function () {
    $p1 = premiavel($this, 'pesquisa', 'P1', 9, 9); $p2 = premiavel($this, 'pesquisa', 'P2', 8, 8);
    premiavel($this, 'extensao', 'E1', 7, 7);

    foreach (range(1, 3) as $_) {
        $this->actingAs($this->gestor)->post(route('gestao.trabalhos.premiacao.sugerir', $this->evento))->assertSessionHas('success');
        $this->put(route('gestao.trabalhos.premiacao.salvar', $this->evento), ['escolhas' => ['pesquisa' => [1 => $p1->id, 2 => $p2->id]]]);
    }
    // novo melhor trabalho entra: a apuração reorganiza sem acumular os antigos
    $p0 = premiavel($this, 'pesquisa', 'P0', 10, 10);
    $this->post(route('gestao.trabalhos.premiacao.sugerir', $this->evento));

    $prem = Premiacao::where('evento_id', $this->evento->id)->get();
    expect($prem->pluck('trabalho_id')->duplicates()->all())->toBe([])
        ->and($prem->where('tipo_trabalho', 'pesquisa')->pluck('colocacao')->sort()->values()->all())->toBe([1, 2, 3])
        ->and($prem->where('tipo_trabalho', 'pesquisa')->firstWhere('colocacao', 1)->trabalho_id)->toBe($p0->id)
        ->and(CertificadoPremiacao::count())->toBe(2 * $prem->count());

    $pdf = $this->get(route('gestao.trabalhos.premiacao.imprimir', $this->evento))->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('pdf');
});
