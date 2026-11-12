<?php

namespace App\Services;

use App\Mail\CandidaturaDecididaMail;
use App\Models\Atividade;
use App\Models\Local;
use App\Models\ChamadaProposta;
use App\Models\Equipe;
use App\Models\Evento;
use App\Models\Inscricao;
use App\Models\Participante;
use App\Models\Proposta;
use App\Models\PropostaApoio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Regras das propostas de minicurso/oficina: envio, análise, programação, apoios e certificado de ministrante. */
class PropostaService
{
    public function __construct(private CertificadoEquipeService $certificados, private ParticipanteDaConta $contas)
    {
    }

    /** @throws \DomainException */
    public function enviar(User $user, ChamadaProposta $chamada, array $dados, array $apoios = []): Proposta
    {
        if (!$chamada->estaAberta()) {
            throw new \DomainException('Esta chamada não está recebendo propostas no momento.');
        }
        $participante = $this->contas->daConta($user);
        if (!$participante) {
            throw new \DomainException('Complete seu cadastro (CPF) em "Minha Conta" antes de enviar uma proposta.');
        }
        if (!array_key_exists($dados['tipo'], $chamada->tiposAceitos())) {
            throw new \DomainException('Tipo de atividade não aceito nesta chamada.');
        }

        $ativas = Proposta::where('chamada_id', $chamada->id)->where('user_id', $user->id)->whereNotIn('status', ['retirada', 'reprovada'])->count();
        if ($chamada->max_por_usuario && $ativas >= $chamada->max_por_usuario) {
            throw new \DomainException("Você atingiu o limite de {$chamada->max_por_usuario} proposta(s) nesta chamada.");
        }

        $duplicada = Proposta::where('chamada_id', $chamada->id)->where('user_id', $user->id)->whereNotIn('status', ['retirada', 'reprovada'])
            ->whereRaw('LOWER(titulo) = ?', [mb_strtolower(trim($dados['titulo']))])->exists();
        if ($duplicada) {
            throw new \DomainException('Você já enviou uma proposta com este título nesta chamada.');
        }

        return DB::transaction(function () use ($user, $chamada, $dados, $apoios, $participante) {
            $proposta = Proposta::create($this->campos($dados) + [
                'chamada_id' => $chamada->id, 'evento_id' => $chamada->evento_id, 'user_id' => $user->id, 'participante_id' => $participante->id, 'status' => 'enviada',
            ]);
            $this->gravarApoios($proposta, $apoios);

            return $proposta;
        });
    }

    /** @throws \DomainException */
    public function atualizar(Proposta $proposta, array $dados, array $apoios = []): Proposta
    {
        if (!$proposta->editavel()) {
            throw new \DomainException('Esta proposta não pode mais ser editada.');
        }
        // Enquanto a organização não pediu ajustes, só dá para editar com a chamada aberta
        if ($proposta->status !== 'ajustes' && !$proposta->chamada->estaAberta()) {
            throw new \DomainException('A chamada foi encerrada: a proposta não pode mais ser editada.');
        }
        if (!array_key_exists($dados['tipo'], $proposta->chamada->tiposAceitos())) {
            throw new \DomainException('Tipo de atividade não aceito nesta chamada.');
        }

        DB::transaction(function () use ($proposta, $dados, $apoios) {
            // Reenvio depois de "ajustes solicitados" volta para a fila da organização
            $proposta->update($this->campos($dados) + ($proposta->status === 'ajustes' ? ['status' => 'enviada'] : []));
            $this->gravarApoios($proposta, $apoios);
        });

        return $proposta->fresh();
    }

    public function retirar(Proposta $proposta): void
    {
        if ($proposta->status === 'aprovada') {
            throw new \DomainException('Proposta já aprovada: fale com a organização para cancelar.');
        }
        if (in_array($proposta->status, ['retirada', 'reprovada'], true)) {
            throw new \DomainException('Esta proposta já está encerrada.');
        }
        $proposta->update(['status' => 'retirada']);
    }

