<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Local;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Cadastro de locais do evento e mapa de distribuição das atividades por local e horário. */
class LocalController extends Controller
{
    public function index(Request $request, Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $locais = $evento->locais()->withCount(['atividades', 'demandas'])->get();
        $atividades = $evento->atividades()->orderBy('data_inicio')->get();

        // Dias com atividades (ou, se não houver, os dias do evento)
        $dias = $atividades->map(fn ($a) => $a->data_inicio->format('Y-m-d'))->unique()->values();
        if ($dias->isEmpty() && $evento->data_inicio) {
            $dias = collect(range(0, min(30, $evento->data_inicio->diffInDays($evento->data_fim ?? $evento->data_inicio))))->map(fn ($i) => $evento->data_inicio->copy()->addDays($i)->format('Y-m-d'));
        }
        $dia = $request->input('dia');
        $dia = $dias->contains($dia) ? $dia : $dias->first();

        return view('admin.locais.index', [
            'evento' => $evento, 'locais' => $locais, 'dias' => $dias, 'dia' => $dia,
            'mapa' => $dia ? $this->mapa($locais, $atividades->filter(fn ($a) => $a->data_inicio->format('Y-m-d') === $dia)) : null,
            'semLocal' => $atividades->whereNull('local_id')->count(),
            'tipos' => Local::TIPOS,
        ]);
    }

    public function store(Request $request, Evento $evento)
    {
        $this->authorize('gerenciar', $evento);
        $dados = $this->validar($request, $evento);

        $evento->locais()->create($dados);

        return redirect()->route('admin.locais.index', $evento)->with('success', 'Local cadastrado.');
    }

    public function update(Request $request, Evento $evento, Local $local)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($local->evento_id === $evento->id, 404);
        $dados = $this->validar($request, $evento, $local);

        $local->update($dados);

        // Os textos exibidos (programação, voluntários) acompanham o nome novo
        $rotulo = $local->fresh()->rotulo();
        $local->atividades()->update(['local' => $rotulo]);
        $local->demandas()->update(['local' => $rotulo]);

        return redirect()->route('admin.locais.index', $evento)->with('success', 'Local atualizado.');
    }

    public function destroy(Evento $evento, Local $local)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($local->evento_id === $evento->id, 404);

        if ($local->emUso()) {
            return back()->with('error', 'Este local já é usado por atividades. Desative-o em vez de excluir (ele deixa de aparecer nas listas de escolha, mas a programação continua igual).');
        }
        $local->delete();

        return back()->with('success', 'Local excluído.');
    }

    private function validar(Request $request, Evento $evento, ?Local $atual = null): array
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'min:2', 'max:120'],
            'bloco' => ['nullable', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(array_keys(Local::TIPOS))],
            'capacidade' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'equipamentos' => ['nullable', 'string', 'max:2000'],
            'ativo' => ['nullable', 'boolean'],
        ], [
            'nome.required' => 'Informe o nome do local.',
            'capacidade.min' => 'A capacidade deve ser de pelo menos 1 pessoa.',
        ]);

        // Unicidade sem diferenciar maiúsculas, acentos nem espaços repetidos ("Lab 1" = "lab  1" = "LAB 1")
        $existe = Local::where('evento_id', $evento->id)->where('nome_normalizado', Local::normalizar($dados['nome']))
            ->when($atual, fn ($q) => $q->where('id', '!=', $atual->id))->first();
        if ($existe) {
            throw \Illuminate\Validation\ValidationException::withMessages(['nome' => "Já existe um local chamado \"{$existe->nome}\" neste evento (maiúsculas, acentos e espaços não diferenciam locais)."]);
        }

        $dados['ativo'] = $request->boolean('ativo', true);

        return $dados;
    }

    /**
     * Monta a linha do tempo do dia: uma linha por local (mais "Sem local definido"), com as atividades em faixas
     * (raias) para que atividades simultâneas no mesmo local apareçam empilhadas e marcadas como conflito.
     */
    private function mapa($locais, $atividadesDoDia): array
    {
        if ($atividadesDoDia->isEmpty()) {
            return ['linhas' => [], 'h0' => 8, 'h1' => 18, 'horas' => range(8, 18)];
        }

        $h0 = (int) floor($atividadesDoDia->min(fn ($a) => $a->data_inicio->hour + $a->data_inicio->minute / 60));
        $h1 = (int) ceil($atividadesDoDia->max(fn ($a) => $a->data_fim->format('Y-m-d') === $a->data_inicio->format('Y-m-d') ? $a->data_fim->hour + $a->data_fim->minute / 60 : 24));
        $h1 = max($h1, $h0 + 1);
        $span = ($h1 - $h0) * 60;

        $linhas = [];
        $grupos = $atividadesDoDia->groupBy(fn ($a) => $a->local_id ?: 0);
        $ordem = $locais->filter(fn ($l) => $l->ativo || $grupos->has($l->id))->pluck('id')->all();
        foreach (array_merge($ordem, $grupos->has(0) ? [0] : []) as $id) {
            $local = $id ? $locais->firstWhere('id', $id) : null;
            $itens = ($grupos->get($id) ?? collect())->sortBy('data_inicio')->values();

            $raias = [];
            $barras = [];
            foreach ($itens as $a) {
                $ini = $a->data_inicio->hour * 60 + $a->data_inicio->minute;
                $fim = $a->data_fim->format('Y-m-d') === $a->data_inicio->format('Y-m-d') ? $a->data_fim->hour * 60 + $a->data_fim->minute : 24 * 60;
                $raia = 0;
                while (isset($raias[$raia]) && $raias[$raia] > $ini) {
                    $raia++;
                }
                $raias[$raia] = $fim;
                $barras[] = [
                    'atividade' => $a, 'raia' => $raia, 'esq' => max(0, ($ini - $h0 * 60) / $span * 100), 'larg' => max(2, ($fim - $ini) / $span * 100),
                    'conflito' => false,
                ];
            }
            // Conflito: sobreposição real entre duas atividades do mesmo local (a linha "sem local" não conta)
            if ($id) {
                foreach ($barras as $i => $b) {
                    foreach ($barras as $j => $o) {
                        if ($i !== $j && $b['atividade']->data_inicio < $o['atividade']->data_fim && $b['atividade']->data_fim > $o['atividade']->data_inicio) {
                            $barras[$i]['conflito'] = true;
                        }
                    }
                }
            }

            $linhas[] = ['local' => $local, 'barras' => $barras, 'raias' => max(1, count($raias)), 'horas' => round($itens->sum(fn ($a) => $a->data_inicio->diffInMinutes($a->data_fim)) / 60, 1), 'total' => $itens->count()];
        }

        return ['linhas' => $linhas, 'h0' => $h0, 'h1' => $h1, 'horas' => range($h0, $h1)];
    }
}
