<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\CertificadoAvaliador;
use App\Models\Evento;
use App\Models\User;
use App\Services\CertificadoAvaliadorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CertificadoAvaliadorGestaoController extends Controller
{
    public function __construct(private CertificadoAvaliadorService $servico)
    {
    }

    public function index(Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        return view('gestao.trabalhos.certificados-avaliadores', ['evento' => $evento, 'linhas' => $this->servico->avaliadores($evento)]);
    }

    public function emitir(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $d = $request->validate([
            'todos' => ['nullable', 'boolean'],
            'user_id' => ['required_without:todos', 'integer', 'exists:users,id'],
            'fase' => ['required_without:todos', Rule::in(array_keys(CertificadoAvaliador::FASES))],
            'carga_horaria' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
        $horas = isset($d['carga_horaria']) ? (int) $d['carga_horaria'] : null;

        if ($request->boolean('todos')) {
            $n = $this->servico->emitirTodos($evento, $horas, $request->user());

            return back()->with('success', $n ? "{$n} certificado(s) de avaliador emitido(s)." : 'Não há certificados novos para emitir.');
        }

        try {
            $c = $this->servico->emitir($evento, User::findOrFail($d['user_id']), $d['fase'], $horas, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Certificado de {$c->avaliador->name} emitido.");
    }

    public function revogar(Evento $evento, CertificadoAvaliador $certificado)
    {
        $this->authorize('gerirTrabalhos', $evento);
        abort_unless($certificado->evento_id === $evento->id, 404);

        $certificado->update(['revogado_em' => now()]);

        return back()->with('success', 'Certificado revogado.');
    }
}
