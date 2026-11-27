<?php

use App\Models\Atividade;
use App\Models\ChamadaProposta;
use App\Models\Equipe;
use App\Models\Local;
use App\Models\Participante;
use App\Models\Proposta;
use App\Services\PropostaService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->admin = mkUser('admin');
    $this->evento = mkEvento($this->admin);
    $this->local = Local::create(['evento_id' => $this->evento->id, 'nome' => 'Lab 1', 'tipo' => 'laboratorio', 'capacidade' => 30]);
    $this->autor = mkUser('user', 'autor@x.com');
    Participante::create(['user_id' => $this->autor->id, 'nome_completo' => 'Prof. Autor', 'cpf' => '111.444.777-35', 'email' => 'autor@x.com', 'tipo_vinculo' => 'servidor']);
    $this->chamada = ChamadaProposta::create(['evento_id' => $this->evento->id, 'titulo' => 'Minicursos', 'tipos_aceitos' => ['oficina'], 'ativa' => true, 'max_por_usuario' => 2, 'criado_por' => $this->admin->id]);
    $this->dados = ['tipo' => 'oficina', 'titulo' => 'Compostagem', 'resumo' => str_repeat('Aprender na prática. ', 10), 'carga_horaria_prevista' => 4, 'vagas_sugeridas' => 20,
        'minicurriculo' => 'Professor com experiência em ensino de práticas ambientais.', 'contato_whatsapp' => '(93) 91234-5678', 'disponibilidade' => collect(range(0, 3))->mapWithKeys(fn ($i) => [now()->addDays($i)->format('Y-m-d') => ['manha', 'tarde', 'noite']])->all()];
    $this->equipe = [
        ['nome' => 'Bia Co-ministrante', 'email' => 'bia@x.com', 'cpf' => '529.982.247-25', 'funcao' => 'ministrante'],
        ['nome' => 'Caio Ajudante', 'email' => 'caio@x.com', 'cpf' => '390.533.447-05'],
    ];
});

it('cada pessoa da proposta tem a atribuição de ministrante ou ajudante, e a organização pode mudar', function () {
    $this->actingAs($this->autor)->get(route('propostas.create', $this->chamada))->assertOk()->assertSee('Atribuição')->assertSee('Outros ministrantes e ajudantes');
    $this->post(route('propostas.store', $this->chamada), $this->dados + ['apoios' => $this->equipe])->assertRedirect();

    $p = Proposta::first();
    expect($p->apoios->pluck('funcao', 'nome')->all())->toBe(['Bia Co-ministrante' => 'ministrante', 'Caio Ajudante' => 'ajudante']);
    $this->get(route('propostas.show', $p))->assertSee('Bia Co-ministrante (Ministrante)')->assertSee('Caio Ajudante (Ajudante)');

    app(PropostaService::class)->decidir($p, $this->admin, 'aprovada', ['data_inicio' => now()->addDay()->setTime(8, 0), 'data_fim' => now()->addDay()->setTime(12, 0), 'local_id' => $this->local->id, 'vagas' => 20, 'carga_horaria' => 4]);
    $atividade = Atividade::find($p->fresh()->atividade_id);
    expect($atividade->responsavel)->toBe('Prof. Autor, Bia Co-ministrante');

    $caio = $p->apoios()->where('nome', 'Caio Ajudante')->first();
    $this->actingAs($this->admin)->get(route('admin.propostas.show', [$this->evento, $p]))->assertOk()->assertSee(route('admin.propostas.apoio-funcao', [$this->evento->id, $caio->id]), false);
    $this->post(route('admin.propostas.apoio-funcao', [$this->evento, $caio]), ['funcao' => 'ministrante'])->assertSessionHas('success');
    expect($atividade->fresh()->responsavel)->toBe('Prof. Autor, Bia Co-ministrante, Caio Ajudante');
    $this->post(route('admin.propostas.apoio-funcao', [$this->evento, $caio]), ['funcao' => 'chefe'])->assertSessionHasErrors('funcao');
});

