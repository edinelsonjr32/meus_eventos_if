<?php

use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use App\Services\TrabalhoWorkflow;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->evento->trabalhoConfig->update(['exige_resumo' => false, 'exige_palavras_chave' => false]);
    $this->autor = mkUser('user', 'autor@x.com');
});

/** Rascunho "rico": dados que o formulário do autor não envia (telefone, Lattes...) e flags definidos pela gestão. */
function rascunhoRico($ctx): Trabalho
{
    return app(TrabalhoWorkflow::class)->salvar($ctx->evento, $ctx->autor, [
        'titulo' => 'Íntegro', 'resumo' => 'Resumo', 'palavras_chave' => 'a, b, c', 'tipo_trabalho' => 'extensao', 'modalidade_apresentacao' => 'oral',
        'agencia_fomento' => 'CNPq', 'projeto_pesquisa' => 'Proj', 'comite_etica' => 'CAAE', 'autoriza_publicacao' => false,
        'autores' => [
            ['nome_completo' => 'Ana', 'cpf' => '111.444.777-35', 'email' => 'ana@x.com', 'instituicao' => 'IFPA', 'categoria_autor' => 'discente', 'tipo_autor' => 'autor_principal', 'apresentador' => false, 'correspondente' => false,
                'telefone' => '(93) 90000-0001', 'vinculo' => 'aluno', 'titulacao' => 'Graduanda', 'lattes' => 'https://lattes.cnpq.br/1', 'orcid' => '0000-0001-2345-6789'],
            ['nome_completo' => 'Caio', 'cpf' => '529.982.247-25', 'email' => 'caio@x.com', 'instituicao' => 'UFOPA', 'categoria_autor' => 'discente', 'tipo_autor' => 'coautor', 'apresentador' => true, 'correspondente' => true,
                'telefone' => '(93) 90000-0002', 'vinculo' => 'externo', 'titulacao' => 'Mestre', 'lattes' => 'https://lattes.cnpq.br/2', 'orcid' => '0000-0002-2345-6789'],
            ['nome_completo' => 'Olga', 'cpf' => '168.995.350-09', 'email' => 'olga@x.com', 'instituicao' => 'IFPA', 'categoria_autor' => 'docente', 'tipo_autor' => 'orientador', 'apresentador' => false, 'correspondente' => false, 'titulacao' => 'Doutora'],
        ],
    ]);
}

/** O que o navegador envia ao salvar sem mexer em nada: o formulário não traz CPF dos demais autores, telefone, Lattes etc. */
function payloadDoFormulario(Trabalho $t, array $mudar = []): array
{
    $autores = $t->autores->sortBy(fn ($a) => $a->tipo_autor === 'autor_principal' ? 0 : 1)->values()->map(function ($a, $i) {
        $base = ['id' => $a->id, 'nome_completo' => $a->nome_completo, 'email' => $a->email, 'instituicao' => $a->instituicao, 'categoria_autor' => $a->categoria_autor, 'tipo_autor' => $a->tipo_autor,
            'apresentador' => (int) $a->apresentador, 'correspondente' => (int) $a->correspondente];

        return $i === 0 ? $base + ['cpf' => $a->cpf] : $base;   // só o 1º autor tem campo de CPF
    })->all();

    return array_replace_recursive(['acao' => 'rascunho', 'titulo' => $t->titulo, 'resumo' => $t->resumo, 'palavras_chave' => implode(', ', $t->palavras_chave), 'tipo_trabalho' => $t->tipo_trabalho,
        'modalidade_apresentacao' => $t->modalidade_apresentacao, 'agencia_fomento' => $t->agencia_fomento, 'projeto_pesquisa' => $t->projeto_pesquisa, 'comite_etica' => $t->comite_etica,
        'autoriza_publicacao' => (int) $t->autoriza_publicacao, 'autores' => $autores], $mudar);
}

function fotografia(Trabalho $t): array
{
    return $t->fresh('autores')->autores->sortBy('ordem')->map(fn ($a) => $a->only(['id', 'nome_completo', 'cpf', 'email', 'telefone', 'instituicao', 'vinculo', 'titulacao', 'lattes', 'orcid', 'tipo_autor', 'categoria_autor', 'apresentador', 'correspondente']))->values()->all();
}

