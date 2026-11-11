<?php

use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Frequencia;
use App\Models\Inscricao;
use App\Models\Participante;
use App\Models\User;
use App\Services\RodadaTeste;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function specRodada(): array
{
    $contas = [['admin@teste.com', 'x'], ['adminb@teste.com', 'x'], ['inscrito@teste.com', 'x'], ['org1@teste.com', 'x'], ['org2@teste.com', 'x'], ['org3@teste.com', 'x']];
    foreach (range(1, 6) as $i) {
        $contas[] = [sprintf('aval%02d@teste.com', $i), 'x'];
    }

    return [
        'cpf_vinculado_ao_inscrito' => '377.594.580-61',
        'contas_a_criar' => array_map(fn ($c) => ['email' => $c[0], 'senha' => 'Senha-de-teste-1'], $contas),
        'areas_tematicas' => ['Educação e Tecnologia', 'Sistemas de Informação', 'Redes e Segurança'],
        'criterios_avaliacao' => ['Originalidade', 'Relevância', 'Clareza', 'Metodologia'],
        'trabalhos' => array_map(fn ($n) => ['n' => $n, 'titulo' => sprintf('Estudo de caso fictício %02d', $n), 'autor' => sprintf('Autor Teste %02d', $n), 'recusado' => $n >= 9], range(1, 10)),
        'atribuicoes' => ['aval01' => [1, 2, 3], 'aval02' => [4, 5, 6], 'aval03' => [7, 8, 9], 'aval04' => [10, 1, 4], 'aval05' => [5, 6], 'aval06' => [7, 8]],
        'alunos_p01_p30' => [],
    ];
}

function svcRodada(): RodadaTeste
{
    return new RodadaTeste(specRodada(), sys_get_temp_dir() . '/manifesto-rodada-' . getmypid() . '.json');
}

beforeEach(function () {
    // As datas da rodada são fixas (10/11/2026): o teste não pode depender de qual é o dia de hoje
    $this->travelTo(\Illuminate\Support\Carbon::create(2026, 11, 10, 12, 0, 0, config('app.timezone')));
    Storage::fake('trabalhos');
    @unlink(sys_get_temp_dir() . '/manifesto-rodada-' . getmypid() . '.json');
    $this->svc = svcRodada();
});

afterEach(fn () => @unlink(sys_get_temp_dir() . '/manifesto-rodada-' . getmypid() . '.json'));

/** Dados "reais" pré-existentes que nada pode tocar. */
function dadosReais(): array
{
    $admin = mkUser('admin', 'diretor@ifpa.edu.br');
    $evento = mkEvento($admin);
    $evento->update(['titulo' => 'Semana Real de Dezembro', 'slug' => 'semana-real']);
    $user = mkUser('user', 'maria.real@gmail.com');
    $part = Participante::create(['user_id' => $user->id, 'nome_completo' => 'Maria Real', 'cpf' => '529.982.247-25', 'email' => 'maria.real@gmail.com', 'tipo_vinculo' => 'externo']);
    Inscricao::create(['evento_id' => $evento->id, 'participante_id' => $part->id, 'data_inscricao' => now()]);
    $trab = mkTrabalho($evento, $user);

    return compact('admin', 'evento', 'user', 'part', 'trab');
}

function contagens(): array
{
    return collect(['users', 'participantes', 'eventos', 'atividades', 'inscricoes', 'frequencias', 'trabalhos', 'trabalho_avaliacoes', 'trabalho_autores', 'trabalho_arquivos', 'evento_user'])
        ->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
}