it('emitir o certificado de ministrante inclui os demais ministrantes, não os ajudantes', function () {
    $svc = app(PropostaService::class);
    $p = $svc->enviar($this->autor, $this->chamada, $this->dados, $this->equipe);
    $svc->decidir($p, $this->admin, 'aprovada', ['data_inicio' => now()->addDay()->setTime(8, 0), 'data_fim' => now()->addDay()->setTime(12, 0), 'local_id' => $this->local->id, 'vagas' => 20, 'carga_horaria' => 4]);
    Atividade::find($p->fresh()->atividade_id)->update(['data_inicio' => now()->subDays(2), 'data_fim' => now()->subDays(2)->addHours(4)]);

    $this->actingAs($this->admin)->post(route('admin.propostas.certificado', [$this->evento, $p]))->assertSessionHas('success', 'Certificado de ministrante emitido para o proponente e mais 1 ministrante(s).');

    $nomes = Equipe::where('funcao', 'Ministrante')->get()->map(fn ($e) => $e->participante->nome_completo)->sort()->values()->all();
    expect($nomes)->toBe(['Bia Co-ministrante', 'Prof. Autor']);

    // a co-ministrante, com conta própria, vê e baixa o certificado dela
    $bia = mkUser('user', 'bia@x.com');
    Participante::where('cpf', '529.982.247-25')->update(['user_id' => $bia->id]);
    $hash = Equipe::whereHas('participante', fn ($q) => $q->where('user_id', $bia->id))->value('hash_validacao');
    $this->actingAs($bia)->get(route('propostas.minhas'))->assertOk()->assertSee('Meus certificados de ministrante')->assertSee($hash, false);
    $this->get(route('propostas.certificado', $this->evento))->assertRedirect(route('certificado.equipe.download', $hash));
});

it('o trabalho pode ter mais de um apresentador, cada um com a opção no formulário', function () {
    Illuminate\Support\Facades\Storage::fake('trabalhos');
    $evento = mkEvento($this->admin);
    $this->actingAs($this->autor)->get(route('trabalhos.create', $evento->slug))->assertOk()
        ->assertSee('Também vai apresentar o trabalho')->assertSee('autores[${i + 1}][cpf]', false);

    $dados = [
        'acao' => 'submeter', 'titulo' => 'Dois apresentadores', 'resumo' => trim(str_repeat('palavra ', 120)), 'palavras_chave' => 'ensino, tecnologia, inovação',
        'tipo_trabalho' => 'pesquisa', 'modalidade_apresentacao' => 'oral', 'declaracao_ineditismo' => 1, 'declaracao_regulamento' => 1, 'autoriza_publicacao' => 1,
        'arquivo' => Illuminate\Http\UploadedFile::fake()->createWithContent('t.pdf', "%PDF-1.4\nx\n%%EOF"),
        'autores' => [
            ['nome_completo' => 'Ana Autora', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'instituicao' => 'IFPA', 'categoria_autor' => 'docente', 'tipo_autor' => 'autor_principal', 'apresentador' => 1, 'correspondente' => 1],
            ['nome_completo' => 'Beto Coautor', 'cpf' => '', 'email' => 'beto@x.com', 'instituicao' => 'IFPA', 'categoria_autor' => 'discente', 'tipo_autor' => 'coautor', 'apresentador' => 1],
        ],
    ];

    $this->post(route('trabalhos.store', $evento->slug), $dados)->assertSessionHasErrors('autores.1.cpf');

    $dados['autores'][1]['cpf'] = '529.982.247-25';
    $dados['arquivo'] = Illuminate\Http\UploadedFile::fake()->createWithContent('t.pdf', "%PDF-1.4\nx\n%%EOF");
    $this->post(route('trabalhos.store', $evento->slug), $dados)->assertSessionHasNoErrors();

    $t = App\Models\Trabalho::where('titulo', 'Dois apresentadores')->first();
    expect($t->autores->where('apresentador', true)->pluck('nome_completo')->sort()->values()->all())->toBe(['Ana Autora', 'Beto Coautor']);
});
