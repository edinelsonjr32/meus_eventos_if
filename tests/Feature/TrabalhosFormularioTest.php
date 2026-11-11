<?php

use App\Exports\TrabalhosExport;
use App\Mail\TrabalhoSubmetidoMail;
use App\Models\Atividade;
use App\Models\Frequencia;
use App\Models\Participante;
use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use App\Services\ApresentacaoService;
use App\Services\CertificadoTrabalhoService;
use App\Services\TrabalhoWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/** Formulário no estilo do Google Forms usado na Semana de Ciência e Tecnologia. */
function frmPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('trabalho.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

/** Apresentador completo (nome, e-mail, IES, categoria e CPF). */
function frmApresentador(array $extra = []): array
{
    return array_merge([
        'nome_completo' => 'Ana Apresentadora', 'cpf' => '111.444.777-35', 'email' => 'ana@ifpa.edu.br', 'instituicao' => 'IFPA Campus Óbidos',
        'categoria_autor' => 'discente', 'tipo_autor' => 'autor_principal', 'apresentador' => 1, 'correspondente' => 1,
    ], $extra);
}

/** Envio mínimo, como no formulário original: sem resumo nem palavras-chave. */
function frmEnvio(array $extra = []): array
{
    return array_merge([
        'acao' => 'submeter', 'titulo' => 'Águas da Amazônia', 'tipo_trabalho' => 'pesquisa', 'modalidade_apresentacao' => 'poster',
        'declaracao_ineditismo' => 1, 'declaracao_regulamento' => 1, 'autoriza_publicacao' => 1,
        'autores' => [frmApresentador()], 'arquivo' => frmPdf(),
    ], $extra);
}

beforeEach(function () {
    Mail::fake();
    Storage::fake('trabalhos');

    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->evento->update(['titulo' => 'X Semana de Ciência e Tecnologia']);
    // Chamada configurada como o formulário original: sem resumo/palavras-chave, até 10 autores
    $this->config = TrabalhoConfig::where('evento_id', $this->evento->id)->first();
    $this->config->update([
        'exige_resumo' => false, 'exige_palavras_chave' => false, 'exige_cpf_apresentador' => true, 'max_autores' => 10,
        'chamada_tema' => 'Planeta Amazônia: cultura das águas para enfrentar as mudanças climáticas',
        'template_url' => 'https://docs.google.com/document/d/abc/template',
        'tipos_permitidos' => ['pesquisa', 'extensao', 'projeto_integrador', 'relato_experiencia'],
    ]);
    $this->usuario = mkUser();
    $this->url = route('trabalhos.store', $this->evento->slug);
});

// ---------- Estrutura e textos do formulário ----------
it('reproduz as seções, perguntas e opções do formulário original', function () {
    $this->actingAs($this->usuario)->get(route('trabalhos.create', $this->evento->slug))->assertOk()
        ->assertSee('Formulário de Submissão de Trabalhos')
        ->assertSee('Este formulário destina-se à submissão de trabalhos para o evento')
        ->assertSee('X Semana de Ciência e Tecnologia')
        ->assertSee('Planeta Amazônia: cultura das águas para enfrentar as mudanças climáticas')
        ->assertSee('Indica uma pergunta obrigatória')
        // seção 1
        ->assertSee('Título do trabalho *')->assertSee('Categoria do Trabalho *')
        ->assertSee('Pesquisa')->assertSee('Extensão')->assertSee('Projeto Integrador (alunos do IFPA)')->assertSee('Relato de Experiência')
        // seção 2
        ->assertSee('Cadastro de autores')->assertSee('Insira o nome completo e a Instituição de Ensino ao qual o apresentador(a) esteja vinculado(a).')
        ->assertSee('Nome completo do autor apresentador *')->assertSee('Instituição de Ensino - IES de vínculo *')
        ->assertSee('Categoria *')->assertSee('Docente')->assertSee('Discente')->assertSee('Cadastrar mais autores? *')
        // seção 3
        ->assertSee('(João da Silva - Instituto Federal do Pará - Campus Óbidos)', false)
        // seção 4
        ->assertSee('Envio de arquivo')->assertSee('Modalidade de Apresentação *')->assertSee('Pôster')
        ->assertSee('Apresentação oral/remoto (para autores externos na impossibilidade de comparecer no evento)')
        ->assertSee('Envio de arquivo (Formato PDF) *');
});

it('mostra o e-mail da conta (somente leitura) e o apresentador pré-preenchido', function () {
    $r = $this->actingAs($this->usuario)->get(route('trabalhos.create', $this->evento->slug))->assertOk()->assertSee($this->usuario->email);

    $autores = $r->viewData('autores');
    expect($autores)->toHaveCount(1)->and($autores[0]['nome_completo'])->toBe($this->usuario->name)
        ->and($autores[0]['tipo_autor'])->toBe('autor_principal')->and($autores[0]['apresentador'])->toBeTrue();
});

it('mostra link do template e usa o texto de abertura personalizado quando há', function () {
    $this->actingAs($this->usuario);
    $this->get(route('trabalhos.create', $this->evento->slug))->assertSee('utilize o')->assertSee('https://docs.google.com/document/d/abc/template', false);

    $this->config->update(['chamada_intro' => 'Texto de abertura personalizado da chamada.']);
    $this->get(route('trabalhos.create', $this->evento->slug))->assertSee('Texto de abertura personalizado da chamada.')
        ->assertDontSee('Este formulário destina-se');

    $this->config->update(['template_url' => null]);
    $this->get(route('trabalhos.create', $this->evento->slug))->assertDontSee('docs.google.com/document', false);
});

it('oferece somente as categorias habilitadas na chamada', function () {
    $this->config->update(['tipos_permitidos' => ['pesquisa', 'relato_experiencia']]);

    $this->actingAs($this->usuario)->get(route('trabalhos.create', $this->evento->slug))->assertOk()
        ->assertSee('id="cat_pesquisa"', false)->assertSee('id="cat_relato_experiencia"', false)
        ->assertDontSee('id="cat_extensao"', false)->assertDontSee('id="cat_projeto_integrador"', false);

    $this->actingAs($this->usuario)->post($this->url, frmEnvio(['tipo_trabalho' => 'extensao']))->assertSessionHasErrors('tipo_trabalho');
});

// ---------- Envio no formato do formulário original ----------
it('aceita o envio mínimo do formulário original (sem resumo nem palavras-chave)', function () {
    $this->actingAs($this->usuario)->post($this->url, frmEnvio())->assertSessionHasNoErrors();

    $t = Trabalho::with('autores')->firstOrFail();
    expect($t->status)->toBe('submetido')->and($t->resumo)->toBeNull()->and($t->palavras_chave)->toBe([])
        ->and($t->tipo_trabalho)->toBe('pesquisa')->and($t->modalidade_apresentacao)->toBe('poster')
        ->and($t->autores)->toHaveCount(1)->and($t->autores->first()->categoria_autor)->toBe('discente')
        ->and($t->autores->first()->instituicao)->toBe('IFPA Campus Óbidos')->and($t->autores->first()->apresentador)->toBeTrue();
});

it('cadastra autores adicionais apenas com nome e instituição', function () {
    $this->actingAs($this->usuario)->post($this->url, frmEnvio(['autores' => [
        frmApresentador(),
        ['nome_completo' => 'João da Silva', 'instituicao' => 'Instituto Federal do Pará - Campus Óbidos', 'tipo_autor' => 'coautor'],
        ['nome_completo' => 'Maria Souza', 'instituicao' => 'UFOPA', 'tipo_autor' => 'orientador', 'categoria_autor' => 'docente'],
        ['nome_completo' => 'Pedro Lima', 'instituicao' => 'UEPA', 'tipo_autor' => 'coautor'],
    ]]))->assertSessionHasNoErrors();

    $t = Trabalho::with('autores')->firstOrFail();
    expect($t->autores)->toHaveCount(4)
        ->and($t->autores->pluck('nome_completo')->all())->toBe(['Ana Apresentadora', 'João da Silva', 'Maria Souza', 'Pedro Lima'])
        ->and($t->autores->skip(1)->pluck('cpf')->filter())->toBeEmpty()       // vários autores sem CPF não colidem na unicidade
        ->and($t->autores->skip(1)->pluck('email')->filter())->toBeEmpty()
        ->and($t->autores->get(2)->categoria_autor)->toBe('docente');
});

it('aceita até 10 autores e rejeita acima do limite (orientadores não contam)', function () {
    $coautores = fn (int $n) => collect(range(1, $n))->map(fn ($i) => ['nome_completo' => "Autor {$i}", 'instituicao' => 'IFPA', 'tipo_autor' => 'coautor'])->all();

    $this->actingAs($this->usuario)->post($this->url, frmEnvio(['autores' => array_merge([frmApresentador()], $coautores(9))]))->assertSessionHasNoErrors();
    expect(Trabalho::first()->autores)->toHaveCount(10);

    $this->actingAs($this->usuario)->post($this->url, frmEnvio(['titulo' => 'Muitos', 'autores' => array_merge([frmApresentador()], $coautores(10))]))->assertSessionHasErrors('autores');
});

it('somente o apresentador recebe e-mail quando os demais autores não têm e-mail', function () {
    $this->actingAs($this->usuario)->post($this->url, frmEnvio(['autores' => [
        frmApresentador(['email' => 'apresentadora@ifpa.edu.br']),
        ['nome_completo' => 'Sem Email', 'instituicao' => 'IFPA', 'tipo_autor' => 'coautor'],
    ]]))->assertSessionHasNoErrors();

    Mail::assertQueued(TrabalhoSubmetidoMail::class, 2);   // apresentador + responsável pela submissão
    Mail::assertQueued(TrabalhoSubmetidoMail::class, fn ($m) => $m->hasTo('apresentadora@ifpa.edu.br'));
    Mail::assertQueued(TrabalhoSubmetidoMail::class, fn ($m) => $m->hasTo($this->usuario->email));
});

// ---------- Regras do apresentador e dos autores ----------
it('exige e-mail, categoria, instituição e CPF do apresentador', function (array $alteracao, string $campo) {
    $this->actingAs($this->usuario)->post($this->url, frmEnvio(['autores' => [frmApresentador($alteracao)]]))->assertSessionHasErrors($campo);
    expect(Trabalho::count())->toBe(0);
})->with([
    'sem e-mail' => [['email' => ''], 'autores.0.email'],
    'e-mail inválido' => [['email' => 'xx'], 'autores.0.email'],
    'sem categoria' => [['categoria_autor' => ''], 'autores.0.categoria_autor'],
    'categoria inválida' => [['categoria_autor' => 'visitante'], 'autores.0.categoria_autor'],
    'sem instituição' => [['instituicao' => ''], 'autores.0.instituicao'],
    'sem nome' => [['nome_completo' => ''], 'autores.0.nome_completo'],
    'sem CPF' => [['cpf' => ''], 'autores.0.cpf'],
    'CPF inválido' => [['cpf' => '111.444.777-36'], 'autores.0.cpf'],
]);

it('o CPF do apresentador deixa de ser obrigatório quando a chamada não exige', function () {
    $this->config->update(['exige_cpf_apresentador' => false]);

    $this->actingAs($this->usuario)->post($this->url, frmEnvio(['autores' => [frmApresentador(['cpf' => ''])]]))->assertSessionHasNoErrors();
    expect(Trabalho::first()->autores->first()->cpf)->toBeNull();
});

it('todo autor precisa de nome e instituição', function () {
    foreach ([['nome_completo' => '', 'instituicao' => 'IFPA'], ['nome_completo' => 'Fulano', 'instituicao' => '']] as $i => $incompleto) {
        $this->actingAs($this->usuario)->post($this->url, frmEnvio(['titulo' => "T{$i}", 'autores' => [frmApresentador(), $incompleto + ['tipo_autor' => 'coautor']]]))
            ->assertSessionHasErrors();
    }
    expect(Trabalho::count())->toBe(0);
});

it('categoria, modalidade e PDF continuam obrigatórios no envio', function (array $alteracao, string $campo) {
    $this->actingAs($this->usuario)->post($this->url, array_merge(frmEnvio(), $alteracao))->assertSessionHasErrors($campo);
})->with([
    'sem categoria' => [['tipo_trabalho' => ''], 'tipo_trabalho'],
    'sem modalidade' => [['modalidade_apresentacao' => ''], 'modalidade_apresentacao'],
    'modalidade inválida' => [['modalidade_apresentacao' => 'virtual'], 'modalidade_apresentacao'],
    'sem arquivo' => [['arquivo' => null], 'arquivo'],
    'sem declaração' => [['declaracao_ineditismo' => null], 'declaracao_ineditismo'],
]);

it('resumo e palavras-chave opcionais: se informados, respeitam os limites', function () {
    $this->actingAs($this->usuario);

    $this->post($this->url, frmEnvio(['titulo' => 'A', 'palavras_chave' => 'a, b']))->assertSessionHasErrors('palavras_chave');
    $this->post($this->url, frmEnvio(['titulo' => 'B', 'palavras_chave' => 'a, b, c, d, e, f']))->assertSessionHasErrors('palavras_chave');
    $this->post($this->url, frmEnvio(['titulo' => 'C', 'resumo' => str_repeat('palavra ', 400)]))->assertSessionHasErrors('resumo');
    $this->post($this->url, frmEnvio(['titulo' => 'D', 'resumo' => 'Resumo curto é aceito quando não é exigido.', 'palavras_chave' => 'água, clima, Amazônia']))->assertSessionHasNoErrors();

    expect(Trabalho::count())->toBe(1);
});

it('quando a chamada exige resumo e palavras-chave, eles voltam a ser obrigatórios', function () {
    $this->config->update(['exige_resumo' => true, 'exige_palavras_chave' => true, 'resumo_min_palavras' => 20]);

    $this->actingAs($this->usuario)->post($this->url, frmEnvio())->assertSessionHasErrors(['resumo', 'palavras_chave']);
    $this->actingAs($this->usuario)->post($this->url, frmEnvio(['resumo' => str_repeat('palavra ', 30), 'palavras_chave' => 'a, b, c']))->assertSessionHasNoErrors();
});

it('rascunho continua exigindo apenas o título', function () {
    $this->actingAs($this->usuario)->post($this->url, ['acao' => 'rascunho', 'titulo' => 'Em andamento', 'autores' => [['nome_completo' => 'Fulana', 'tipo_autor' => 'autor_principal']]])->assertSessionHasNoErrors();

    expect(Trabalho::first()->status)->toBe('rascunho');
});

// ---------- Edição ----------
it('ao editar, o apresentador vem primeiro e os demais autores em seguida', function () {
    $this->actingAs($this->usuario)->post($this->url, frmEnvio(['acao' => 'rascunho', 'autores' => [
        frmApresentador(),
        ['nome_completo' => 'Coautor Um', 'instituicao' => 'IFPA', 'tipo_autor' => 'coautor'],
    ]]));
    $t = Trabalho::firstOrFail();
    // reordena no banco: o principal passa a ser o último
    $t->autores()->where('tipo_autor', 'autor_principal')->update(['ordem' => 9]);

    $r = $this->actingAs($this->usuario)->get(route('trabalhos.edit', $t))->assertOk();
    $autores = $r->viewData('autores');

    expect($autores)->toHaveCount(2)->and($autores[0]['tipo_autor'])->toBe('autor_principal')->and($autores[0]['nome_completo'])->toBe('Ana Apresentadora')
        ->and($autores[1]['nome_completo'])->toBe('Coautor Um')->and($autores[0]['categoria_autor'])->toBe('discente');
    $r->assertSee('maisAutores', false);
});

it('após erro de validação, reabre o formulário com os dados preenchidos', function () {
    $r = $this->actingAs($this->usuario)->from(route('trabalhos.create', $this->evento->slug))
        ->post($this->url, frmEnvio(['titulo' => 'Título preservado', 'modalidade_apresentacao' => '']))->assertRedirect(route('trabalhos.create', $this->evento->slug));

    $this->get(route('trabalhos.create', $this->evento->slug))->assertSee('Título preservado')->assertSee('Selecione a modalidade de apresentação.');
});

// ---------- Compatibilidade: autores sem CPF/e-mail no restante do sistema ----------
function frmTrabalhoAprovado($ctx, array $autores): Trabalho
{
    $t = mkTrabalho($ctx->evento, $ctx->usuario, ['autores' => $autores], 'aprovado');
    $t->update(['apresentado' => true, 'apresentado_em' => now(), 'autoriza_publicacao' => true]);

    return $t->fresh();
}

it('emite certificado sem CPF no texto e envia e-mail apenas a quem tem e-mail', function () {
    $gestor = mkUser();
    mkPapel($gestor, $this->evento, 'gestor_trabalhos');
    $t = frmTrabalhoAprovado($this, [
        ['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'instituicao' => 'IFPA', 'tipo_autor' => 'autor_principal', 'apresentador' => true],
        ['nome_completo' => 'Sem Dados', 'instituicao' => 'UFOPA', 'tipo_autor' => 'coautor'],
    ]);
    $svc = app(CertificadoTrabalhoService::class);
    Mail::fake();

    $novos = $svc->emitir($t, $gestor);

    expect($novos)->toHaveCount(2);
    Mail::assertQueued(\App\Mail\CertificadoTrabalhoMail::class, 1);
    Mail::assertQueued(\App\Mail\CertificadoTrabalhoMail::class, fn ($m) => $m->hasTo('ana@x.com'));

    $semCpf = $svc->textos($novos->last()->fresh());
    expect($semCpf['corpo'])->toContain('SEM DADOS')->not->toContain('CPF')->not->toContain('{');
    expect($svc->textos($novos->first()->fresh())['corpo'])->toContain('CPF 111.444.777-35');
});

it('a página pública de validação funciona para autor sem CPF', function () {
    $gestor = mkUser();
    $t = frmTrabalhoAprovado($this, [
        ['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'instituicao' => 'IFPA', 'tipo_autor' => 'autor_principal', 'apresentador' => true],
        ['nome_completo' => 'Sem Cpf', 'instituicao' => 'UFOPA', 'tipo_autor' => 'coautor'],
    ]);
    app(CertificadoTrabalhoService::class)->emitir($t, $gestor);
    $cert = $t->certificados()->whereHas('autor', fn ($a) => $a->where('nome_completo', 'Sem Cpf'))->first();

    $this->get(route('certificado.trabalho.validar', $cert->hash_validacao))->assertOk()->assertSee('Certificado autêntico')->assertSee('Sem Cpf')->assertDontSee('CPF:');
    $this->get(route('certificado.trabalho.download', $cert->hash_validacao))->assertOk();
});

it('detalhe do trabalho mostra "CPF não informado" e categoria do autor', function () {
    $this->actingAs($this->usuario)->post($this->url, frmEnvio(['autores' => [
        frmApresentador(), ['nome_completo' => 'Coautor', 'instituicao' => 'IFPA', 'tipo_autor' => 'coautor', 'categoria_autor' => 'docente'],
    ]]));
    $t = Trabalho::firstOrFail();

    $this->actingAs($this->usuario)->get(route('trabalhos.show', $t))->assertOk()->assertSee('CPF não informado')->assertSee('Docente')->assertSee('Discente')->assertSee('Categoria');
});

it('conflito de interesse e confirmação por frequência toleram autores sem CPF ou e-mail', function () {
    $gestor = mkUser();
    mkPapel($gestor, $this->evento, 'gestor_trabalhos');
    $av = mkUser();
    mkPapel($av, $this->evento, 'avaliador');
    $t = frmTrabalhoAprovado($this, [
        ['nome_completo' => 'Ana', 'email' => 'ana@ifpa.edu.br', 'instituicao' => 'IFPA', 'tipo_autor' => 'autor_principal', 'apresentador' => true],   // sem CPF
        ['nome_completo' => 'Sem Dados', 'instituicao' => 'UFOPA', 'tipo_autor' => 'coautor'],
    ]);

    expect(app(TrabalhoWorkflow::class)->conflitoDeInteresse($t, $av))->toBeNull();

    // apresentador sem CPF é reconhecido pelo e-mail da presença
    $sessao = Atividade::create(['evento_id' => $this->evento->id, 'titulo' => 'Sessão', 'tipo' => 'outro', 'data_inicio' => now()->addDay(), 'data_fim' => now()->addDay()->addHours(2), 'carga_horaria' => 2, 'vagas' => 10, 'token_frequencia' => uniqid()]);
    $t->update(['apresentado' => false]);
    app(ApresentacaoService::class)->definirSessao($t->fresh(), $sessao);
    $p = Participante::create(['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@ifpa.edu.br', 'tipo_vinculo' => 'aluno']);
    Frequencia::create(['participante_id' => $p->id, 'atividade_id' => $sessao->id, 'data_registro' => now(), 'hash_validacao' => uniqid(), 'tipo_participacao' => 'palestrante']);

    expect($t->fresh()->apresentado)->toBeTrue()->and($t->fresh()->apresentacao_origem)->toBe('frequencia');
});

// ---------- Rótulos e dados ----------
it('usa "Categoria" na exportação e rótulos curtos de modalidade', function () {
    $t = frmTrabalhoAprovado($this, [['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'a@x.com', 'instituicao' => 'IFPA', 'tipo_autor' => 'autor_principal', 'apresentador' => true]]);
    $t->update(['modalidade_apresentacao' => 'oral', 'tipo_trabalho' => 'projeto_integrador']);
    $export = new TrabalhosExport(collect([$t->fresh(['autores', 'area', 'avaliacoes'])]));

    expect($export->headings())->toContain('Categoria')->not->toContain('Tipo')
        ->and($export->map($t->fresh(['autores', 'area', 'avaliacoes'])))->toContain('Projeto Integrador (alunos do IFPA)')->toContain('Apresentação oral/remoto');
    expect(Trabalho::MODALIDADES)->toBe(['poster' => 'Pôster', 'oral' => 'Apresentação oral/remoto']);
});

it('a configuração da chamada salva tema, abertura, template e exigências', function () {
    $gestor = mkUser();
    mkPapel($gestor, $this->evento, 'gestor_trabalhos');
    $base = ['max_autores' => 10, 'max_arquivo_mb' => 10, 'resumo_min_palavras' => 50, 'resumo_max_palavras' => 250, 'tipos_permitidos' => ['pesquisa', 'extensao'],
        'avaliadores_por_trabalho' => 2, 'nota_minima_aprovacao' => 7, 'divergencia_maxima' => 3];
    $url = route('gestao.trabalhos.config.update', $this->evento);
    $this->actingAs($gestor);

    $this->put($url, array_merge($base, [
        'chamada_tema' => 'Tema novo', 'chamada_intro' => 'Abertura nova', 'template_url' => 'https://exemplo.com/template.docx',
        'exige_resumo' => 1, 'exige_palavras_chave' => 0, 'exige_cpf_apresentador' => 0,
    ]))->assertSessionHasNoErrors();

    $c = $this->config->fresh();
    expect($c->chamada_tema)->toBe('Tema novo')->and($c->chamada_intro)->toBe('Abertura nova')->and($c->template_url)->toBe('https://exemplo.com/template.docx')
        ->and($c->exige_resumo)->toBeTrue()->and($c->exige_palavras_chave)->toBeFalse()->and($c->exige_cpf_apresentador)->toBeFalse();

    $this->put($url, array_merge($base, ['template_url' => 'não é url']))->assertSessionHasErrors('template_url');
    $this->put($url, array_merge($base, ['tipos_permitidos' => ['resumo']]))->assertSessionHasErrors('tipos_permitidos.0');   // tipos antigos não existem mais
});

// ---------- Formulário único, erros com dados mantidos e palavras-chave em lista ----------
it('palavras-chave chegam como lista (uma por campo), sem depender de vírgula', function () {
    $this->actingAs($this->usuario)->post($this->url, frmEnvio(['palavras_chave' => ['água', 'clima do Pará', 'Amazônia', 'água']]))->assertSessionHasNoErrors();

    // repetidas (mesmo com maiúsculas diferentes) são descartadas; expressões com espaço e vírgula interna ficam inteiras
    expect(Trabalho::latest('id')->first()->palavras_chave)->toBe(['água', 'clima do Pará', 'Amazônia']);
});

it('o texto antigo separado por vírgula continua aceito', function () {
    $this->actingAs($this->usuario)->post($this->url, frmEnvio(['palavras_chave' => 'a, b, c']))->assertSessionHasNoErrors();
    expect(Trabalho::latest('id')->first()->palavras_chave)->toBe(['a', 'b', 'c']);
});

it('rejeita menos de 3 ou mais de 5 palavras-chave informadas, com mensagem clara', function () {
    $this->actingAs($this->usuario)->post($this->url, frmEnvio(['palavras_chave' => ['uma', 'duas']]))
        ->assertSessionHasErrors(['palavras_chave' => 'Informe de 3 a 5 palavras-chave.']);
    $this->post($this->url, frmEnvio(['palavras_chave' => ['a', 'b', 'c', 'd', 'e', 'f']]))->assertSessionHasErrors('palavras_chave');
});

it('após um erro o formulário volta com alerta e todos os dados preenchidos', function () {
    $envio = frmEnvio([
        'titulo' => 'Título que não pode sumir', 'palavras_chave' => ['solo', 'rio', 'peixe'], 'modalidade_apresentacao' => 'oral',
        'autores' => [frmApresentador(['cpf' => '000.000.000-00']), ['nome_completo' => 'Otto Orientador', 'instituicao' => 'IFPA', 'tipo_autor' => 'orientador']],
        'mais_autores' => 'sim',
    ]);

    $r = $this->actingAs($this->usuario)->from(route('trabalhos.create', $this->evento->slug))->post($this->url, $envio);
    $r->assertSessionHasErrors()->assertRedirect(route('trabalhos.create', $this->evento->slug));

    $this->followRedirects($r)->assertOk()
        ->assertSee('Não foi possível salvar')->assertSee('id="alerta-erros"', false)
        ->assertSee('Título que não pode sumir')->assertSee('Ana Apresentadora')->assertSee('Otto Orientador')
        ->assertSee('selecione-o novamente')
        ->assertSee('solo')->assertSee('peixe');   // palavras-chave voltam como chips
});

it('o formulário é uma página só: todas as seções ficam visíveis, sem etapas', function () {
    $this->actingAs($this->usuario)->get(route('trabalhos.create', $this->evento->slug))->assertOk()
        ->assertSee('id="sec-dados"', false)->assertSee('id="sec-apresentador"', false)->assertSee('id="sec-arquivo"', false)
        ->assertDontSee('passo === ', false)->assertDontSee('Próxima')->assertSee('name="palavras_chave[]"', false);
});
