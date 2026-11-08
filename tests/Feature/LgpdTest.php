<?php

use App\Models\Anais;
use App\Models\Atividade;
use App\Models\CertificadoTrabalho;
use App\Models\Frequencia;
use App\Models\Inscricao;
use App\Models\LgpdRegistro;
use App\Models\Participante;
use App\Models\Trabalho;
use App\Models\User;
use App\Services\CertificadoTrabalhoService;
use App\Services\Lgpd\TitularService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/** Cenário de um titular com dados espalhados: conta, participante, inscrição, presença e autoria com certificado. */
function lgpdCenario($ctx): array
{
    $user = mkUser('user', 'ana@titular.com');
    $p = Participante::create(['user_id' => $user->id, 'nome_completo' => 'Ana Titular', 'cpf' => '111.444.777-35', 'email' => 'ana@titular.com', 'tipo_vinculo' => 'aluno', 'matricula' => '2024001']);
    Inscricao::create(['evento_id' => $ctx->evento->id, 'participante_id' => $p->id, 'data_inscricao' => now()]);
    $atv = Atividade::create(['evento_id' => $ctx->evento->id, 'titulo' => 'Palestra', 'tipo' => 'palestra', 'data_inicio' => now(), 'data_fim' => now()->addHours(2), 'carga_horaria' => 2, 'vagas' => 10, 'token_frequencia' => uniqid()]);
    Frequencia::create(['participante_id' => $p->id, 'atividade_id' => $atv->id, 'data_registro' => now(), 'hash_validacao' => 'H' . uniqid(), 'tipo_participacao' => 'ouvinte']);

    $t = mkTrabalho($ctx->evento, $user, ['titulo' => 'Trabalho da Ana', 'autores' => [
        ['nome_completo' => 'Ana Titular', 'cpf' => '111.444.777-35', 'email' => 'ana@titular.com', 'instituicao' => 'IFPA', 'categoria_autor' => 'discente', 'tipo_autor' => 'autor_principal', 'apresentador' => true],
        ['nome_completo' => 'Outro Autor', 'cpf' => '529.982.247-25', 'email' => 'outro@x.com', 'instituicao' => 'UFOPA', 'tipo_autor' => 'coautor'],
    ]], 'aprovado');
    $t->update(['apresentado' => true, 'apresentado_em' => now(), 'autoriza_publicacao' => true]);
    app(CertificadoTrabalhoService::class)->emitir($t->fresh(), $ctx->gestor);

    return [$user, $p, $t->fresh(['autores', 'certificados'])];
}

beforeEach(function () {
    Mail::fake();
    Storage::fake('trabalhos');

    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->evento->update(['slug' => 'lgpd']);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->svc = app(TitularService::class);
});

// ---------- Política de privacidade ----------
it('a política de privacidade é pública e usa os dados do responsável', function () {
    config(['privacidade.controlador' => 'IFPA Óbidos (teste)', 'privacidade.contato' => 'lgpd@ifpa.test', 'privacidade.dias_rascunho' => 90]);

    $this->get(route('privacidade'))->assertOk()
        ->assertSee('Política de Privacidade')->assertSee('IFPA Óbidos (teste)')->assertSee('lgpd@ifpa.test')->assertSee('90 dias')
        ->assertSee('Não é armazenada', false)->assertSee('CPF e e-mail nunca são publicados')->assertSee('Seus direitos');
});

it('os links para a política aparecem no login, no sistema, no formulário e nos Anais', function () {
    $url = route('privacidade');
    Anais::create(['evento_id' => $this->evento->id, 'titulo' => 'Anais', 'ano' => 2026, 'local_publicacao' => 'Óbidos', 'editora' => 'IFPA', 'status' => 'publicado', 'publicado_em' => now()]);

    $this->get(route('login'))->assertSee($url, false);
    $this->get(route('anais.index', 'lgpd'))->assertSee($url, false);
    $this->get(route('evento.publico.show', 'lgpd'))->assertSee($url, false);
    $this->actingAs(mkUser())->get(route('trabalhos.index'))->assertSee($url, false);
    $this->get(route('trabalhos.create', 'lgpd'))->assertSee('Política de Privacidade')->assertSee('CPF e e-mail dos autores nunca são publicados');
});

