<?php

use App\Mail\MovimentacaoMail;
use App\Services\AvaliacaoApresentacaoService;
use App\Services\AvaliacaoService;
use App\Services\TrabalhoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

require_once __DIR__ . '/../Helpers/TrabalhosHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->dono = mkUser('admin', 'chefe@ifpa.edu.br');
    $this->evento = mkEvento($this->dono);
    $this->avaliador = mkUser('user', 'avaliador@gmail.com');
    mkPapel($this->avaliador, $this->evento, 'avaliador');
    $this->autor = mkUser('user', 'autora@gmail.com');
    $this->trabalho = mkTrabalho($this->evento, $this->autor);
    $this->trabalho->update(['status' => 'em_avaliacao']);
    $this->avaliacao = app(TrabalhoWorkflow::class)->atribuirAvaliador($this->trabalho->fresh(), $this->avaliador, $this->dono);
});

/** Assuntos dos avisos genéricos enviados para um e-mail. */
function avisosPara(string $email): array
{
    return Mail::queued(MovimentacaoMail::class, fn ($m) => $m->hasTo($email))->map(fn ($m) => \Illuminate\Support\Str::before($m->d['assunto'], ' — '))->values()->all();
}

it('avisa o avaliador removido', function () {
    app(TrabalhoWorkflow::class)->removerAvaliacao($this->avaliacao, $this->dono);

    expect(avisosPara('avaliador@gmail.com'))->toBe(['Você não precisa mais avaliar este trabalho']);
});

it('avisa a gestão quando o avaliador recusa, com o motivo, sem avisar o autor', function () {
    app(AvaliacaoService::class)->recusar($this->avaliacao, $this->avaliador, 'Conflito de agenda');

    expect(avisosPara('chefe@ifpa.edu.br'))->toBe(['Avaliador recusou um trabalho'])
        ->and(avisosPara('autora@gmail.com'))->toBe([]);
    Mail::assertQueued(MovimentacaoMail::class, fn ($m) => $m->d['citacao'] === 'Conflito de agenda');
});

it('avisa o avaliador quando o trabalho volta para correção, sem revelar autores', function () {
    $this->avaliacao->update(['status' => 'concluida', 'nota_final' => 7]);
    $this->trabalho->update(['status' => 'avaliado']);

    app(AvaliacaoService::class)->decidir($this->trabalho->fresh(), $this->dono, 'aprovado_com_ressalvas', 'Revisar o método', now()->addDays(5));

    expect(avisosPara('avaliador@gmail.com'))->toBe(['Trabalho voltou ao autor para correção']);
    $m = Mail::queued(MovimentacaoMail::class, fn ($m) => $m->hasTo('avaliador@gmail.com'))->first();
    $html = $m->render();
    expect($html)->not->toContain('autora@gmail.com')->not->toContain($this->trabalho->autores->first()->nome_completo);
});
