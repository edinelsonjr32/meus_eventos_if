<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exports\FrequenciaGeralExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Evento;
use App\Models\Curso;

class RelatorioController extends Controller
{
    /**
     * Exibe a tela de filtros para exportação.
     */
    public function index()
    {
        $eventos = Evento::gerenciaveisPor(auth()->user())->orderBy('data_inicio', 'desc')->get();
        $cursos = Curso::orderBy('nome', 'asc')->get();
        $turmas = \App\Models\Turma::with('curso')->get()->sortBy(fn ($t) => mb_strtolower($t->curso->nome) . $t->ano)->values();

        return view('admin.relatorios.index', compact('eventos', 'cursos', 'turmas'));
    }

    /**
     * Gera e baixa o arquivo Excel/CSV.
     */
    public function exportar(Request $request)
    {
        // Validação básica dos filtros
        $request->validate([
            'evento_id' => 'nullable|exists:eventos,id',
            'curso_id' => 'nullable|exists:cursos,id',
            'turma_id' => 'nullable|exists:turmas,id',
            'data_de' => 'nullable|date',
            'data_ate' => 'nullable|date|after_or_equal:data_de',
            'vinculo' => 'nullable|in:aluno,todos',
        ], ['data_ate.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.']);

        $filtros = $request->only(['evento_id', 'curso_id', 'turma_id', 'data_de', 'data_ate', 'vinculo']);

        // Somente eventos que o usuário gerencia (com ou sem filtro de evento)
        $permitidos = Evento::gerenciaveisPor($request->user())->pluck('id')->all();
        if (!empty($filtros['evento_id']) && !in_array((int) $filtros['evento_id'], $permitidos, true)) {
            abort(403);
        }
        $filtros['eventos_permitidos'] = $permitidos;

        $dataHora = now()->format('Ymd_His');
        $evento = !empty($filtros['evento_id']) ? Evento::find($filtros['evento_id']) : null;
        $nomeArquivo = 'Frequencia_' . ($evento ? \Illuminate\Support\Str::slug($evento->titulo) . '_' : '') . "{$dataHora}.xlsx";

        return Excel::download(new FrequenciaGeralExport($filtros, $request->user()->name), $nomeArquivo);
    }
}
