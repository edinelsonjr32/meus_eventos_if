<?php

namespace App\Services;

use App\Mail\CandidaturaDecididaMail;
use App\Models\ChamadaVoluntario;
use App\Models\DemandaVoluntario;
use App\Models\Equipe;
use App\Models\User;
use App\Models\Voluntario;
use Illuminate\Support\Facades\Mail;

/** Regras do voluntariado: inscrição, seleção, alocação em demandas e certificado de voluntário. */
class VoluntarioService
{
    public function __construct(private CertificadoEquipeService $certificados, private ParticipanteDaConta $contas)
    {
    }

    /** @throws \DomainException */
    public function inscrever(User $user, ChamadaVoluntario $chamada, array $dados): Voluntario
    {
        if (!$chamada->estaAberta()) {
            throw new \DomainException('Esta chamada não está recebendo inscrições no momento.');
        }
        $participante = $this->contas->daConta($user);
        if (!$participante) {
            throw new \DomainException('Complete seu cadastro (CPF) em "Minha Conta" antes de se inscrever como voluntário.');
        }

        $disponibilidade = $this->normalizarDisponibilidade($dados['disponibilidade'] ?? []);
        $diasValidos = $chamada->evento->diasDeVoluntariado()->all();
        $disponibilidade = array_intersect_key($disponibilidade, array_flip($diasValidos));
        if ($disponibilidade === []) {
            throw new \DomainException('Marque ao menos um dia e período em que você pode ajudar.');
        }

        $campos = [
            'motivacao' => $dados['motivacao'] ?? null, 'disponibilidade' => null, 'disponibilidade_dias' => $disponibilidade,
            'habilidades' => $dados['habilidades'] ?? null, 'telefone' => $dados['telefone'] ?? null,
        ];

        $existente = Voluntario::where('chamada_id', $chamada->id)->where('user_id', $user->id)->first();
        if ($existente) {
            if ($existente->status !== 'desistente') {
                throw new \DomainException('Você já está inscrito nesta chamada.');
            }
            $existente->update($campos + ['status' => 'inscrito', 'parecer' => null, 'decidido_por' => null, 'decidido_em' => null]);
            $this->avisarInscricao($existente);

            return $existente;
        }

        $voluntario = Voluntario::create($campos + ['chamada_id' => $chamada->id, 'evento_id' => $chamada->evento_id, 'user_id' => $user->id, 'participante_id' => $participante->id, 'status' => 'inscrito']);
        $this->avisarInscricao($voluntario);

        return $voluntario;
    }

    private function avisarInscricao(Voluntario $voluntario): void
    {
        Notificacoes::voluntario($voluntario, 'Inscrição de voluntário recebida', 'Recebemos sua inscrição como voluntário(a). A organização vai analisá-la e você será avisado(a) por e-mail do resultado.', 'ok');
    }

    /** Mantém só dias com períodos válidos: ["2026-11-12" => ["manha", "tarde"]]. */
    public function normalizarDisponibilidade(mixed $entrada): array
    {
        $saida = [];
        foreach ((array) $entrada as $dia => $periodos) {
            $validos = array_values(array_intersect(array_keys(Voluntario::PERIODOS), (array) $periodos));
            if ($validos && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dia)) {
                $saida[$dia] = $validos;
            }
        }
        ksort($saida);