// ---------- Localizar titular ----------
it('normaliza e-mail e CPF e rejeita identificadores inválidos', function () {
    expect(TitularService::normalizar(' Ana@Titular.com '))->toBe(['email' => 'ana@titular.com', 'cpf' => null])
        ->and(TitularService::normalizar('11144477735'))->toBe(['email' => null, 'cpf' => '111.444.777-35'])
        ->and(TitularService::normalizar('111.444.777-35'))->toBe(['email' => null, 'cpf' => '111.444.777-35'])
        ->and(TitularService::normalizar('111.444.777-36'))->toBe(['email' => null, 'cpf' => null])
        ->and(TitularService::normalizar('nao-e-nada'))->toBe(['email' => null, 'cpf' => null]);
});

it('liga e-mail, CPF, conta, participante e autoria do mesmo titular', function () {
    [$user, $p, $t] = lgpdCenario($this);
    mkUser('user', 'terceiro@x.com');

    foreach (['ana@titular.com', '111.444.777-35', '11144477735'] as $id) {
        $alvo = $this->svc->localizar($id);
        expect($alvo['usuarios']->pluck('id')->all())->toBe([$user->id])
            ->and($alvo['participantes']->pluck('id')->all())->toBe([$p->id])
            ->and($alvo['autorias'])->toHaveCount(1)
            ->and($alvo['autorias']->first()->nome_completo)->toBe('Ana Titular');
    }
    expect($this->svc->encontrou($this->svc->localizar('ninguem@nada.com')))->toBeFalse();
});

// ---------- Exportar (acesso/portabilidade) ----------
it('exporta todos os dados do titular e registra a auditoria sem guardar o dado pessoal', function () {
    [$user, $p, $t] = lgpdCenario($this);

    $dados = $this->svc->exportar('ana@titular.com');

    expect($dados['identificadores']['cpfs'])->toContain('111.444.777-35')
        ->and($dados['contas'][0]['email'])->toBe('ana@titular.com')
        ->and($dados['participantes'][0]['nome_completo'])->toBe('Ana Titular')
        ->and($dados['participantes'][0]['inscricoes'][0]['evento'])->toBe($this->evento->titulo)
        ->and($dados['participantes'][0]['presencas'][0]['atividade'])->toBe('Palestra')
        ->and($dados['trabalhos_como_autor'][0]['titulo'])->toBe('Trabalho da Ana')
        ->and($dados['trabalhos_como_autor'][0]['seus_dados_na_autoria'])->toHaveCount(1);

    // dados de OUTRAS pessoas (coautor) não vazam para o titular
    expect(json_encode($dados))->not->toContain('Outro Autor')->not->toContain('529.982.247-25');

    $log = LgpdRegistro::where('acao', 'exportacao')->firstOrFail();
    expect($log->titular_hash)->toBe(hash('sha256', 'ana@titular.com'))->and($log->resumo['trabalhos'])->toBe(1);
    expect(json_encode($log->toArray()))->not->toContain('ana@titular.com')->not->toContain('111.444');
});

it('o comando de exportação grava o arquivo e recusa titular inexistente ou identificador inválido', function () {
    lgpdCenario($this);
    Storage::fake('local');

    $this->artisan('lgpd:exportar', ['identificador' => '111.444.777-35'])->expectsOutputToContain('Arquivo gerado')->assertExitCode(0);
    $arquivos = Storage::disk('local')->files('lgpd');
    expect($arquivos)->toHaveCount(1)->and(json_decode(Storage::disk('local')->get($arquivos[0]), true)['contas'][0]['email'])->toBe('ana@titular.com');

    $this->artisan('lgpd:exportar', ['identificador' => 'ninguem@nada.com'])->expectsOutputToContain('Nenhum dado encontrado')->assertExitCode(1);
    $this->artisan('lgpd:exportar', ['identificador' => 'xyz'])->expectsOutputToContain('e-mail válido ou um CPF válido')->assertExitCode(1);
});

