<?php

namespace App\Services;

use App\Mail\CandidaturaDecididaMail;
use App\Models\Atividade;
use App\Models\Local;
use App\Support\HtmlSeguro;
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

        $proposta = DB::transaction(function () use ($user, $chamada, $dados, $apoios, $participante) {
            $proposta = Proposta::create($this->campos($dados) + [
                'chamada_id' => $chamada->id, 'evento_id' => $chamada->evento_id, 'user_id' => $user->id, 'participante_id' => $participante->id, 'status' => 'enviada',
            ]);
            $this->gravarApoios($proposta, $apoios);

            return $proposta;
        });

        Notificacoes::proposta($proposta, 'Proposta recebida', 'Recebemos sua proposta. A organização vai analisá-la e você será avisado(a) por e-mail a cada novidade.', 'ok');

        return $proposta;
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

        $atualizada = $proposta->fresh();
        Notificacoes::proposta($atualizada, 'Proposta atualizada', 'Recebemos as alterações da sua proposta' . ($proposta->wasChanged('status') || $atualizada->status === 'enviada' ? ' e ela voltou para a análise da organização.' : '.'), 'info');

        return $atualizada;
    }

    /** @throws \DomainException */
    public function excluir(Proposta $proposta): void
    {
        if (!$proposta->excluivel()) {
            throw new \DomainException($proposta->atividade_id || $proposta->status === 'aprovada'
                ? 'Esta proposta já foi aprovada e está na programação: fale com a organização para cancelá-la.'
                : 'Retire a proposta antes de excluí-la.');
        }
        $proposta->delete();   // apoios saem junto
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
        Notificacoes::proposta($proposta->fresh(), 'Proposta retirada', 'Sua proposta foi retirada e não segue mais para análise.', 'alerta');
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
        } elseif ($decisao === 'em_analise') {
            Notificacoes::proposta($proposta, 'Sua proposta está em análise', 'A organização começou a analisar a sua proposta. Você será avisado(a) da decisão por e-mail.', 'info');
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
            if ($faltas = $proposta->faltaDisponibilidade($inicio, $fim)) {
                throw new \DomainException('Fora da disponibilidade informada pelo proponente: ele não marcou ' . implode(', ', $faltas) . '. Marque "aprovar mesmo com conflito" para confirmar assim mesmo.');
            }
            app(LocalService::class)->garantirLivre($proposta->evento_id, $local->id, $inicio, $fim, $proposta->atividade_id);
            app(LocalService::class)->garantirCapacidade($local->id, (int) $d['vagas']);
        }

        $campos = [
            'titulo' => $proposta->titulo, 'descricao' => HtmlSeguro::paraTexto($proposta->resumo), 'tipo' => $proposta->tipo, 'responsavel' => $this->responsavelDaAtividade($proposta),
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

    /** Muda a atribuição de uma pessoa (organização) e atualiza os responsáveis na programação. */
    public function definirFuncao(PropostaApoio $apoio, string $funcao): void
    {
        $apoio->update(['funcao' => $funcao]);
        $proposta = $apoio->proposta;
        if ($atividade = $proposta->atividade) {
            $nomes = collect(preg_split('/[,;]+/', (string) $atividade->responsavel))->map(fn ($n) => trim($n))->filter();
            $chave = \App\Models\MinistranteEvento::chaveDe($apoio->nome);
            $nomes = $funcao === 'ministrante'
                ? $nomes->push($apoio->nome)
                : $nomes->reject(fn ($n) => \App\Models\MinistranteEvento::chaveDe($n) === $chave);
            $atividade->update(['responsavel' => \Illuminate\Support\Str::limit(ResponsaveisDaAtividade::juntar($nomes), 250) ?: null]);
        }
    }

    /**
     * Certificado de ministrante: soma as atividades concluídas em que a pessoa ministrou no evento, como proponente
     * ou como ministrante indicada na proposta (uma certificação por pessoa).
     *
     * @throws \DomainException
     */
    public function emitirCertificadoMinistrante(Evento $evento, User $user, ?User $gestor = null): Equipe
    {
        $participante = $this->contas->daConta($user);
        if (!$participante) {
            throw new \DomainException('Esta pessoa ainda não tem cadastro de participante (CPF).');
        }

        return $this->certificarMinistrante($evento, $participante, $gestor);
    }

    /** @throws \DomainException */
    public function certificarMinistrante(Evento $evento, Participante $participante, ?User $gestor = null): Equipe
    {
        $concluidas = Proposta::with('atividade')->where('evento_id', $evento->id)->where('status', 'aprovada')
            ->where(fn ($q) => $q
                ->when($participante->user_id, fn ($w) => $w->where('user_id', $participante->user_id), fn ($w) => $w->where('participante_id', $participante->id))
                ->orWhereHas('apoios', fn ($a) => $a->where('funcao', 'ministrante')->where(fn ($w) => $w->where('participante_id', $participante->id)->orWhere('cpf', $participante->cpf))))
            ->get()
            ->filter(fn (Proposta $p) => $p->atividadeConcluida());

        if ($concluidas->isEmpty()) {
            throw new \DomainException('O certificado fica disponível depois que a atividade terminar.');
        }

        $horas = (int) round($concluidas->sum(fn ($p) => $p->atividade->carga_horaria));
        $detalhe = $concluidas->map(fn ($p) => $p->tipoRotulo() . ' "' . $p->titulo . '" (' . $p->atividade->data_inicio->format('d/m/Y') . ')')->implode('; ');

        return $this->certificados->emitirParaFuncao($evento, $participante, 'Ministrante', $horas, $detalhe, $gestor);
    }

    /**
     * Certificados dos demais ministrantes de uma proposta concluída (quem ainda não está no evento é incluído).
     *
     * @return array{0:int,1:string[]} emitidos e avisos
     */
    public function certificarCoministrantes(Proposta $proposta, User $gestor): array
    {
        $emitidos = 0;
        $avisos = [];
        foreach ($proposta->apoios()->where('funcao', 'ministrante')->get() as $apoio) {
            try {
                $participante = $apoio->noEvento() ? $apoio->participante : $this->adicionarApoioAoEvento($apoio, $gestor);
                $this->certificarMinistrante($proposta->evento, $participante, $gestor);
                $emitidos++;
            } catch (\DomainException $e) {
                $avisos[] = "{$apoio->nome}: {$e->getMessage()}";
            }
        }

        return [$emitidos, $avisos];
    }

    /** Ministrantes da proposta mais as pessoas que a organização vinculou à atividade. */
    private function responsavelDaAtividade(Proposta $proposta): string
    {
        $vinculados = $proposta->atividade_id ? \App\Models\AtividadeResponsavel::where('atividade_id', $proposta->atividade_id)->orderBy('id')->pluck('nome') : [];

        return \Illuminate\Support\Str::limit(ResponsaveisDaAtividade::juntar(explode(', ', $proposta->nomesDosMinistrantes()), $vinculados), 250);
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
            'tipo' => $d['tipo'], 'titulo' => trim($d['titulo']), 'resumo' => HtmlSeguro::limparSimples($d['resumo']) ?? trim($d['resumo']),
            'objetivos' => HtmlSeguro::limparSimples($d['objetivos'] ?? null), 'publico_alvo' => $d['publico_alvo'] ?? null, 'conteudo_programatico' => HtmlSeguro::limparSimples($d['conteudo_programatico'] ?? null), 'metodologia' => HtmlSeguro::limparSimples($d['metodologia'] ?? null),
            'carga_horaria_prevista' => (int) $d['carga_horaria_prevista'], 'vagas_sugeridas' => (int) $d['vagas_sugeridas'],
            'requer_laboratorio' => (bool) ($d['requer_laboratorio'] ?? false), 'espaco_solicitado' => $d['espaco_solicitado'] ?? null,
            'equipamentos_solicitados' => HtmlSeguro::limparSimples($d['equipamentos_solicitados'] ?? null), 'observacoes' => HtmlSeguro::limparSimples($d['observacoes'] ?? null),
            'pre_requisitos' => HtmlSeguro::limparSimples($d['pre_requisitos'] ?? null), 'materiais_participante' => HtmlSeguro::limparSimples($d['materiais_participante'] ?? null),
            'minicurriculo' => HtmlSeguro::limparSimples($d['minicurriculo'] ?? null), 'contato_whatsapp' => filled($d['contato_whatsapp'] ?? null) ? trim($d['contato_whatsapp']) : null,
        ] + (array_key_exists('disponibilidade', $d) ? ['disponibilidade_dias' => \App\Support\Disponibilidade::normalizar($d['disponibilidade']) ?: null] : []);
    }

    /** Substitui a lista de apoios (preserva os já adicionados ao evento). */
    private function gravarApoios(Proposta $proposta, array $apoios): void
    {
        $proposta->apoios()->whereNull('adicionado_ao_evento_em')->delete();
        foreach (collect($apoios)->filter(fn ($a) => filled($a['nome'] ?? null)) as $a) {
            $proposta->apoios()->create([
                'nome' => trim($a['nome']), 'email' => filled($a['email'] ?? null) ? mb_strtolower(trim($a['email'])) : null,
                'cpf' => filled($a['cpf'] ?? null) ? \App\Rules\Cpf::formatar($a['cpf']) : null,
                'funcao' => ($a['funcao'] ?? null) === 'ministrante' ? 'ministrante' : 'ajudante',
            ]);
        }
    }
}