    /**
     * Decisão da organização. $decisao: em_analise | ajustes | reprovada | aprovada.
     * Aprovar exige os dados da programação (data/hora, local, capacidade...) e cria a atividade no evento.
     *
     * @throws \DomainException
     */
    public function decidir(Proposta $proposta, User $gestor, string $decisao, array $dados = []): Proposta
    {
        if (!in_array($decisao, ['em_analise', 'ajustes', 'reprovada', 'aprovada'], true)) {
            throw new \DomainException('Decisão inválida.');
        }
        if ($proposta->status === 'retirada') {
            throw new \DomainException('Esta proposta foi retirada pelo proponente e não pode mais ser decidida.');
        }
        if (in_array($decisao, ['ajustes', 'reprovada'], true) && blank($dados['parecer'] ?? null)) {
            throw new \DomainException('Escreva a mensagem para o proponente.');
        }

        DB::transaction(function () use ($proposta, $gestor, $decisao, $dados) {
            if ($decisao === 'aprovada') {
                $atividade = $this->vincularAtividade($proposta, $dados);
                $proposta->atividade_id = $atividade->id;
            } elseif ($proposta->atividade_id) {
                $this->desvincularAtividade($proposta);
                $proposta->atividade_id = null;
            }

            $proposta->fill([
                'status' => $decisao,
                'parecer' => filled($dados['parecer'] ?? null) ? trim($dados['parecer']) : ($decisao === 'aprovada' ? $proposta->parecer : null),
                'decidido_por' => $gestor->id, 'decidido_em' => now(),
            ])->save();
        });

        $proposta->refresh();
        if (in_array($decisao, ['ajustes', 'reprovada', 'aprovada'], true)) {
            $this->avisar($proposta);
        }

        return $proposta;
    }

    /** Cria (ou atualiza) a atividade da programação a partir da proposta aprovada. */
    private function vincularAtividade(Proposta $proposta, array $d): Atividade
    {
        foreach (['data_inicio', 'data_fim', 'local_id', 'vagas', 'carga_horaria'] as $obrigatorio) {
            if (blank($d[$obrigatorio] ?? null)) {
                throw new \DomainException('Informe data/hora de início e fim, o local, a capacidade máxima e carga horária para aprovar.');
            }
        }

        $inicio = \Illuminate\Support\Carbon::parse($d['data_inicio']);
        $fim = \Illuminate\Support\Carbon::parse($d['data_fim']);
        if ($fim->lte($inicio)) {
            throw new \DomainException('O horário de término deve ser depois do início.');
        }

        $local = Local::where('evento_id', $proposta->evento_id)->find($d['local_id']);
        if (!$local) {
            throw new \DomainException('Escolha um local cadastrado para este evento.');
        }

        if (empty($d['ignorar_conflito'])) {
            app(LocalService::class)->garantirLivre($proposta->evento_id, $local->id, $inicio, $fim, $proposta->atividade_id);
            app(LocalService::class)->garantirCapacidade($local->id, (int) $d['vagas']);
        }

        $proponente = $proposta->participante?->nome_completo ?: $proposta->proponente->name;
        $campos = [
            'titulo' => $proposta->titulo, 'descricao' => $proposta->resumo, 'tipo' => $proposta->tipo, 'responsavel' => $proponente,
            'local_id' => $local->id, 'local' => $local->rotulo(), 'espaco' => null, 'equipamentos' => $d['equipamentos'] ?? null, 'observacoes_organizacao' => $d['observacoes_organizacao'] ?? null,
            'carga_horaria' => (int) $d['carga_horaria'], 'vagas' => (int) $d['vagas'], 'data_inicio' => $inicio, 'data_fim' => $fim, 'proposta_id' => $proposta->id,
        ];

        $atividade = $proposta->atividade_id ? Atividade::find($proposta->atividade_id) : null;
        if ($atividade) {
            $inscritos = $atividade->inscritos()->count();
            if ((int) $d['vagas'] < $inscritos) {
                throw new \DomainException("A capacidade não pode ser menor que o número de inscritos ({$inscritos}).");
            }
            $atividade->update($campos);

            return $atividade;
        }

        do {
            $token = Str::random(10);
        } while (Atividade::where('token_frequencia', $token)->exists());

        return Atividade::create($campos + ['evento_id' => $proposta->evento_id, 'token_frequencia' => $token]);
    }

    /** Se a proposta deixa de ser aprovada, a atividade criada some da programação (só se ninguém se inscreveu nem registrou presença). */
    private function desvincularAtividade(Proposta $proposta): void
    {
        $atividade = Atividade::find($proposta->atividade_id);
        if (!$atividade) {
            return;
        }
        if ($atividade->inscritos()->exists() || $atividade->frequencias()->exists()) {
            throw new \DomainException('A atividade já tem inscritos ou presenças: não é possível desfazer a aprovação.');
        }
        $atividade->delete();
    }

