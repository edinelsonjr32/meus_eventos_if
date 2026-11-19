<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\SalaApresentacao;
use App\Models\Trabalho;
use App\Services\AgendaApresentacoes;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Agenda das apresentações orais: salas (links de videoconferência) e horário de cada trabalho, com aviso a autores e avaliadores. */
class AgendaApresentacaoController extends Controller
{
    public function __construct(private AgendaApresentacoes $agenda)
    {
    }

    public function index(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $todas = $request->boolean('todas');
        $trabalhos = $this->agenda->trabalhosDaAgenda($evento, $todas)->get();

        return view('gestao.trabalhos.agenda', [
            'evento' => $evento,
            'salas' => $evento->salasApresentacao()->withCount('trabalhos')->orderBy('nome')->get(),
            'trabalhos' => $trabalhos,
            'conflitos' => $this->agenda->idsEmConflito($trabalhos),
            'todas' => $todas,
            'duracaoPadrao' => 15,
        ]);
    }

    // ---------- Salas ----------

    public function salaSalvar(Request $request, Evento $evento, ?SalaApresentacao $sala = null)
    {
        $this->authorize('gerirTrabalhos', $evento);
        abort_if($sala && $sala->evento_id !== $evento->id, 404);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'min:2', 'max:120'],
            'link' => ['required', 'url:http,https', 'max:500'],
            'instrucoes' => ['nullable', 'string', 'max:2000'],
            'ativo' => ['nullable', 'boolean'],
        ], ['nome.required' => 'Informe o nome da sala.', 'link.required' => 'Informe o link da sala.', 'link.url' => 'O link deve começar com https:// (ex.: https://meet.google.com/abc-defg-hij).']);

        $existe = SalaApresentacao::where('evento_id', $evento->id)->where('nome_normalizado', SalaApresentacao::normalizar($dados['nome']))
            ->when($sala, fn ($q) => $q->where('id', '!=', $sala->id))->first();
        if ($existe) {
            throw ValidationException::withMessages(['nome' => "Já existe uma sala chamada \"{$existe->nome}\" neste evento."]);
        }

        $dados['ativo'] = $request->boolean('ativo', true);
        $sala ? $sala->update($dados) : $evento->salasApresentacao()->create($dados);

        return redirect()->route('gestao.trabalhos.agenda.index', $evento)->with('success', $sala ? 'Sala atualizada.' : 'Sala cadastrada.');
    }

    public function salaExcluir(Evento $evento, SalaApresentacao $sala)
    {
        $this->authorize('gerirTrabalhos', $evento);
        abort_unless($sala->evento_id === $evento->id, 404);

        if ($sala->trabalhos()->exists()) {
            return back()->with('error', 'Esta sala já tem apresentações agendadas. Desative-a (ou mude as apresentações de sala) em vez de excluir.');
        }
        $sala->delete();

        return back()->with('success', 'Sala excluída.');
    }

    // ---------- Horários ----------

    /** Programa vários trabalhos em sequência na mesma sala (o link se repete; os horários se encadeiam). */
    public function programar(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $dados = $request->validate([
            'trabalhos' => ['required', 'array', 'min:1', 'max:200'],
            'trabalhos.*' => ['integer'],
            'sala_id' => ['required', 'integer', Rule::exists('salas_apresentacao', 'id')->where('evento_id', $evento->id)->where('ativo', true)],
            'inicio' => ['required', 'date'],
            'duracao' => ['required', 'integer', 'min:1', 'max:480'],
            'intervalo' => ['nullable', 'integer', 'min:0', 'max:240'],
            'ignorar_conflito' => ['nullable', 'boolean'],
        ], [
            'trabalhos.required' => 'Marque ao menos um trabalho para programar.', 'sala_id.required' => 'Escolha a sala.', 'sala_id.exists' => 'Escolha uma sala ativa deste evento.',
            'inicio.required' => 'Informe o dia e a hora da primeira apresentação.', 'duracao.required' => 'Informe a duração de cada apresentação (minutos).',
        ]);

        // Mantém a ordem de apresentação pelo código do protocolo
        $trabalhos = $evento->trabalhos()->where('status', 'aprovado')->whereIn('id', $dados['trabalhos'])->with('evento')->orderBy('codigo')->get();
        if ($trabalhos->isEmpty()) {
            return back()->with('error', 'Nenhum dos trabalhos marcados está apto para apresentação.');
        }

        $r = $this->agenda->programar($trabalhos, (int) $dados['sala_id'], Carbon::parse($dados['inicio']), (int) $dados['duracao'], (int) ($dados['intervalo'] ?? 0), $request->boolean('ignorar_conflito'));
        if (!$r['ok']) {
            return back()->withInput()->with('error', 'A sala já está ocupada neste horário: ' . implode(' ', $r['conflitos']) . ' Mude o horário ou marque "ignorar conflito".');
        }

        $msg = "{$r['total']} apresentação(ões) programada(s). Quando estiver tudo certo, envie o aviso aos autores e avaliadores.";

        return back()->with('success', $msg)->with('warning', $r['avisos'] ? implode(' ', $r['avisos']) : null);
    }

    public function limpar(Evento $evento, Trabalho $trabalho)
    {
        $this->authorize('gerirTrabalhos', $evento);
        abort_unless($trabalho->evento_id === $evento->id, 404);

        $this->agenda->limpar($trabalho);

        return back()->with('success', "Horário de {$trabalho->codigo} removido.");
    }

    /** Envia sala e horário: aos selecionados ou a todos os que ainda não foram avisados (ou mudaram). */
    public function notificar(Request $request, Evento $evento)
    {
        $this->authorize('gerirTrabalhos', $evento);

        $request->validate(['trabalhos' => ['nullable', 'array', 'max:300'], 'trabalhos.*' => ['integer']]);
        $consulta = $evento->trabalhos()->where('status', 'aprovado')->whereNotNull('sala_apresentacao_id')->whereNotNull('apresentacao_inicio')->with(['sala', 'evento']);
        $trabalhos = $request->filled('trabalhos')
            ? $consulta->whereIn('id', $request->input('trabalhos'))->get()
            : $consulta->get()->filter(fn (Trabalho $t) => $t->agendaPendenteDeAviso());

        if ($trabalhos->isEmpty()) {
            return back()->with('error', 'Não há apresentações com sala e horário pendentes de aviso.');
        }

        $emails = 0;
        foreach ($trabalhos as $t) {
            $emails += $this->agenda->notificarPendente($t);
        }

        return back()->with('success', "Aviso enviado para {$trabalhos->count()} apresentação(ões) ({$emails} e-mail(s) para autores e avaliadores).");
    }
}
