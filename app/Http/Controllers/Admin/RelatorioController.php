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
        $eventos = Evento::orderBy('data_inicio', 'desc')->get();
        $cursos = Curso::orderBy('nome', 'asc')->get();

        return view('admin.relatorios.index', compact('eventos', 'cursos'));
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
        ]);

        $filtros = $request->only(['evento_id', 'curso_id']);

        $dataHora = now()->format('Ymd_His');
        $nomeArquivo = "Relatorio_Frequencia_Geral_{$dataHora}.xlsx";

        // Chama a classe de exportação com os filtros
        return Excel::download(new FrequenciaGeralExport($filtros), $nomeArquivo);
    }
}