it('cria contas, eventos, trabalhos, atribuições e notas descritos no spec', function () {
    $m = $this->svc->preparar();

    expect(User::where('email', 'like', '%@teste.com')->count())->toBe(12)
        ->and(User::where('email', 'admin@teste.com')->first()->role)->toBe('admin')
        ->and(User::where('email', 'inscrito@teste.com')->first()->role)->toBe('user')
        ->and(Evento::count())->toBe(5)
        ->and(DB::table('trabalhos')->count())->toBe(40)
        ->and(Atividade::count())->toBe(4)
        ->and(Participante::where('cpf', '377.594.580-61')->first()->user->email ?? User::find(Participante::where('cpf', '377.594.580-61')->value('user_id'))->email)->toBe('inscrito@teste.com');

    $teste = Evento::where('slug', 'evento-teste')->first();
    // 16 atribuições pendentes (3+3+3+3+2+2) no Evento Teste, todos em avaliação
    expect(DB::table('trabalho_avaliacoes')->whereIn('trabalho_id', $teste->trabalhos()->pluck('id'))->where('status', 'pendente')->count())->toBe(16)
        ->and($teste->trabalhos()->where('status', 'em_avaliacao')->count())->toBe(10);
    // aval05 não tem o trabalho 1 atribuído: a URL de CT-17 existe no manifesto
    expect($m['urls']['trabalho_nao_atribuido_aval05_aval06'])->toStartWith('/trabalhos/');

    // Eventos Org: 1-3 aprovados com 2 avaliações concluídas; 4-5 sem atribuição; 9-10 recusados
    $org = Evento::where('slug', 'evento-org-d1')->first();
    $t = fn ($n) => $org->trabalhos()->where('titulo', sprintf('Estudo de caso fictício %02d', $n))->first();
    expect($t(1)->status)->toBe('aprovado')->and($t(1)->avaliacoes()->where('status', 'concluida')->count())->toBe(2)
        ->and($t(1)->nota_final)->not->toBeNull()
        ->and($t(4)->avaliacoes()->count())->toBe(0)->and($t(4)->status)->toBe('em_avaliacao')
        ->and($t(9)->status)->toBe('reprovado')
        ->and(DB::table('trabalho_avaliacao_notas')->count())->toBe(3 * 3 * 2 * 4);

    // Eventos de teste não aparecem no portal público; Prazo Encerrado está fechado
    $this->get('/')->assertOk()->assertDontSee('Evento Teste');
    expect(Evento::where('slug', 'evento-prazo-encerrado')->first()->trabalhoConfig->estaAberta())->toBeFalse()
        ->and($teste->trabalhoConfig->estaAberta())->toBeTrue()
        ->and($teste->trabalhoConfig->max_arquivo_mb)->toBe(2)
        ->and($teste->latitude)->toBeNull();
    expect(is_file(sys_get_temp_dir() . '/manifesto-rodada-' . getmypid() . '.json'))->toBeTrue();
});

it('é idempotente: preparar de novo não duplica e uma colisão não cria nada', function () {
    $this->svc->preparar();
    $antes = contagens();

    expect(fn () => svcRodada()->preparar())->toThrow(RuntimeException::class, 'manifesto');
    expect(contagens())->toBe($antes);

    // Sem manifesto, mas com colisão (e-mail e slug existentes): aborta e não cria nada
    @unlink(sys_get_temp_dir() . '/manifesto-rodada-' . getmypid() . '.json');
    expect(fn () => svcRodada()->preparar())->toThrow(RuntimeException::class, 'Colisão');
    expect(contagens())->toBe($antes);
});

it('uma colisão com dado real aborta sem criar nada', function () {
    mkUser('user', 'admin@teste.com');
    Participante::create(['nome_completo' => 'Pessoa Real', 'cpf' => '377.594.580-61', 'email' => 'real@x.com', 'tipo_vinculo' => 'externo']);
    $antes = contagens();

    expect(fn () => $this->svc->preparar())->toThrow(RuntimeException::class, 'CPF já cadastrado');
    expect(contagens())->toBe($antes)->and($this->svc->manifesto())->toBeNull();
});

