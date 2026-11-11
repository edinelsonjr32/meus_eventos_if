<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\Turma;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestão dos cursos e turmas usados no cadastro de alunos (inscrição, credenciamento, frequência).
 * Cursos e turmas com alunos vinculados não podem ser excluídos: o vínculo do aluno com a turma seria perdido.
 */
class CursoController extends Controller
{
    public function index(Request $request)
    {
        $busca = trim((string) $request->input('q'));

        $cursos = Curso::with(['turmas' => fn ($q) => $q->withCount('participantes')->orderByDesc('ano')])
            ->when($busca !== '', fn ($q) => $q->where('nome', 'like', "%{$busca}%"))
            ->orderBy('nome')->get();

        $totais = [
            'cursos' => Curso::count(),
            'turmas' => Turma::count(),
            'alunos' => \App\Models\Participante::whereNotNull('turma_id')->count(),
        ];

        return view('admin.cursos.index', compact('cursos', 'totais', 'busca'));
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150', Rule::unique('cursos', 'nome')],
        ], ['nome.unique' => 'Já existe um curso com este nome.', 'nome.required' => 'Informe o nome do curso.']);

        $curso = Curso::create(['nome' => trim($dados['nome'])]);

        return redirect()->route('admin.cursos.index')->with('success', "Curso \"{$curso->nome}\" cadastrado. Agora adicione as turmas.");
    }

    public function update(Request $request, Curso $curso)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150', Rule::unique('cursos', 'nome')->ignore($curso->id)],
        ], ['nome.unique' => 'Já existe um curso com este nome.', 'nome.required' => 'Informe o nome do curso.']);

        $curso->update(['nome' => trim($dados['nome'])]);

        return redirect()->route('admin.cursos.index')->with('success', 'Curso atualizado.');
    }

    public function destroy(Curso $curso)
    {
        $alunos = \App\Models\Participante::whereIn('turma_id', $curso->turmas()->select('id'))->count();
        if ($alunos > 0) {
            return back()->with('error', "Não é possível excluir \"{$curso->nome}\": há {$alunos} aluno(s) vinculado(s) às suas turmas.");
        }

        $nome = $curso->nome;
        $curso->delete();   // as turmas (sem alunos) saem junto

        return redirect()->route('admin.cursos.index')->with('success', "Curso \"{$nome}\" excluído.");
    }

    public function storeTurma(Request $request, Curso $curso)
    {
        $dados = $request->validate([
            'ano' => ['required', 'string', 'max:20', Rule::unique('turmas', 'ano')->where('curso_id', $curso->id)],
        ], ['ano.unique' => 'Este curso já tem uma turma com este ano.', 'ano.required' => 'Informe o ano da turma.']);

        $curso->turmas()->create(['ano' => trim($dados['ano'])]);

        return redirect()->route('admin.cursos.index')->with('success', "Turma {$dados['ano']} adicionada a \"{$curso->nome}\".");
    }

    public function updateTurma(Request $request, Turma $turma)
    {
        $dados = $request->validate([
            'ano' => ['required', 'string', 'max:20', Rule::unique('turmas', 'ano')->where('curso_id', $turma->curso_id)->ignore($turma->id)],
        ], ['ano.unique' => 'Este curso já tem uma turma com este ano.', 'ano.required' => 'Informe o ano da turma.']);

        $turma->update(['ano' => trim($dados['ano'])]);

        return redirect()->route('admin.cursos.index')->with('success', 'Turma atualizada.');
    }

    public function destroyTurma(Turma $turma)
    {
        $alunos = $turma->participantes()->count();
        if ($alunos > 0) {
            return back()->with('error', "Não é possível excluir a turma {$turma->ano}: há {$alunos} aluno(s) vinculado(s).");
        }

        $turma->delete();

        return redirect()->route('admin.cursos.index')->with('success', 'Turma excluída.');
    }
}
