<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Equipe;
use App\Models\Evento;
use App\Models\Participante;
use App\Services\CertificadoEquipeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EquipeController extends Controller
{
    public function __construct(private CertificadoEquipeService $certificados)
    {
    }

    public function index(Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $membros = $evento->equipe()->with('participante')->get()->sortBy(fn ($m) => mb_strtolower($m->participante->nome_completo))->values();

        return view('admin.equipes.index', [
            'evento' => $evento,
            'membros' => $membros,
            'funcoes' => Equipe::FUNCOES,
            'corpoModelo' => $evento->cert_equipe_corpo ?: CertificadoEquipeService::CORPO_PADRAO,
        ]);
    }

    public function store(Request $request, Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $dados = $request->validate([
            'cpf_busca' => 'required|string',
            'funcao' => ['required', Rule::in(Equipe::FUNCOES)],
            'carga_horaria' => 'nullable|integer|min:1|max:1000',
        ]);

        $termo = $dados['cpf_busca'];
        $cpfLimpo = preg_replace('/\D/', '', $termo);

        $participante = Participante::where('cpf', $termo)->orWhere('cpf', $cpfLimpo)->first();
        if (!$participante) {
            return back()->withInput()->with('error', 'Participante não encontrado! Ele precisa se cadastrar no sistema antes de ser adicionado à equipe.');
        }

        if (Equipe::where('evento_id', $evento->id)->where('participante_id', $participante->id)->exists()) {
            return back()->with('warning', 'Esta pessoa já faz parte da equipe deste evento.');
        }

        Equipe::create([
            'evento_id' => $evento->id,
            'participante_id' => $participante->id,
            'funcao' => $dados['funcao'],
            'carga_horaria_trabalho' => filled($dados['carga_horaria'] ?? null) ? (int) $dados['carga_horaria'] : Equipe::CARGA_PADRAO,
        ]);

        return back()->with('success', "{$participante->nome_completo} adicionado à equipe com sucesso!");
    }

    /** Edita função e carga horária; o certificado já emitido passa a refletir os novos dados. */
    public function update(Request $request, Equipe $equipe)
    {
        $this->authorize('gerenciar', $equipe->evento);

        $dados = $request->validate([
            'funcao' => ['required', Rule::in(Equipe::FUNCOES)],
            'carga_horaria' => 'required|integer|min:1|max:1000',
        ]);

        $equipe->update(['funcao' => $dados['funcao'], 'carga_horaria_trabalho' => (int) $dados['carga_horaria']]);

        return back()->with('success', 'Membro atualizado.' . ($equipe->certificadoEmitido() ? ' O certificado emitido já reflete a alteração.' : ''));
    }

    public function destroy(Equipe $equipe)
    {
        $this->authorize('gerenciar', $equipe->evento);

        $equipe->delete();

        return back()->with('success', 'Membro removido da equipe.');
    }

    public function emitir(Equipe $equipe)
    {
        $this->authorize('gerenciar', $equipe->evento);

        try {
            $novo = $this->certificados->emitir($equipe, auth()->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($novo ? 'success' : 'warning', $novo ? 'Certificado emitido.' : 'O certificado deste membro já havia sido emitido.');
    }

    public function emitirTodos(Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        [$emitidos, $ignorados] = $this->certificados->emitirEvento($evento, auth()->user());

        $resp = back()->with($emitidos ? 'success' : 'warning', $emitidos ? "{$emitidos} certificado(s) emitido(s)." : 'Nenhum certificado novo a emitir.');

        return $ignorados ? $resp->with('erros_lote', $ignorados) : $resp;
    }

    public function revogar(Equipe $equipe)
    {
        $this->authorize('gerenciar', $equipe->evento);
        $this->certificados->revogar($equipe);

        return back()->with('success', 'Certificado revogado: o código deixa de validar. Você pode emitir novamente.');
    }

    public function certificado(Equipe $equipe)
    {
        $this->authorize('gerenciar', $equipe->evento);
        abort_unless($equipe->certificadoEmitido(), 404, 'Certificado ainda não emitido.');

        return $this->certificados->gerarPdf($equipe)->stream($this->certificados->nomeArquivo($equipe));
    }

    public function modelo(Request $request, Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $dados = $request->validate(['cert_equipe_corpo' => 'nullable|string|max:3000']);
        $evento->update(['cert_equipe_corpo' => filled($dados['cert_equipe_corpo'] ?? null) ? $dados['cert_equipe_corpo'] : null]);

        return back()->with('success', 'Texto do certificado da equipe salvo.');
    }
}
