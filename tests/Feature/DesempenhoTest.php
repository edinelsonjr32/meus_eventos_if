<?php

use App\Models\Anais;
use App\Models\TrabalhoConfig;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Detecta consultas repetidas (N+1): o número de consultas de cada tela deve ser o MESMO com poucos
 * e com muitos trabalhos. Se crescer com os dados, falta carregamento antecipado (eager loading).
 */
function consultas(callable $acao): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $acao();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

/** Adiciona N trabalhos completos: 3 autores, 2 avaliações concluídas com notas, apresentados, aprovados e com certificados. */
function popular($ctx, int $n): void
{
    static $seq = 0;
    for ($i = 0; $i < $n; $i++) {
        $seq++;
        $t = mkTrabalho($ctx->evento, $ctx->autorUser, [
            'titulo' => "Trabalho de carga {$seq}", 'tipo_trabalho' => ['pesquisa', 'extensao'][$seq % 2],
            'area_tematica_id' => $ctx->area->id,
            'autores' => [
                ['nome_completo' => "Autor {$seq}", 'cpf' => cpfValido($seq), 'email' => "a{$seq}@x.com", 'instituicao' => 'IFPA', 'categoria_autor' => 'discente', 'tipo_autor' => 'autor_principal', 'apresentador' => true],
                ['nome_completo' => "Coautor {$seq}", 'instituicao' => 'UFOPA', 'tipo_autor' => 'coautor'],
                ['nome_completo' => "Orientador {$seq}", 'instituicao' => 'IFPA', 'tipo_autor' => 'orientador'],
            ],
        ], 'aprovado');
        $t->update(['apresentado' => true, 'apresentado_em' => now(), 'autoriza_publicacao' => true]);

        foreach ([$ctx->av1, $ctx->av2] as $av) {
            $a = $t->avaliacoes()->create(['avaliador_id' => $av->id, 'rodada' => 1, 'status' => 'concluida', 'nota_final' => 8, 'recomendacao' => 'aprovar', 'parecer' => 'Parecer', 'concluida_em' => now()]);
            foreach ($ctx->evento->criterios as $c) {
                $a->notas()->create(['criterio_id' => $c->id, 'nota' => 8]);
            }
        }
        $path = "{$ctx->evento->id}/{$t->id}/v1.pdf";
        Storage::disk('trabalhos')->put($path, "%PDF-1.4\n%%EOF");
        $t->arquivos()->create(['versao' => 1, 'tipo' => 'original', 'path' => $path, 'nome_original' => 'x.pdf', 'mime' => 'application/pdf', 'tamanho' => 10, 'sha256' => str_repeat('a', 64), 'enviado_por' => $ctx->autorUser->id, 'ativo' => true]);
        app(\App\Services\CertificadoTrabalhoService::class)->emitir($t->fresh(), $ctx->gestor);
    }
}

/** CPF válido distinto para cada número (apenas para dados de carga). */
function cpfValido(int $n): string
{
    $d = str_split(str_pad((string) (100000000 + $n * 7919), 9, '0', STR_PAD_LEFT));
    for ($t = 9; $t < 11; $t++) {
        $s = 0;
        for ($i = 0; $i < $t; $i++) {
            $s += $d[$i] * (($t + 1) - $i);
        }
        $d[$t] = (($s * 10) % 11) % 10;
    }
    $c = implode('', $d);

    return substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9, 2);
}

beforeEach(function () {
    Mail::fake();
    Storage::fake('trabalhos');

    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->evento->update(['slug' => 'carga']);
    $this->area = $this->evento->areasTematicas()->create(['nome' => 'Área A', 'ordem' => 0]);
    $this->gestor = mkUser();
    mkPapel($this->gestor, $this->evento, 'gestor_trabalhos');
    $this->autorUser = mkUser();
    $this->av1 = mkUser(); $this->av2 = mkUser();
    mkPapel($this->av1, $this->evento, 'avaliador'); mkPapel($this->av2, $this->evento, 'avaliador');
    Anais::create(['evento_id' => $this->evento->id, 'titulo' => 'Anais', 'ano' => 2026, 'local_publicacao' => 'Óbidos', 'editora' => 'IFPA', 'status' => 'publicado', 'publicado_em' => now()]);
});

it('as telas principais não fazem consultas repetidas conforme os dados crescem (N+1)', function (string $nome, callable $abrir) {
    popular($this, 3);
    $pouco = consultas(fn () => $abrir($this)->assertSuccessful());

    popular($this, 25);   // passa de uma página (20 itens) e de 28 trabalhos no total
    $muito = consultas(fn () => $abrir($this)->assertSuccessful());

    // Pode até diminuir (a 1ª requisição carrega sessão/cache); não pode CRESCER com os dados
    expect($muito)->toBeLessThanOrEqual($pouco, "{$nome}: {$pouco} consultas com 3 trabalhos e {$muito} com 28 (cresce com os dados)");
})->with([
    'fila da gestão' => ['fila da gestão', fn ($c) => $c->actingAs($c->gestor)->get(route('gestao.trabalhos.index', $c->evento))],
    'fila com filtros' => ['fila filtrada', fn ($c) => $c->actingAs($c->gestor)->get(route('gestao.trabalhos.index', [$c->evento, 'q' => 'carga', 'tipo' => 'pesquisa']))],
    'ranking' => ['ranking', fn ($c) => $c->actingAs($c->gestor)->get(route('gestao.trabalhos.ranking', $c->evento))],
    'apresentações e certificados' => ['certificados', fn ($c) => $c->actingAs($c->gestor)->get(route('gestao.trabalhos.certificados.index', $c->evento))],
    'montagem dos Anais' => ['anais gestão', fn ($c) => $c->actingAs($c->gestor)->get(route('gestao.trabalhos.anais.edit', $c->evento))],
    'Anais públicos' => ['anais público', fn ($c) => test()->get(route('anais.index', 'carga'))],
    'Anais públicos filtrados' => ['anais filtrado', fn ($c) => test()->get(route('anais.index', ['carga', 'q' => 'carga', 'area' => $c->area->id]))],
    'Meus Trabalhos' => ['meus trabalhos', fn ($c) => $c->actingAs($c->autorUser)->get(route('trabalhos.index'))],
    'avaliações do avaliador' => ['avaliador', fn ($c) => $c->actingAs($c->av1)->get(route('avaliador.avaliacoes.index'))],
    'exportação de trabalhos' => ['export trabalhos', fn ($c) => $c->actingAs($c->gestor)->get(route('gestao.trabalhos.exportar', ['evento' => $c->evento, 'formato' => 'csv']))],
    'exportação do ranking' => ['export ranking', fn ($c) => $c->actingAs($c->gestor)->get(route('gestao.trabalhos.exportar-notas', ['evento' => $c->evento, 'relatorio' => 'resumo', 'formato' => 'csv']))],
    'exportação dos pareceres' => ['export pareceres', fn ($c) => $c->actingAs($c->gestor)->get(route('gestao.trabalhos.exportar-notas', ['evento' => $c->evento, 'relatorio' => 'detalhado', 'formato' => 'csv']))],
]);