// ---------- Anonimizar (eliminação) ----------
it('anonimiza conta, participante e autoria, preserva os registros acadêmicos e revoga certificados', function () {
    [$user, $p, $t] = lgpdCenario($this);
    $outro = $t->autores->firstWhere('nome_completo', 'Outro Autor');
    $hash = $t->certificados->firstWhere('trabalho_autor_id', $t->autores->firstWhere('nome_completo', 'Ana Titular')->id)->hash_validacao;
    DB::table('sessions')->insert(['id' => 'sess-ana', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $r = $this->svc->anonimizar('111.444.777-35');

    expect($r)->toMatchArray(['contas' => 1, 'participantes' => 1, 'autorias' => 1, 'certificados_revogados' => 1]);

    $user->refresh(); $p->refresh();
    expect($user->name)->toBe('Usuário anonimizado')->and($user->email)->toBe("anonimo-{$user->id}@anonimizado.invalid")
        ->and(Hash::check('password', $user->password))->toBeFalse()
        ->and($p->nome_completo)->toBe('Titular anonimizado')->and($p->cpf)->toStartWith('ANON-')->and($p->email)->toContain('@anonimizado.invalid')->and($p->matricula)->toBeNull()->and($p->user_id)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();

    $autoria = $t->fresh('autores')->autores->firstWhere('id', $t->autores->first()->id);
    expect($autoria->nome_completo)->toBe('Titular anonimizado')->and($autoria->cpf)->toBeNull()->and($autoria->email)->toBeNull();

    // registros acadêmicos preservados (sem identificar a pessoa)
    expect($t->fresh()->titulo)->toBe('Trabalho da Ana')->and(Inscricao::count())->toBe(1)->and(Frequencia::count())->toBe(1);

    // o certificado anonimizado deixa de valer; o de outra pessoa continua
    $this->get(route('certificado.trabalho.validar', $hash))->assertOk()->assertSee('Certificado revogado')->assertDontSee('Ana Titular');
    $this->get(route('certificado.trabalho.download', $hash))->assertStatus(410);
    expect(CertificadoTrabalho::where('trabalho_autor_id', $outro->id)->whereNull('revogado_em')->exists())->toBeTrue()
        ->and($outro->fresh()->nome_completo)->toBe('Outro Autor');

    // trilha de auditoria
    expect(LgpdRegistro::where('acao', 'anonimizacao')->first()->resumo['autorias'])->toBe(1);
});

it('anonimizar tira os papéis da pessoa nos eventos e bloqueia o login antigo', function () {
    $user = mkUser('user', 'av@titular.com');
    mkPapel($user, $this->evento, 'avaliador');

    $this->svc->anonimizar('av@titular.com');

    expect(DB::table('evento_user')->where('user_id', $user->id)->exists())->toBeFalse();
    $this->post('/login', ['email' => 'av@titular.com', 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();
});

it('anonimizar vários participantes não colide no CPF único', function () {
    $a = Participante::create(['nome_completo' => 'A', 'cpf' => '111.444.777-35', 'email' => 'a@x.com', 'tipo_vinculo' => 'externo']);
    $b = Participante::create(['nome_completo' => 'B', 'cpf' => '529.982.247-25', 'email' => 'b@x.com', 'tipo_vinculo' => 'externo']);

    $this->svc->anonimizar('a@x.com');
    $this->svc->anonimizar('b@x.com');

    expect($a->fresh()->cpf)->not->toBe($b->fresh()->cpf)->and($b->fresh()->nome_completo)->toBe('Titular anonimizado');
});

it('o comando de anonimização pede confirmação e respeita --confirmar', function () {
    [$user] = lgpdCenario($this);

    $this->artisan('lgpd:anonimizar', ['identificador' => 'ana@titular.com'])->expectsConfirmation('Anonimizar estes dados? Não há como desfazer.', 'no')->assertExitCode(1);
    expect($user->fresh()->name)->not->toBe('Usuário anonimizado');

    $this->artisan('lgpd:anonimizar', ['identificador' => 'ana@titular.com', '--confirmar' => true])->expectsOutputToContain('Concluído')->assertExitCode(0);
    expect($user->fresh()->name)->toBe('Usuário anonimizado');

    $this->artisan('lgpd:anonimizar', ['identificador' => 'ninguem@nada.com', '--confirmar' => true])->expectsOutputToContain('Nenhum dado encontrado')->assertExitCode(1);
});

it('a página dos Anais continua funcionando depois de anonimizar um autor', function () {
    [$user, $p, $t] = lgpdCenario($this);
    Anais::create(['evento_id' => $this->evento->id, 'titulo' => 'Anais', 'ano' => 2026, 'local_publicacao' => 'Óbidos', 'editora' => 'IFPA', 'status' => 'publicado', 'publicado_em' => now()]);

    $this->svc->anonimizar('ana@titular.com');

    $this->get(route('anais.show', ['lgpd', $t->codigo]))->assertOk()->assertSee('Trabalho da Ana')->assertSee('Titular anonimizado')->assertDontSee('Ana Titular');
});

// ---------- Exclusão de conta pelo titular (antes: erro 500 com vínculos) ----------
it('excluir a conta sem vínculos apaga o usuário e anonimiza o cadastro de participante', function () {
    $user = mkUser('user', 'solta@x.com');
    $p = Participante::create(['user_id' => $user->id, 'nome_completo' => 'Solta', 'cpf' => '529.982.247-25', 'email' => 'solta@x.com', 'tipo_vinculo' => 'externo']);

    $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertSessionHasNoErrors()->assertRedirect('/');

    $this->assertGuest();
    expect(User::find($user->id))->toBeNull()->and($p->fresh()->nome_completo)->toBe('Titular anonimizado')->and($p->fresh()->cpf)->toStartWith('ANON-');
    expect(LgpdRegistro::where('acao', 'exclusao_conta')->exists())->toBeTrue();
});

it('excluir a conta de quem tem trabalhos, avaliações ou eventos anonimiza em vez de falhar', function (string $vinculo) {
    $user = mkUser($vinculo === 'evento' ? 'admin' : 'user', "{$vinculo}@x.com");
    if ($vinculo === 'trabalho') {
        mkTrabalho($this->evento, $user, ['titulo' => 'Trabalho do excluído']);
    } elseif ($vinculo === 'avaliacao') {
        $t = mkTrabalho($this->evento, mkUser(), []);
        $t->avaliacoes()->create(['avaliador_id' => $user->id, 'rodada' => 1, 'status' => 'pendente']);
    } else {
        mkEvento($user);
    }

    $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertSessionHasNoErrors()->assertRedirect('/');

    $this->assertGuest();
    $u = User::find($user->id);
    expect($u)->not->toBeNull()->and($u->name)->toBe('Usuário anonimizado')->and($u->email)->toContain('@anonimizado.invalid');
    // os trabalhos continuam existindo (o do avaliador e o do próprio excluído)
    expect(Trabalho::count())->toBe($vinculo === 'evento' ? 0 : 1);
})->with(['trabalho', 'avaliacao', 'evento']);

// ---------- Retenção ----------
it('apaga rascunhos abandonados e seus arquivos, preservando os recentes e os enviados', function () {
    $autor = mkUser();
    $velho = app(\App\Services\TrabalhoWorkflow::class)->salvar($this->evento, $autor, ['titulo' => 'Rascunho velho', 'autores' => [['nome_completo' => 'A', 'tipo_autor' => 'autor_principal']]]);
    Storage::disk('trabalhos')->put('velho.pdf', "%PDF-1.4\n%%EOF");
    $velho->arquivos()->create(['versao' => 1, 'tipo' => 'original', 'path' => 'velho.pdf', 'nome_original' => 'v.pdf', 'mime' => 'application/pdf', 'tamanho' => 10, 'sha256' => str_repeat('a', 64), 'enviado_por' => $autor->id, 'ativo' => true]);
    $recente = app(\App\Services\TrabalhoWorkflow::class)->salvar($this->evento, $autor, ['titulo' => 'Rascunho recente', 'autores' => []]);
    $enviado = mkTrabalho($this->evento, $autor, ['titulo' => 'Enviado antigo']);
    Trabalho::whereKey([$velho->id, $enviado->id])->update(['updated_at' => now()->subDays(400)]);

    $this->artisan('lgpd:limpar-rascunhos', ['--dias' => 180])->expectsOutputToContain('1 rascunho(s)')->assertExitCode(0);

    expect(Trabalho::withTrashed()->whereKey($velho->id)->exists())->toBeFalse()
        ->and(Storage::disk('trabalhos')->exists('velho.pdf'))->toBeFalse()
        ->and(Trabalho::whereKey($recente->id)->exists())->toBeTrue()
        ->and(Trabalho::whereKey($enviado->id)->exists())->toBeTrue()
        ->and(DB::table('trabalho_autores')->where('trabalho_id', $velho->id)->exists())->toBeFalse();
    expect(LgpdRegistro::where('acao', 'limpeza_rascunhos')->first()->resumo['rascunhos_apagados'])->toBe(1);
});

it('a limpeza usa o prazo configurado e roda semanalmente', function () {
    config(['privacidade.dias_rascunho' => 30]);
    $autor = mkUser();
    $r = app(\App\Services\TrabalhoWorkflow::class)->salvar($this->evento, $autor, ['titulo' => 'Rascunho de 40 dias', 'autores' => []]);
    Trabalho::whereKey($r->id)->update(['updated_at' => now()->subDays(40)]);

    $this->artisan('lgpd:limpar-rascunhos')->expectsOutputToContain('há mais de 30 dias')->assertExitCode(0);
    expect(Trabalho::whereKey($r->id)->exists())->toBeFalse();

    $this->artisan('schedule:list')->expectsOutputToContain('lgpd:limpar-rascunhos')->assertExitCode(0);
});
