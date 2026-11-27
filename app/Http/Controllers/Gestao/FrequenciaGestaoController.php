<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Frequencia;
use App\Models\Inscricao;
use App\Models\Participante;
use App\Rules\Cpf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Área do "Gestor de Frequência": exibir o QR Code das atividades e registrar presença à mão para quem não conseguiu.
 * Não dá acesso ao restante da administração nem permite alterar o cadastro de quem já existe.
 */
class FrequenciaGestaoController extends Controller
{
    public function index(Request $request)
    {
        $eventos = Evento::frequenciaGeridaPor($request->user())
            ->with(['atividades' => fn ($q) => $q->withCount('frequencias')->orderBy('data_inicio')])
            ->orderByDesc('data_inicio')->get();

        return view('gestao.frequencia.index', compact('eventos'));
    }

    public function qr(Atividade $atividade)
    {
        $this->autorizar($atividade);

        $link = route('frequencia.form', $atividade->token_frequencia);

        return view('gestao.frequencia.qr', [
            'atividade' => $atividade,
            'link' => $link,
            'qr' => (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(600)->margin(1)->generate($link),
            'total' => $atividade->frequencias()->count(),
        ]);
    }

    public function manual(Request $request, Atividade $atividade)
    {
        $this->autorizar($atividade);

        $busca = trim((string) $request->query('q'));
        $resultados = null;

        if (mb_strlen($busca) >= 3) {
            $digitos = preg_replace('/\D/', '', $busca);
            $consulta = Participante::with('turma.curso');

            if (strlen($digitos) === 11 && preg_match('/^[\d.\-\s]+$/', $busca)) {
                $consulta->where('cpf', Cpf::formatar($digitos));
            } else {
                $termo = '%' . addcslashes($busca, '%_\\') . '%';
                $consulta->where(fn ($q) => $q->where('nome_completo', 'like', $termo)->orWhere('email', 'like', $termo)->orWhere('matricula', 'like', $termo));
            }

            $resultados = $consulta->orderBy('nome_completo')->limit(15)->get();
        }

        return view('gestao.frequencia.manual', [
            'atividade' => $atividade,
            'busca' => $busca,
            'resultados' => $resultados,
            'presentes' => $resultados ? $atividade->frequencias()->whereIn('participante_id', $resultados->pluck('id'))->pluck('participante_id')->all() : [],
            'ultimas' => $atividade->frequencias()->with('participante')->latest('data_registro')->latest('id')->limit(20)->get(),
            'total' => $atividade->frequencias()->count(),
        ]);
    }

    /** Presença de quem já tem cadastro: um clique, sem alterar nada do cadastro. */
    public function registrar(Request $request, Atividade $atividade, Participante $participante)
    {
        $this->autorizar($atividade);

        return $this->voltar($atividade, $this->marcarPresenca($request, $atividade, $participante), $participante);
    }

    /** Presença de quem ainda não tem cadastro. Se o CPF já existir, usa o cadastro existente sem mudá-lo. */
    public function novo(Request $request, Atividade $atividade)
    {
        $this->autorizar($atividade);

        $request->merge(['cpf' => Cpf::formatar((string) $request->cpf) ?: $request->cpf, 'email' => mb_strtolower(trim((string) $request->email))]);

        $dados = $request->validate([
            'nome_completo' => ['required', 'string', 'min:3', 'max:255'],
            'cpf' => ['required', 'string', 'size:14', new Cpf],
            'email' => ['required', 'email:rfc', 'regex:/^[\x21-\x7E]+$/', 'max:255'],
            'tipo_vinculo' => ['required', 'in:aluno,servidor,externo'],
            'matricula' => ['required_unless:tipo_vinculo,externo', 'nullable', 'string', 'max:50'],
            'turma_id' => ['required_if:tipo_vinculo,aluno', 'nullable', 'exists:turmas,id'],
        ], [
            'email.regex' => 'O e-mail não pode ter acentos, espaços ou caracteres especiais.',
            'matricula.required_unless' => 'Informe a matrícula.',
            'turma_id.required_if' => 'Escolha o curso e a turma.',
        ]);

        $participante = Participante::where('cpf', $dados['cpf'])->first();
        $jaTinhaCadastro = $participante !== null;

        $participante ??= Participante::create([
            'nome_completo' => trim($dados['nome_completo']),
            'cpf' => $dados['cpf'],
            'email' => $dados['email'],
            'tipo_vinculo' => $dados['tipo_vinculo'],
            'matricula' => $dados['tipo_vinculo'] === 'externo' ? null : $dados['matricula'],
            'turma_id' => $dados['tipo_vinculo'] === 'aluno' ? $dados['turma_id'] : null,
        ]);

        $nova = $this->marcarPresenca($request, $atividade, $participante);

        return $this->voltar($atividade, $nova, $participante, $jaTinhaCadastro ? ' Este CPF já tinha cadastro, que foi mantido como estava.' : '');
    }

    private function marcarPresenca(Request $request, Atividade $atividade, Participante $participante): bool
    {
        $nova = DB::transaction(function () use ($atividade, $participante) {
            Inscricao::firstOrCreate(['evento_id' => $atividade->evento_id, 'participante_id' => $participante->id], ['data_inscricao' => now()]);

            return Frequencia::firstOrCreate(
                ['participante_id' => $participante->id, 'atividade_id' => $atividade->id],
                ['data_registro' => now(), 'hash_validacao' => (string) Str::uuid(), 'tipo_participacao' => 'ouvinte']
            )->wasRecentlyCreated;
        });

        if ($nova) {
            // Rastro de quem lançou à mão
            Log::info('Presença manual registrada', ['atividade' => $atividade->id, 'participante' => $participante->id, 'por' => $request->user()->id]);
        }

        return $nova;
    }

    private function voltar(Atividade $atividade, bool $nova, Participante $participante, string $extra = '')
    {
        return redirect()->route('gestao.frequencia.manual', $atividade)->with(
            $nova ? 'success' : 'warning',
            ($nova ? "Presença de {$participante->nome_completo} registrada." : "{$participante->nome_completo} já tinha presença nesta atividade.") . $extra
        );
    }

    private function autorizar(Atividade $atividade): void
    {
        abort_unless($atividade->evento, 404);
        $this->authorize('gerirFrequencia', $atividade->evento);
    }
}
