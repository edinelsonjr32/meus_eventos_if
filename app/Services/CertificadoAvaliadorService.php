<?php

namespace App\Services;

use App\Models\CertificadoAvaliador;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Certificados de quem avaliou trabalhos. Só avaliações concluídas contam. O certificado informa apenas a
 * quantidade: títulos e autores ficam de fora para não quebrar o anonimato da avaliação.
 */
class CertificadoAvaliadorService
{
    private const TABELAS = ['escrita' => 'trabalho_avaliacoes', 'apresentacao' => 'apresentacao_avaliacoes'];

    /** Avaliadores do evento por fase, com o que concluíram, o que falta e o certificado (se houver). */
    public function avaliadores(Evento $evento): Collection
    {
        $certificados = CertificadoAvaliador::where('evento_id', $evento->id)->get()->keyBy(fn ($c) => "{$c->fase}:{$c->user_id}");
        $linhas = collect();

        foreach (self::TABELAS as $fase => $tabela) {
            $contagens = DB::table($tabela)->join('trabalhos', 'trabalhos.id', '=', "{$tabela}.trabalho_id")
                ->where('trabalhos.evento_id', $evento->id)
                ->groupBy("{$tabela}.avaliador_id")
                ->selectRaw("{$tabela}.avaliador_id as user_id")
                // trabalho avaliado em mais de uma rodada conta uma vez só
                ->selectRaw("count(distinct case when {$tabela}.status = 'concluida' then {$tabela}.trabalho_id end) as concluidas")
                ->selectRaw("sum(case when {$tabela}.status in ('pendente', 'em_andamento') then 1 else 0 end) as pendentes")
                ->get()->keyBy('user_id');

            foreach (User::whereIn('id', $contagens->keys())->where('email', 'not like', '%@anonimizado.invalid')->get() as $u) {
                $linhas->push((object) [
                    'usuario' => $u, 'fase' => $fase,
                    'concluidas' => (int) $contagens[$u->id]->concluidas, 'pendentes' => (int) $contagens[$u->id]->pendentes,
                    'certificado' => $certificados->get("{$fase}:{$u->id}"),
                ]);
            }
        }

        return $linhas->sortBy(fn ($l) => Str::lower(Str::ascii($l->usuario->name)) . $l->fase)->values();
    }

    /** Emite ou atualiza o certificado (mantém o código enquanto não for revogado). @throws \DomainException */
    public function emitir(Evento $evento, User $avaliador, string $fase, ?int $cargaHoraria, User $gestor): CertificadoAvaliador
    {
        $linha = $this->avaliadores($evento)->first(fn ($l) => $l->usuario->id === $avaliador->id && $l->fase === $fase);
        if (!$linha || $linha->concluidas < 1) {
            throw new \DomainException("{$avaliador->name}: nenhuma avaliação concluída nesta fase.");
        }

        $certificado = $linha->certificado ?? new CertificadoAvaliador(['evento_id' => $evento->id, 'user_id' => $avaliador->id, 'fase' => $fase]);
        if (!$certificado->exists || $certificado->revogado()) {
            $certificado->fill(['hash_validacao' => $this->novoHash(), 'emitido_em' => now(), 'revogado_em' => null]);
        }
        $certificado->fill(['quantidade' => $linha->concluidas, 'carga_horaria' => $cargaHoraria ?: $certificado->carga_horaria, 'emitido_por' => $gestor->id])->save();

        return $certificado;
    }

    /** Emite para quem concluiu algo e ainda não tem certificado válido. */
    public function emitirTodos(Evento $evento, ?int $cargaHoraria, User $gestor): int
    {
        return $this->avaliadores($evento)
            ->filter(fn ($l) => $l->concluidas > 0 && (!$l->certificado || $l->certificado->revogado()))
            ->each(fn ($l) => $this->emitir($evento, $l->usuario, $l->fase, $cargaHoraria, $gestor))
            ->count();
    }

    public function gerarPdf(CertificadoAvaliador $c)
    {
        $c->loadMissing(['avaliador', 'evento.assinaturas']);
        $evento = $c->evento;
        $participante = Participante::where('user_id', $c->user_id)->first();
        $nome = $participante?->nome_completo ?: $c->avaliador->name;

        $corpo = sprintf(
            'Certificamos que <b>%s</b>%s atuou como <b>avaliador(a)</b> de trabalhos na fase de <b>%s</b> do evento <b>%s</b>, realizado em %s, tendo avaliado %d %s%s.',
            e(mb_strtoupper($nome)),
            $participante?->cpf ? ', inscrito(a) sob o CPF nº <b>' . e($participante->cpf) . '</b>,' : '',
            $c->fase === 'escrita' ? 'avaliação escrita' : 'apresentação',
            e($evento->titulo), e($evento->data_inicio->format('d/m/Y')),
            $c->quantidade, $c->quantidade === 1 ? 'trabalho' : 'trabalhos',
            $c->carga_horaria ? ", com carga horária de <b>{$c->carga_horaria} horas</b>" : ''
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
            'hashCert' => $c->hash_validacao,
            'urlValidacao' => route('certificado.avaliador.validar', $c->hash_validacao),
            'emitidoEm' => $c->emitido_em,
        ])->setPaper('a4', 'landscape')->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'Arial', 'isHtml5ParserEnabled' => true]);
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
            $hash = 'V' . strtoupper(Str::random(19));
        } while (CertificadoAvaliador::where('hash_validacao', $hash)->exists());

        return $hash;
    }
}
