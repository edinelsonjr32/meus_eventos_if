<?php

use App\Models\CriterioAvaliacao;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use App\Models\User;
use App\Services\TrabalhoWorkflow;
use Illuminate\Support\Facades\DB;

/* Helpers compartilhados pelos testes do módulo de trabalhos (gestão e avaliação). */

function mkUser(string $role = 'user', ?string $email = null): User
{
    $u = User::factory()->create($email ? ['email' => $email] : []);
    $u->forceFill(['role' => $role])->save();

    return $u;
}

function mkPapel(User $u, Evento $e, string $papel): void
{
    DB::table('evento_user')->insert(['evento_id' => $e->id, 'user_id' => $u->id, 'role' => $papel, 'created_at' => now(), 'updated_at' => now()]);
}

function mkEvento(User $dono): Evento
{
    $e = Evento::create(['titulo' => 'Evento ' . uniqid(), 'slug' => 'e-' . uniqid(), 'data_inicio' => now(), 'data_fim' => now()->addDays(3), 'local' => 'X', 'criado_por' => $dono->id]);
    TrabalhoConfig::create(['evento_id' => $e->id, 'ativo' => true, 'submissao_inicio' => now()->subDay(), 'submissao_fim' => now()->addDays(5), 'avaliadores_por_trabalho' => 2]);

    // Critérios padrão (2 critérios: pesos 1 e 2, escala 10)
    foreach ([['Conteúdo', 1], ['Metodologia', 2]] as $i => [$nome, $peso]) {
        CriterioAvaliacao::create(['evento_id' => $e->id, 'nome' => $nome, 'peso' => $peso, 'nota_max' => 10, 'ordem' => $i]);
    }

    return $e;
}

/** Cria e submete um trabalho pelo serviço (sem passar pelo formulário). */
function mkTrabalho(Evento $e, User $autor, array $extra = [], string $status = 'submetido'): Trabalho
{
    $w = app(TrabalhoWorkflow::class);
    $t = $w->salvar($e, $autor, array_merge([
        'titulo' => 'Trabalho ' . uniqid(), 'resumo' => 'Resumo', 'palavras_chave' => 'a, b, c', 'tipo_trabalho' => 'pesquisa',
        'modalidade_apresentacao' => 'oral',
        'autores' => [['nome_completo' => 'Autor ' . uniqid(), 'cpf' => '111.444.777-35', 'email' => $autor->email, 'tipo_autor' => 'autor_principal', 'apresentador' => true]],
    ], $extra));
    $w->submeter($t, $autor);

    if ($status !== 'submetido') {
        $t->update(['status' => $status]);
    }

    return $t->fresh();
}