it('remove só o manifesto e os cadastros dos alunos, sem tocar em dado real', function () {
    $real = dadosReais();
    $antesReal = contagens();
    $this->svc->preparar();
    $comRodada = contagens();

    // Alunos: cadastro, inscrição, presença; organizador cria evento; CT-18 religa o participante do inscrito à conta do aluno
    $teste = Evento::where('slug', 'evento-teste')->first();
    $ativ = Atividade::where('evento_id', $teste->id)->first();
    $aluno = mkUser('user', 'p01@teste.com');
    $extra = mkUser('user', 'p01b@teste.com');
    $pa = Participante::create(['user_id' => $aluno->id, 'nome_completo' => 'Alice Teste', 'cpf' => '429.755.285-07', 'email' => 'p01@teste.com', 'tipo_vinculo' => 'externo']);
    Inscricao::create(['evento_id' => $teste->id, 'participante_id' => $pa->id, 'data_inscricao' => now()]);
    Frequencia::create(['participante_id' => $pa->id, 'atividade_id' => $ativ->id, 'data_registro' => now(), 'hash_validacao' => uniqid(), 'tipo_participacao' => 'ouvinte']);
    $evNovo = Evento::create(['titulo' => 'Evento Novo D1', 'slug' => 'evento-novo-d1', 'data_inicio' => now(), 'data_fim' => now()->addDay(), 'local' => 'X', 'criado_por' => User::where('email', 'org1@teste.com')->value('id')]);
    $sequestrado = Participante::where('cpf', '377.594.580-61')->first();
    $sequestrado->update(['user_id' => mkUser('user', 'p27@teste.com')->id, 'email' => 'p27@teste.com']);

    // Dry-run: informa e não apaga
    $antesDry = contagens();
    $r = $this->svc->remover(false);
    expect($r['users'])->toBe(12 + 3)->and($r['eventos'])->toBe(6)->and($r['vinculos_externos'])->toBe([])
        ->and(contagens())->toBe($antesDry);

    $this->svc->remover(true);

    // Tudo da rodada sumiu; o que era real ficou exatamente como estava
    expect(contagens())->toBe($antesReal)
        ->and(User::whereIn('id', [$real['admin']->id, $real['user']->id])->count())->toBe(2)
        ->and(Evento::find($real['evento']->id)->titulo)->toBe('Semana Real de Dezembro')
        ->and(Participante::find($real['part']->id)->cpf)->toBe('529.982.247-25')
        ->and(Inscricao::where('participante_id', $real['part']->id)->count())->toBe(1)
        ->and($real['trab']->fresh()->titulo)->toBe($real['trab']->titulo)
        ->and(User::where('email', 'like', '%@teste.com')->count())->toBe(0)
        ->and(Participante::where('cpf', '377.594.580-61')->exists())->toBeFalse()
        ->and(Evento::withTrashed()->where('slug', 'evento-novo-d1')->exists())->toBeFalse();
    expect($this->svc->manifesto())->toBeNull();
});

it('a remoção aborta se um cadastro de aluno estiver ligado a evento real', function () {
    $real = dadosReais();
    $this->svc->preparar();

    $aluno = mkUser('user', 'p02@teste.com');
    $pa = Participante::create(['user_id' => $aluno->id, 'nome_completo' => 'Bruno Teste', 'cpf' => '358.868.259-75', 'email' => 'p02@teste.com', 'tipo_vinculo' => 'externo']);
    Inscricao::create(['evento_id' => $real['evento']->id, 'participante_id' => $pa->id, 'data_inscricao' => now()]);
    $antes = contagens();

    expect(fn () => $this->svc->remover(true))->toThrow(RuntimeException::class, 'fora da rodada');
    expect(contagens())->toBe($antes);
});

it('só aceita o padrão de e-mails dos alunos (p01..p30) e não apaga usuário real parecido', function () {
    $this->svc->preparar();
    $parecidos = [mkUser('user', 'p31@teste.com'), mkUser('user', 'paulo@teste.com'), mkUser('user', 'p01@teste.com.br'), mkUser('user', 'p05@outro.com')];
    mkUser('user', 'p30c@teste.com');

    $this->svc->remover(true);

    foreach ($parecidos as $u) {
        expect(User::find($u->id))->not->toBeNull();
    }
    expect(User::where('email', 'p30c@teste.com')->exists())->toBeFalse();
});