        return $saida;
    }

    public function desistir(Voluntario $voluntario): void
    {
        $voluntario->demandas()->detach();
        $voluntario->update(['status' => 'desistente']);
        Notificacoes::voluntario($voluntario->fresh(), 'Inscrição de voluntário cancelada', 'Sua desistência foi registrada. Se mudar de ideia e a chamada ainda estiver aberta, é possível se inscrever novamente.', 'alerta');
    }

    /** @throws \DomainException */
    public function decidir(Voluntario $voluntario, User $gestor, string $status, ?string $parecer = null): Voluntario
    {
        if (!in_array($status, ['aprovado', 'lista_espera', 'reprovado', 'inscrito'], true)) {
            throw new \DomainException('Decisão inválida.');
        }

        $chamada = $voluntario->chamada;
        if ($status === 'aprovado' && $chamada->vagas) {
            $aprovados = Voluntario::where('chamada_id', $chamada->id)->where('status', 'aprovado')->where('id', '!=', $voluntario->id)->count();
            if ($aprovados >= $chamada->vagas) {
                throw new \DomainException("Todas as {$chamada->vagas} vaga(s) já foram preenchidas. Coloque na lista de espera ou aumente as vagas da chamada.");
            }
        }

        if ($status !== 'aprovado') {
            $voluntario->demandas()->detach();   // quem deixa de ser aprovado sai das demandas
        }

        $voluntario->update(['status' => $status, 'parecer' => filled($parecer) ? trim($parecer) : null, 'decidido_por' => $gestor->id, 'decidido_em' => now()]);

        if (in_array($status, ['aprovado', 'lista_espera', 'reprovado'], true)) {
            $voluntario->loadMissing('usuario', 'evento');
            if ($voluntario->usuario?->email) {
                Mail::to($voluntario->usuario->email)->queue(new CandidaturaDecididaMail(
                    'voluntario', $voluntario->evento->titulo, $voluntario->chamada->titulo, $status, $voluntario->statusRotulo(), $voluntario->parecer, route('voluntariado.meu'), null
                ));
            }
        }

        return $voluntario->fresh();
    }

    /** @throws \DomainException */
    public function alocar(Voluntario $voluntario, DemandaVoluntario $demanda, bool $ignorarConflito = false): void
    {
        if ($voluntario->status !== 'aprovado') {
            throw new \DomainException('Só voluntários aprovados podem ser vinculados a atividades.');
        }
        if ($voluntario->evento_id !== $demanda->evento_id) {
            throw new \DomainException('A demanda é de outro evento.');
        }
        if ($voluntario->demandas()->whereKey($demanda->id)->exists()) {
            throw new \DomainException('Este voluntário já está nesta atividade.');
        }
        if ($demanda->vagas !== null && $demanda->voluntarios()->count() >= $demanda->vagas) {
            throw new \DomainException('Todas as vagas desta atividade já estão preenchidas.');
        }

        if (!$ignorarConflito) {
            if ($faltas = $voluntario->faltaDisponibilidade($demanda)) {
                throw new \DomainException('Fora da disponibilidade informada pelo voluntário: ele não marcou ' . implode(', ', $faltas) . '. Marque "ignorar conflito" para vincular mesmo assim.');
            }
        }

        if (!$ignorarConflito && $demanda->data_inicio && $demanda->data_fim) {
            $choque = $voluntario->demandas()->where('data_inicio', '<', $demanda->data_fim)->where('data_fim', '>', $demanda->data_inicio)->first();
            if ($choque) {
                throw new \DomainException("Conflito de horário: o voluntário já está em \"{$choque->titulo}\" ({$choque->data_inicio->format('d/m H:i')} a {$choque->data_fim->format('H:i')}).");
            }
        }

        $voluntario->demandas()->attach($demanda->id);

        Notificacoes::voluntario($voluntario, 'Você foi vinculado(a) a uma atividade', 'A organização vinculou você a uma atividade. Confira os detalhes abaixo e, em caso de dúvida, procure o servidor responsável.', 'ok', Notificacoes::detalhesDaDemanda($demanda));
    }

    public function desalocar(Voluntario $voluntario, DemandaVoluntario $demanda): void
    {
        $removido = $voluntario->demandas()->detach($demanda->id);
        if ($removido) {
            Notificacoes::voluntario($voluntario, 'Você foi desvinculado(a) de uma atividade', 'A organização removeu seu vínculo com a atividade abaixo. Se tiver dúvidas, fale com o servidor responsável.', 'alerta', ['Atividade' => $demanda->titulo]);
        }
    }

    /**
     * Certificado de voluntário (usa o módulo de Equipe, função "Voluntário"). A carga horária é a soma das demandas,
     * ou o valor informado pela organização. @throws \DomainException
     */
    public function emitirCertificado(Voluntario $voluntario, ?User $gestor = null, ?int $cargaHoraria = null): Equipe
    {
        if ($voluntario->status !== 'aprovado') {
            throw new \DomainException('Só voluntários aprovados recebem certificado.');
        }
        $participante = $voluntario->participante ?: $this->contas->daConta($voluntario->usuario);
        if (!$participante) {
            throw new \DomainException('O voluntário ainda não tem cadastro de participante (CPF).');
        }

        $voluntario->loadMissing('demandas');
        $horas = $cargaHoraria ?: (int) ceil($voluntario->cargaHoraria());
        if ($horas < 1) {
            throw new \DomainException('Informe a carga horária (ou vincule o voluntário a atividades com carga horária).');
        }

        $detalhe = $voluntario->demandas->isEmpty()
            ? $voluntario->chamada->titulo
            : $voluntario->demandas->map(fn ($d) => $d->titulo . ($d->data_inicio ? ' (' . $d->data_inicio->format('d/m/Y') . ')' : ''))->implode('; ');

        $ja = Equipe::where('evento_id', $voluntario->evento_id)->where('participante_id', $participante->id)->where('funcao', 'Voluntário')->whereNotNull('certificado_emitido_em')->exists();
        $equipe = $this->certificados->emitirParaFuncao($voluntario->evento, $participante, 'Voluntário', $horas, $detalhe, $gestor);

        if (!$ja) {
            Notificacoes::voluntario($voluntario, 'Seu certificado de voluntário está disponível', 'O certificado do seu trabalho voluntário foi emitido. Baixe-o na sua área.', 'ok', ['Carga horária' => $horas . ' h'], 'Baixar meu certificado', route('voluntariado.certificado', $voluntario));
        }

        return $equipe;
    }

    /** Vínculo de equipe (certificado) deste voluntário, se existir. */
    public function certificadoDe(Voluntario $voluntario): ?Equipe
    {
        $participante = $voluntario->participante ?: $this->contas->daConta($voluntario->usuario);

        return $participante
            ? Equipe::where('evento_id', $voluntario->evento_id)->where('participante_id', $participante->id)->where('funcao', 'Voluntário')->first()
            : null;
    }
}