    /** Pessoa de apoio vira participante inscrito no evento (cria o cadastro se necessário). @throws \DomainException */
    public function adicionarApoioAoEvento(PropostaApoio $apoio, User $gestor): Participante
    {
        $proposta = $apoio->proposta;
        if ($apoio->noEvento()) {
            throw new \DomainException('Esta pessoa já foi adicionada ao evento.');
        }

        $cpf = \App\Rules\Cpf::formatar((string) $apoio->cpf);
        if (blank($cpf) || blank($apoio->email)) {
            throw new \DomainException('Falta o CPF ou o e-mail da pessoa de apoio. Edite a proposta (ou peça ao proponente) antes de adicioná-la.');
        }

        return DB::transaction(function () use ($apoio, $proposta, $cpf) {
            $participante = Participante::where('cpf', $cpf)->first()
                ?? Participante::create(['nome_completo' => $apoio->nome, 'cpf' => $cpf, 'email' => mb_strtolower($apoio->email), 'tipo_vinculo' => 'externo']);

            Inscricao::firstOrCreate(['evento_id' => $proposta->evento_id, 'participante_id' => $participante->id], ['data_inscricao' => now()]);
            $apoio->update(['participante_id' => $participante->id, 'adicionado_ao_evento_em' => now()]);

            return $participante;
        });
    }

    /**
     * Certificado de ministrante: soma as atividades já concluídas do proponente no evento (uma certificação por pessoa).
     *
     * @throws \DomainException
     */
    public function emitirCertificadoMinistrante(Evento $evento, User $proponente, ?User $gestor = null): Equipe
    {
        $participante = $this->contas->daConta($proponente);
        if (!$participante) {
            throw new \DomainException('O proponente ainda não tem cadastro de participante (CPF).');
        }

        $concluidas = Proposta::with('atividade')->where('evento_id', $evento->id)->where('user_id', $proponente->id)->where('status', 'aprovada')->get()
            ->filter(fn (Proposta $p) => $p->atividadeConcluida());

        if ($concluidas->isEmpty()) {
            throw new \DomainException('O certificado fica disponível depois que a atividade terminar.');
        }

        $horas = (int) round($concluidas->sum(fn ($p) => $p->atividade->carga_horaria));
        $detalhe = $concluidas->map(fn ($p) => $p->tipoRotulo() . ' "' . $p->titulo . '" (' . $p->atividade->data_inicio->format('d/m/Y') . ')')->implode('; ');

        return $this->certificados->emitirParaFuncao($evento, $participante, 'Ministrante', $horas, $detalhe, $gestor);
    }

    private function avisar(Proposta $proposta): void
    {
        $proposta->loadMissing('proponente', 'evento');
        if ($proposta->proponente?->email) {
            Mail::to($proposta->proponente->email)->queue(new CandidaturaDecididaMail(
                'proposta', $proposta->evento->titulo, $proposta->titulo, $proposta->status, $proposta->statusRotulo(), $proposta->parecer, route('propostas.minhas'),
                $proposta->status === 'aprovada' && $proposta->atividade ? $proposta->atividade->data_inicio->format('d/m/Y \à\s H:i') . ' · ' . ($proposta->atividade->local ?? '') : null
            ));
        }
    }

    private function campos(array $d): array
    {
        return [
            'tipo' => $d['tipo'], 'titulo' => trim($d['titulo']), 'resumo' => trim($d['resumo']),
            'objetivos' => $d['objetivos'] ?? null, 'publico_alvo' => $d['publico_alvo'] ?? null, 'conteudo_programatico' => $d['conteudo_programatico'] ?? null, 'metodologia' => $d['metodologia'] ?? null,
            'carga_horaria_prevista' => (int) $d['carga_horaria_prevista'], 'vagas_sugeridas' => (int) $d['vagas_sugeridas'],
            'requer_laboratorio' => (bool) ($d['requer_laboratorio'] ?? false), 'espaco_solicitado' => $d['espaco_solicitado'] ?? null,
            'equipamentos_solicitados' => $d['equipamentos_solicitados'] ?? null, 'observacoes' => $d['observacoes'] ?? null,
        ];
    }

    /** Substitui a lista de apoios (preserva os já adicionados ao evento). */
    private function gravarApoios(Proposta $proposta, array $apoios): void
    {
        $proposta->apoios()->whereNull('adicionado_ao_evento_em')->delete();
        foreach (collect($apoios)->filter(fn ($a) => filled($a['nome'] ?? null)) as $a) {
            $proposta->apoios()->create([
                'nome' => trim($a['nome']), 'email' => filled($a['email'] ?? null) ? mb_strtolower(trim($a['email'])) : null,
                'cpf' => filled($a['cpf'] ?? null) ? \App\Rules\Cpf::formatar($a['cpf']) : null,
            ]);
        }
    }
}
