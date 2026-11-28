<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\AtividadeEquipe;
use App\Models\Inscricao;
use App\Models\MinistranteEvento;
use App\Models\Participante;
use App\Models\Proposta;
use App\Models\User;
use App\Rules\Cpf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Quem ministra e quem ajuda em uma atividade (proponente, indicados na proposta e pessoas vinculadas),
 * a presença lançada pela organização e o certificado que ela libera.
 */
class EquipeDaAtividade
{
    public function __construct(private ParticipanteDaConta $contas, private PropostaService $propostas)
    {
    }

    /** @return Collection<int, object{chave:string,nome:string,funcao:string,participante:?Participante,registro:?AtividadeEquipe,impedimento:?string}> */
    public function membros(Atividade $atividade): Collection
    {
        $registros = AtividadeEquipe::with('participante')->where('atividade_id', $atividade->id)->get()->keyBy('participante_id');
        $lista = collect();
        $vistos = [];

        $incluir = function (string $chave, string $nome, string $funcao, ?Participante $p, ?string $impedimento) use (&$lista, &$vistos, $registros) {
            $marcas = array_filter([MinistranteEvento::chaveDe($nome), $p ? "id:{$p->id}" : null]);
            if (array_intersect($marcas, $vistos)) {
                return;
            }
            $vistos = array_merge($vistos, $marcas);
            $registro = $p ? $registros->get($p->id) : null;
            $lista->push((object) ['chave' => $chave, 'nome' => $nome, 'funcao' => $registro?->funcao ?? $funcao, 'participante' => $p, 'registro' => $registro, 'impedimento' => $p ? null : $impedimento]);
        };

        if ($proposta = $this->proposta($atividade)) {
            $p = $proposta->participante ?: ($proposta->proponente ? $this->contas->daConta($proposta->proponente) : null);
            $incluir('proponente', $p?->nome_completo ?: (string) $proposta->proponente?->name, 'ministrante', $p, 'Sem cadastro de participante (CPF).');

            foreach ($proposta->apoios as $apoio) {
                $p = $apoio->participante ?: (filled($apoio->cpf) ? Participante::where('cpf', Cpf::formatar($apoio->cpf))->first() : null);
                // quem ainda não tem cadastro é criado ao lançar a presença, desde que haja CPF e e-mail
                $falta = filled($apoio->cpf) && filled($apoio->email) ? null : 'Falta CPF ou e-mail na proposta.';
                $marcas = array_filter([MinistranteEvento::chaveDe($apoio->nome), $p ? "id:{$p->id}" : null]);
                if (array_intersect($marcas, $vistos)) {
                    continue;
                }
                $vistos = array_merge($vistos, $marcas);
                $registro = $p ? $registros->get($p->id) : null;
                $lista->push((object) ['chave' => "apoio:{$apoio->id}", 'nome' => $apoio->nome, 'funcao' => $apoio->funcao, 'participante' => $p, 'registro' => $registro, 'impedimento' => $falta]);
            }
        }

        foreach ($atividade->responsaveis()->get() as $v) {
            $p = $v->participante_id ? Participante::find($v->participante_id) : Participante::where('user_id', $v->user_id)->first();
            $incluir("resp:{$v->id}", $v->nome, 'ministrante', $p, 'Conta sem cadastro de participante (CPF): a pessoa precisa informar o CPF em Minha Conta.');
        }

        // quem teve presença lançada e depois saiu da equipe continua listado, para poder ser desfeito
        foreach ($registros as $r) {
            if (!$lista->contains(fn ($m) => $m->registro?->id === $r->id)) {
                $lista->push((object) ['chave' => "reg:{$r->id}", 'nome' => $r->participante->nome_completo, 'funcao' => $r->funcao, 'participante' => $r->participante, 'registro' => $r, 'impedimento' => null]);
            }
        }

        return $lista;
    }

