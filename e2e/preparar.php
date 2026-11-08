<?php

// Prepara dados para os testes de navegador, sem depender do estado deixado por quem usa o ambiente local:
// 3 trabalhos em triagem (sem avaliadores), 1 em triagem com 1 avaliador (1/2) e 1 em avaliação com avaliação pendente.
use App\Models\Evento;
use App\Models\User;
use App\Services\TrabalhoWorkflow;

$evento = Evento::where('slug', 'semana-de-tecnologia-2026')->firstOrFail();
$autor = User::where('email', 'autor@teste.com')->firstOrFail();
$gestor = User::where('email', 'gestor@teste.com')->firstOrFail();
$av = User::where('email', 'avaliador@teste.com')->firstOrFail();
$w = app(TrabalhoWorkflow::class);
$rodada = now()->format('His');

$novo = function (string $rotulo) use ($w, $evento, $autor, $rodada) {
    $t = $w->salvar($evento, $autor, [
        'titulo' => "E2E {$rotulo} {$rodada}", 'resumo' => 'Resumo de teste automatizado', 'palavras_chave' => 'a, b, c', 'tipo_trabalho' => 'pesquisa', 'modalidade_apresentacao' => 'oral',
        'autores' => [['nome_completo' => 'Autor Teste', 'cpf' => '111.444.777-35', 'email' => $autor->email, 'tipo_autor' => 'autor_principal', 'apresentador' => true]],
    ]);
    $w->submeter($t, $autor);
    $t->update(['status' => 'em_triagem']);

    return $t->fresh();
};

foreach (['triagem A', 'triagem B', 'triagem C'] as $r) { $novo($r); }
$w->atribuirAvaliador($novo('meio'), $av, $gestor);                       // 1/2: abaixo do mínimo

$pronto = $novo('avaliacao');                                            // 2/2 e em avaliação: gera avaliação pendente para o avaliador
$outro = User::where('id', '!=', $av->id)->whereIn('id', DB::table('evento_user')->where('evento_id', $evento->id)->where('role', 'avaliador')->pluck('user_id'))->first();
$w->atribuirAvaliador($pronto, $av, $gestor);
if ($outro) { $w->atribuirAvaliador($pronto, $outro, $gestor); }
$pronto->update(['status' => 'em_avaliacao']);

echo "preparado\n";