// ---------------- Patch de segurança da rodada ----------------

it('com SUPERADMIN_EMAILS, só o superadmin lista usuários e busca pessoas de todo o sistema', function () {
    config(['app.superadmin_emails' => 'geral@ifpa.edu.br']);
    $geral = mkUser('admin', 'geral@ifpa.edu.br');
    $teste = mkUser('admin', 'org1@teste.com');
    $evento = mkEvento($teste);
    $real = Participante::create(['nome_completo' => 'Pessoa Real', 'cpf' => '529.982.247-25', 'email' => 'real@x.com', 'tipo_vinculo' => 'externo']);

    expect(Gate::forUser($geral)->allows('superadmin'))->toBeTrue()->and(Gate::forUser($teste)->allows('superadmin'))->toBeFalse();
    $this->actingAs($teste)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($geral)->get(route('admin.users.index'))->assertOk();

    $url = route('admin.eventos.buscar-pessoas', $evento->id);
    $this->actingAs($teste)->getJson($url . '?tipo=participante&q=real')->assertJsonCount(0, 'results');
    $this->actingAs($teste)->getJson($url . '?tipo=usuario&q=geral')->assertJsonCount(0, 'results');
    $this->actingAs($teste)->getJson($url . '?tipo=usuario&q=geral@ifpa.edu.br')->assertJsonCount(1, 'results');
    $this->actingAs($geral)->getJson(route('admin.eventos.buscar-pessoas', mkEvento($geral)->id) . '?tipo=participante&q=real')->assertJsonCount(1, 'results');
});

it('um administrador real que não é superadmin continua listando usuários e pessoas para designar papéis', function () {
    config(['app.superadmin_emails' => 'geral@ifpa.edu.br']);
    $colega = mkUser('admin', 'colega@ifpa.edu.br');
    $evento = mkEvento($colega);
    mkUser('user', 'maria.silva@exemplo.com')->update(['name' => 'Maria Silva']);
    Participante::create(['nome_completo' => 'Pessoa Real', 'cpf' => '529.982.247-25', 'email' => 'real@x.com', 'tipo_vinculo' => 'externo']);

    $url = route('admin.eventos.buscar-pessoas', $evento->id);
    // lista inicial (termo vazio, como o Select2 faz ao abrir) e busca por nome
    expect($this->actingAs($colega)->getJson($url . '?tipo=usuario&q=')->json('results'))->not->toBeEmpty();
    $this->actingAs($colega)->getJson($url . '?tipo=usuario&q=maria')->assertJsonCount(1, 'results');
    $this->actingAs($colega)->getJson($url . '?tipo=participante&q=real')->assertJsonCount(1, 'results');
    // a página de papéis abre
    $this->actingAs($colega)->get(route('admin.eventos.papeis.index', $evento->id))->assertOk();
});

it('sem SUPERADMIN_EMAILS o comportamento anterior é mantido (todo admin é geral)', function () {
    config(['app.superadmin_emails' => '']);
    $this->actingAs(mkUser('admin'))->get(route('admin.users.index'))->assertOk();
});

it('e-mail para domínio de teste é cancelado; para outros domínios sai normalmente', function () {
    config(['mail.default' => 'array', 'mail.bloquear_dominios' => 'teste.com']);
    $enviados = fn () => count(app('mail.manager')->mailer('array')->getSymfonyTransport()->messages());

    Mail::raw('x', fn ($m) => $m->to('p01@teste.com')->subject('s'));
    expect($enviados())->toBe(0);
    Mail::raw('x', fn ($m) => $m->to('alguem@gmail.com')->subject('s'));
    expect($enviados())->toBe(1);
    Mail::raw('x', fn ($m) => $m->to('p01@teste.com')->cc('alguem@gmail.com')->subject('s'));
    expect($enviados())->toBe(2);
});