it('salvar sem alterar nada não muda NENHUM dado dos autores (CPF dos demais, telefone, Lattes, ORCID, vínculo, flags)', function () {
    $t = rascunhoRico($this);
    $antes = fotografia($t);

    $this->actingAs($this->autor)->put(route('trabalhos.update', $t), payloadDoFormulario($t->fresh('autores')))->assertSessionHasNoErrors()->assertRedirect();

    expect(fotografia($t))->toBe($antes);
    $m = $t->fresh();
    expect($m->agencia_fomento)->toBe('CNPq')->and($m->projeto_pesquisa)->toBe('Proj')->and($m->comite_etica)->toBe('CAAE')->and($m->tipo_trabalho)->toBe('extensao');
});

it('o que o autor altera vale, e esvaziar um campo enviado o limpa (sem confundir com campo ausente)', function () {
    $t = rascunhoRico($this);
    $caio = $t->autores->firstWhere('nome_completo', 'Caio');

    $payload = payloadDoFormulario($t->fresh('autores'));
    $idx = collect($payload['autores'])->search(fn ($a) => $a['id'] === $caio->id);
    $payload['autores'][$idx]['nome_completo'] = 'Caio Renomeado';
    $payload['autores'][$idx]['email'] = '';             // enviado vazio: limpa
    $payload['autores'][$idx]['telefone'] = '';          // enviado vazio: limpa
    $this->actingAs($this->autor)->put(route('trabalhos.update', $t), $payload)->assertSessionHasNoErrors();

    $c = $caio->fresh();
    expect($c->nome_completo)->toBe('Caio Renomeado')->and($c->email)->toBeNull()->and($c->telefone)->toBeNull()
        ->and($c->cpf)->toBe('529.982.247-25')->and($c->lattes)->toBe('https://lattes.cnpq.br/2')->and($c->id)->toBe($caio->id);
});

it('o formulário de edição mantém apresentador e correspondente gravados no 1º autor (não força de volta)', function () {
    $t = rascunhoRico($this);   // a Ana (principal) NÃO é apresentadora nem correspondente: o Caio é

    $html = $this->actingAs($this->autor)->get(route('trabalhos.edit', $t))->assertOk()->getContent();
    expect($html)->toContain('name="autores[0][apresentador]" value="0"')->toContain('name="autores[0][correspondente]" value="0"')->toContain('name="autores[0][tipo_autor]" value="autor_principal"');

    // trabalho novo continua começando como apresentador e correspondente
    $novo = $this->get(route('trabalhos.create', $this->evento->slug))->assertOk()->getContent();
    expect($novo)->toContain('name="autores[0][apresentador]" value="1"')->toContain('name="autores[0][correspondente]" value="1"');
});

it('o formulário oferece todas as categorias habilitadas na chamada (não só a primeira)', function () {
    $this->evento->trabalhoConfig->update(['tipos_permitidos' => ['pesquisa', 'extensao', 'projeto_integrador', 'relato_experiencia']]);
    $t = rascunhoRico($this);

    foreach ([route('trabalhos.edit', $t), route('trabalhos.create', $this->evento->slug)] as $url) {
        $html = $this->actingAs($this->autor)->get($url)->assertOk()->getContent();
        foreach (['pesquisa', 'extensao', 'projeto_integrador', 'relato_experiencia'] as $tipo) {
            expect($html)->toContain('name="tipo_trabalho" id="cat_' . $tipo . '"');
        }
    }

    // se a gestão restringe, só as habilitadas aparecem
    $this->evento->trabalhoConfig->update(['tipos_permitidos' => ['pesquisa']]);
    $html = $this->get(route('trabalhos.create', $this->evento->slug))->getContent();
    expect($html)->toContain('id="cat_pesquisa"')->not->toContain('id="cat_extensao"');
});

it('trabalho de categoria que deixou de ser aceita continua editável sem perder a categoria salva', function () {
    $t = rascunhoRico($this);   // extensao
    TrabalhoConfig::where('evento_id', $this->evento->id)->update(['tipos_permitidos' => json_encode(['pesquisa'])]);

    $this->actingAs($this->autor)->put(route('trabalhos.update', $t), payloadDoFormulario($t->fresh('autores')))->assertSessionHasNoErrors();
    expect($t->fresh()->tipo_trabalho)->toBe('extensao');
});