    /** Lança a presença da pessoa (libera o certificado). @throws \DomainException */
    public function lancarPresenca(Atividade $atividade, string $chave, User $gestor): AtividadeEquipe
    {
        if ($atividade->data_inicio->isFuture()) {
            throw new \DomainException('A presença só pode ser lançada depois que a atividade começar (' . $atividade->data_inicio->format('d/m/Y H:i') . ').');
        }
        $membro = $this->membros($atividade)->firstWhere('chave', $chave);
        if (!$membro) {
            throw new \DomainException('Esta pessoa não faz parte da equipe da atividade.');
        }
        if ($membro->registro) {
            return $membro->registro;
        }
        if ($membro->impedimento) {
            throw new \DomainException("{$membro->nome}: {$membro->impedimento}");
        }

        $participante = $membro->participante;
        if (str_starts_with($chave, 'apoio:')) {
            $apoio = $this->proposta($atividade)->apoios->firstWhere('id', (int) Str::after($chave, ':'));
            if (!$apoio->noEvento()) {
                $participante = $this->propostas->adicionarApoioAoEvento($apoio, $gestor);
            }
        }
        Inscricao::firstOrCreate(['evento_id' => $atividade->evento_id, 'participante_id' => $participante->id], ['data_inscricao' => now()]);

        return AtividadeEquipe::firstOrCreate(
            ['atividade_id' => $atividade->id, 'participante_id' => $participante->id],
            ['funcao' => $membro->funcao, 'presenca_em' => now(), 'presenca_por' => $gestor->id, 'hash_validacao' => $this->novoHash()]
        );
    }

    /** Emite o certificado de quem já tem presença (idempotente). @throws \DomainException */
    public function emitir(AtividadeEquipe $registro): AtividadeEquipe
    {
        if (!$registro->atividade->emiteCertificado()) {
            throw new \DomainException('Esta atividade está marcada como "não gera certificado".');
        }
        if (!$registro->certificadoEmitido()) {
            $registro->update(['certificado_emitido_em' => now()]);
        }

        return $registro;
    }

    public function gerarPdf(AtividadeEquipe $registro)
    {
        $registro->loadMissing(['participante', 'atividade.evento.assinaturas']);
        $atividade = $registro->atividade;
        $evento = $atividade->evento;
        $p = $registro->participante;

        $corpo = sprintf(
            'Certificamos que <b>%s</b>, inscrito(a) sob o CPF nº <b>%s</b>, atuou como <b>%s</b> na atividade <b>%s</b> (%s) do evento <b>%s</b>, realizada em %s, com carga horária de <b>%s horas</b>.',
            e(mb_strtoupper($p->nome_completo)), e((string) $p->cpf), e(mb_strtolower($registro->funcaoRotulo())), e($atividade->titulo),
            e(mb_strtolower($atividade->tipoRotulo())), e($evento->titulo), e($atividade->data_inicio->format('d/m/Y')), e((string) (float) $atividade->carga_horaria)
        );

        return Pdf::loadView('certificados.modelo_oficial', [
            'textoCabecalho' => $evento->cert_cabecalho ?: "Ministério da Educação\nSecretaria de Educação Profissional e Tecnológica\nInstituto Federal do Pará - Campus Óbidos",
            'textoCorpo' => $corpo,
            'textoRodape' => (string) $evento->cert_rodape,
            'imgFundo' => $this->imagem($evento->caminho_fundo),
            'imgBrasao' => $this->imagem($evento->caminho_brasao),
            'assinaturas' => $evento->assinaturas->map(function ($a) {
                $a->img_base64 = $this->imagem($a->arquivo_assinatura);

                return $a;
            }),
            'hashCert' => $registro->hash_validacao,
            'urlValidacao' => route('certificado.atividade.validar', $registro->hash_validacao),
            'emitidoEm' => $registro->certificado_emitido_em,
        ])->setPaper('a4', 'landscape')->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'Arial', 'isHtml5ParserEnabled' => true]);
    }

    private function proposta(Atividade $atividade): ?Proposta
    {
        return Proposta::with(['apoios.participante', 'participante', 'proponente'])->where('atividade_id', $atividade->id)->first();
    }

    private function imagem(?string $caminho): ?string
    {
        if (!$caminho || !Storage::disk('public')->exists($caminho)) {
            return null;
        }
        $tipo = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));

        return 'data:image/' . ($tipo === 'jpg' ? 'jpeg' : $tipo) . ';base64,' . base64_encode(Storage::disk('public')->get($caminho));
    }

    private function novoHash(): string
    {
        do {
            $hash = 'A' . strtoupper(Str::random(19));
        } while (AtividadeEquipe::where('hash_validacao', $hash)->exists());

        return $hash;
    }
}
