<?php

use App\Mail\AvaliadorAtribuidoMail;
use App\Mail\CertificadoTrabalhoMail;
use App\Mail\TrabalhoDecisaoMail;
use App\Mail\TrabalhoReenviadoMail;
use App\Mail\TrabalhoSubmetidoMail;
use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\TrabalhoAvaliacao;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Jornada de ponta a ponta, SOMENTE por rotas HTTP reais e na ordem de uma chamada de verdade:
 * dono cria e publica a chamada → designa gestor e avaliadores → autor submete → triagem → avaliação
 * → decisão com ressalvas → correção → nova rodada → aprovação → apresentação pela frequência →
 * certificados → Anais públicos → certificado pelo autor. Nada é criado por atalho no banco.
 */
function jornadaPdf(string $nome): UploadedFile
{
    // PDF de verdade (1 página): o compilador dos Anais descarta PDFs ilegíveis
    return UploadedFile::fake()->createWithContent($nome, \Barryvdh\DomPDF\Facade\Pdf::loadHTML("<h1>{$nome}</h1><p>Conteúdo do trabalho.</p>")->output());
}

it('percorre toda a jornada de um trabalho, da chamada ao certificado e aos Anais', function () {
    Mail::fake();
    Storage::fake('trabalhos');

    // ---------- Pessoas ----------
    $dono = mkUser('admin');
    $gestor = mkUser('user', 'gestor@jornada.com');
    $av1 = mkUser('user', 'av1@jornada.com');
    $av2 = mkUser('user', 'av2@jornada.com');
    $autor = mkUser('user', 'ana@ifpa.edu.br');

    $evento = Evento::create([
        'titulo' => 'X Semana de Ciência e Tecnologia', 'slug' => 'semana-jornada', 'data_inicio' => now()->addDays(10), 'data_fim' => now()->addDays(12),
        'local' => 'IFPA Campus Óbidos', 'criado_por' => $dono->id, 'latitude' => '-2.457763', 'longitude' => '-54.743856', 'raio_permitido' => 500,
    ]);
    $sessao = Atividade::create([
        'evento_id' => $evento->id, 'titulo' => 'Sessão de Pôsteres', 'tipo' => 'outro', 'data_inicio' => now()->addDays(10), 'data_fim' => now()->addDays(10)->addHours(3),
        'carga_horaria' => 3, 'vagas' => 100, 'token_frequencia' => 'SESSAO-JORNADA',
    ]);

    // ---------- 1. O dono cria e publica a chamada (critérios e áreas incluídos) ----------
    $this->actingAs($dono)->put(route('gestao.trabalhos.config.update', $evento), [
        'acao' => 'publicar', 'submissao_inicio' => now()->subDay()->format('Y-m-d\TH:i'), 'submissao_fim' => now()->addDays(5)->format('Y-m-d\TH:i'),
        'max_autores' => 10, 'max_arquivo_mb' => 20, 'resumo_min_palavras' => 50, 'resumo_max_palavras' => 250,
        'tipos_permitidos' => ['pesquisa', 'extensao', 'projeto_integrador', 'relato_experiencia'],
        'avaliadores_por_trabalho' => 2, 'nota_minima_aprovacao' => 7, 'divergencia_maxima' => 3,
        'exige_resumo' => 0, 'exige_palavras_chave' => 0, 'exige_cpf_apresentador' => 1,
        'chamada_tema' => 'Planeta Amazônia', 'areas' => "Educação\nSustentabilidade",
        'criterios' => [['nome' => 'Conteúdo', 'peso' => 1, 'nota_max' => 10], ['nome' => 'Metodologia', 'peso' => 1, 'nota_max' => 10]],
    ])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'Chamada criada e publicada'));

    // ---------- 2. Designa gestor e avaliadores ----------
    foreach ([['gestor@jornada.com', 'gestor_trabalhos'], ['av1@jornada.com', 'avaliador'], ['av2@jornada.com', 'avaliador']] as [$email, $papel]) {
        $this->actingAs($dono)->post(route('admin.eventos.papeis.store', $evento), ['email' => $email, 'papel' => $papel])->assertSessionHas('success');
    }

    // ---------- 3. O autor vê a chamada e submete ----------
    $this->get(route('evento.publico.show', $evento->slug))->assertOk()->assertSee('Submeter Trabalho');
    $this->actingAs($autor)->get(route('trabalhos.index'))->assertOk()->assertSee('Chamada aberta');
    $this->get(route('trabalhos.create', $evento->slug))->assertOk()->assertSee('Planeta Amazônia');

    $area = $evento->areasTematicas()->first();
    $this->post(route('trabalhos.store', $evento->slug), [
        'acao' => 'submeter', 'titulo' => 'Monitoramento de igarapés com sensores de baixo custo', 'tipo_trabalho' => 'pesquisa',
        'area_tematica_id' => $area->id, 'modalidade_apresentacao' => 'poster', 'declaracao_ineditismo' => 1, 'declaracao_regulamento' => 1, 'autoriza_publicacao' => 1,
        'autores' => [
            ['nome_completo' => 'Ana Apresentadora', 'cpf' => '111.444.777-35', 'email' => 'ana@ifpa.edu.br', 'instituicao' => 'IFPA Campus Óbidos', 'categoria_autor' => 'discente', 'tipo_autor' => 'autor_principal', 'apresentador' => 1, 'correspondente' => 1],
            ['nome_completo' => 'Prof. Otávio Orientador', 'instituicao' => 'IFPA Campus Óbidos', 'tipo_autor' => 'orientador'],
        ],
        'arquivo' => jornadaPdf('trabalho.pdf'),
    ])->assertSessionHasNoErrors();

    $trabalho = Trabalho::firstOrFail();
    expect($trabalho->status)->toBe('submetido')->and($trabalho->codigo)->toBe("TRB-{$evento->id}-0001")->and($trabalho->autores)->toHaveCount(2);
    Mail::assertQueued(TrabalhoSubmetidoMail::class, fn ($m) => $m->hasTo('ana@ifpa.edu.br'));

    // ---------- 4. Triagem e atribuição de avaliadores pelo gestor ----------
    $this->actingAs($gestor)->get(route('gestao.trabalhos.index', $evento))->assertOk()->assertSee($trabalho->codigo);
    $this->post(route('gestao.trabalhos.triagem', $trabalho), ['para' => 'em_triagem'])->assertSessionHas('success');
    $this->post(route('gestao.trabalhos.avaliadores.store', $trabalho), ['avaliadores' => [$av1->id, $av2->id]])->assertSessionHas('success');
    Mail::assertQueued(AvaliadorAtribuidoMail::class, 2);
    $this->post(route('gestao.trabalhos.triagem', $trabalho), ['para' => 'em_avaliacao'])->assertSessionHas('success');
    expect($trabalho->fresh()->status)->toBe('em_avaliacao');

    // ---------- 5. Os avaliadores avaliam (rodada 1) ----------
    $criterios = $evento->criterios()->get();
    $avaliar = function ($usuario, array $notas, int $rodada) use ($trabalho, $criterios) {
        $avaliacao = TrabalhoAvaliacao::where('trabalho_id', $trabalho->id)->where('avaliador_id', $usuario->id)->where('rodada', $rodada)->firstOrFail();
        test()->actingAs($usuario)->get(route('avaliador.avaliacoes.show', $avaliacao))->assertOk();
        test()->post(route('avaliador.avaliacoes.concluir', $avaliacao), [
            'notas' => $criterios->pluck('id')->combine($notas)->all(),
            'parecer' => "Parecer detalhado do avaliador {$usuario->id} na rodada {$rodada}.", 'recomendacao' => 'aprovar_com_ressalvas',
        ])->assertSessionHasNoErrors()->assertRedirect(route('avaliador.avaliacoes.index'));
    };
    $avaliar($av1, [8, 8], 1);
    expect($trabalho->fresh()->status)->toBe('em_avaliacao');   // falta o segundo avaliador
    $avaliar($av2, [7, 7], 1);
    expect($trabalho->fresh())->status->toBe('avaliado')->nota_final->toBe(7.5);

    // ---------- 6. Decisão com ressalvas ----------
    $this->actingAs($gestor)->post(route('gestao.trabalhos.decisao', $trabalho), [
        'decisao' => 'aprovado_com_ressalvas', 'justificativa' => 'Detalhar a metodologia e atualizar as referências.', 'prazo_correcao' => now()->addDays(4)->format('Y-m-d'),
    ])->assertSessionHas('success');
    expect($trabalho->fresh()->status)->toBe('aguardando_correcao');
    Mail::assertQueued(TrabalhoDecisaoMail::class, fn ($m) => $m->decisao === 'aprovado_com_ressalvas' && $m->hasTo('ana@ifpa.edu.br'));

    // O autor vê o resultado e o parecer anônimo, mas nunca avaliadores nem o painel da gestão
    $this->actingAs($autor)->get(route('trabalhos.show', $trabalho))->assertOk()
        ->assertSee('Resultado da avaliação')->assertSee('Detalhar a metodologia')->assertDontSee($av1->email)->assertDontSee($av2->email);
    $this->get(route('gestao.trabalhos.show', $trabalho))->assertForbidden();

    // ---------- 7. Correção e reenvio pelo autor ----------
    $this->get(route('trabalhos.edit', $trabalho))->assertOk();
    $this->put(route('trabalhos.update', $trabalho), [
        'acao' => 'submeter', 'titulo' => 'Monitoramento de igarapés com sensores de baixo custo (versão revisada)', 'tipo_trabalho' => 'pesquisa',
        'area_tematica_id' => $area->id, 'modalidade_apresentacao' => 'poster', 'declaracao_ineditismo' => 1, 'declaracao_regulamento' => 1, 'autoriza_publicacao' => 1,
        'autores' => [
            ['id' => $trabalho->autores[0]->id, 'nome_completo' => 'Ana Apresentadora', 'cpf' => '111.444.777-35', 'email' => 'ana@ifpa.edu.br', 'instituicao' => 'IFPA Campus Óbidos', 'categoria_autor' => 'discente', 'tipo_autor' => 'autor_principal', 'apresentador' => 1, 'correspondente' => 1],
            ['id' => $trabalho->autores[1]->id, 'nome_completo' => 'Prof. Otávio Orientador', 'instituicao' => 'IFPA Campus Óbidos', 'tipo_autor' => 'orientador'],
        ],
        'arquivo' => jornadaPdf('trabalho-revisado.pdf'),
    ])->assertSessionHasNoErrors();
    expect($trabalho->fresh()->status)->toBe('resubmetido')->and($trabalho->fresh()->arquivos)->toHaveCount(2);
    Mail::assertQueued(TrabalhoReenviadoMail::class, fn ($m) => $m->hasTo('gestor@jornada.com'));

    // ---------- 8. Nova rodada com os mesmos avaliadores e aprovação ----------
    $this->actingAs($gestor)->post(route('gestao.trabalhos.reavaliar', $trabalho))->assertSessionHas('success');
    expect($trabalho->fresh()->status)->toBe('em_avaliacao')->and($trabalho->fresh()->rodadaAtual())->toBe(2);
    $avaliar($av1, [9, 9], 2);
    $avaliar($av2, [9, 9], 2);
    expect($trabalho->fresh())->status->toBe('avaliado')->nota_final->toBe(9.0);
    $this->actingAs($gestor)->post(route('gestao.trabalhos.decisao', $trabalho), ['decisao' => 'aprovado', 'justificativa' => 'Parabéns!'])->assertSessionHas('success');
    expect($trabalho->fresh()->status)->toBe('aprovado');

    // ---------- 9. Apresentação: a presença do apresentador na sessão confirma sozinha ----------
    $this->post(route('gestao.trabalhos.apresentacao', $trabalho), ['acao' => 'sessao', 'atividade_id' => $sessao->id])->assertSessionHas('success');
    expect($trabalho->fresh()->apresentado)->toBeFalse();
    $this->post(route('gestao.trabalhos.certificados.emitir', $trabalho))->assertSessionHas('error');   // ainda não apresentou

    auth()->logout();
    $this->get(route('frequencia.form', 'SESSAO-JORNADA'))->assertOk();
    $this->post(route('frequencia.store', 'SESSAO-JORNADA'), [
        'nome_completo' => 'Ana Apresentadora', 'cpf' => '111.444.777-35', 'email' => 'ana@ifpa.edu.br', 'tipo_vinculo' => 'externo',
        'user_lat' => '-2.457763', 'user_lng' => '-54.743856',
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonStructure(['redirect']);
    expect($trabalho->fresh())->apresentado->toBeTrue()->apresentacao_origem->toBe('frequencia');

    // ---------- 10. Certificados ----------
    $this->actingAs($gestor)->post(route('gestao.trabalhos.certificados.emitir', $trabalho))->assertSessionHas('success');
    $certificados = $trabalho->fresh()->certificados;
    expect($certificados)->toHaveCount(2);
    Mail::assertQueued(CertificadoTrabalhoMail::class, 1);   // só a apresentadora tem e-mail; o orientador não informou

    $this->actingAs($autor)->get(route('trabalhos.show', $trabalho))->assertOk()->assertSee('Certificados');
    $hash = $certificados->firstWhere('apresentador', true)->hash_validacao;
    auth()->logout();
    $this->get(route('certificado.trabalho.validar', $hash))->assertOk()->assertSee('Certificado autêntico')->assertSee('Ana Apresentadora');
    expect($this->get(route('certificado.trabalho.download', $hash))->assertOk()->getContent())->toStartWith('%PDF-');

    // ---------- 11. Ranking da comissão ----------
    $this->actingAs($gestor)->get(route('gestao.trabalhos.ranking', $evento))->assertOk()->assertSee($trabalho->codigo)->assertSee('9,00');
    $this->get(route('gestao.trabalhos.exportar-notas', ['evento' => $evento, 'relatorio' => 'detalhado', 'formato' => 'csv']))->assertOk();

    // ---------- 12. Anais ----------
    $this->put(route('gestao.trabalhos.anais.update', $evento), ['titulo' => 'Anais da X Semana', 'ano' => 2026, 'local_publicacao' => 'Óbidos', 'editora' => 'IFPA Campus Óbidos', 'issn' => '1234-5678'])
        ->assertSessionHasNoErrors();
    $this->get(route('anais.index', $evento->slug))->assertNotFound();   // ainda não publicado
    $this->post(route('gestao.trabalhos.anais.publicar', $evento))->assertSessionHas('success');
    $this->post(route('gestao.trabalhos.anais.dados-publicacao', $evento), ['acao' => 'exibir'])->assertSessionHas('success');

    auth()->logout();
    $this->get(route('anais.index', $evento->slug))->assertOk()->assertSee('Monitoramento de igarapés')->assertSee('1 trabalho(s) publicado(s)');
    $this->get(route('anais.show', [$evento->slug, $trabalho->codigo]))->assertOk()->assertSee('Como citar (ABNT)')->assertSee('APRESENTADORA, A.');
    $this->get(route('anais.trabalho.pdf', [$evento->slug, $trabalho->codigo]))->assertOk();

    // O autor obtém o certificado pelos Anais (CPF + e-mail da submissão)
    $this->post(route('anais.certificado', [$evento->slug, $trabalho->codigo]), ['cpf' => '111.444.777-35', 'email' => 'ana@ifpa.edu.br'])
        ->assertRedirect(route('certificado.trabalho.download', $hash));

    // PDF compilado (somente se o Ghostscript existir no ambiente)
    if (Process::run([config('trabalhos.ghostscript'), '--version'])->successful()) {
        $this->actingAs($gestor)->post(route('gestao.trabalhos.anais.gerar', $evento))->assertSessionHas('success');   // fila "sync" nos testes
        expect($evento->fresh()->anais->pdf_status)->toBe('pronto');
        auth()->logout();
        $this->get(route('anais.pdf', $evento->slug))->assertOk();
    }

    // ---------- Histórico consolidado ----------
    $sequencia = $trabalho->fresh()->historico->filter(fn ($h) => $h->de !== $h->para)->pluck('para')->all();
    expect($sequencia)->toBe(['rascunho', 'submetido', 'em_triagem', 'em_avaliacao', 'avaliado', 'aguardando_correcao', 'resubmetido', 'em_avaliacao', 'avaliado', 'aprovado']);
});
