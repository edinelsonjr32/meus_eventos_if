<?php

namespace App\Services;

use App\Models\AtividadeEquipe;
use App\Models\CertificadoAvaliador;
use App\Models\CertificadoPremiacao;
use App\Models\CertificadoTrabalho;
use App\Models\User;
use Illuminate\Support\Collection;

/** Certificados além dos de participação: ministrante/ajudante, autoria, premiação e avaliação de trabalhos. */
class MeusCertificados
{
    /** @return Collection<int, object{titulo:string,descricao:string,url:string}> */
    public function para(User $user): Collection
    {
        $participante = $user->participante;
        $minhaAutoria = fn ($q) => $q->where(fn ($w) => $w->where('email', $user->email)
            ->when($participante, fn ($x) => $x->orWhere('participante_id', $participante->id)->when($participante->cpf, fn ($y) => $y->orWhere('cpf', $participante->cpf))));

        $itens = collect();

        AtividadeEquipe::daConta($user)->with('atividade.evento')->latest('presenca_em')->get()
            ->filter(fn ($r) => $r->atividade?->evento && $r->atividade->emiteCertificado())
            ->each(fn ($r) => $itens->push((object) [
                'titulo' => $r->atividade->titulo,
                'descricao' => $r->funcaoRotulo() . ' · ' . $r->atividade->evento->titulo . ' · ' . (float) $r->atividade->carga_horaria . 'h',
                'url' => route('certificado.atividade.meu', $r),
            ]));

        CertificadoTrabalho::whereNull('revogado_em')->whereHas('autor', $minhaAutoria)->with(['trabalho.evento', 'autor'])->latest('emitido_em')->get()
            ->filter(fn ($c) => $c->trabalho?->evento)
            ->each(fn ($c) => $itens->push((object) [
                'titulo' => $c->trabalho->titulo,
                'descricao' => $c->funcaoRotulo() . ' · ' . $c->trabalho->evento->titulo,
                'url' => route('certificado.trabalho.download', $c->hash_validacao),
            ]));

        CertificadoPremiacao::whereNull('revogado_em')->whereHas('autor', $minhaAutoria)->with(['premiacao.trabalho.evento.trabalhoConfig'])->latest('emitido_em')->get()
            ->filter(fn ($c) => $c->premiacao?->trabalho?->evento?->trabalhoConfig?->premiacao_divulgada_em)   // só depois de divulgada
            ->each(fn ($c) => $itens->push((object) [
                'titulo' => $c->premiacao->trabalho->titulo,
                'descricao' => 'Premiação · ' . $c->premiacao->trabalho->evento->titulo,
                'url' => route('certificado.premiacao.download', $c->hash_validacao),
            ]));

        CertificadoAvaliador::where('user_id', $user->id)->whereNull('revogado_em')->with('evento')->latest('emitido_em')->get()
            ->filter(fn ($c) => $c->evento)
            ->each(fn ($c) => $itens->push((object) [
                'titulo' => 'Avaliador(a) de trabalhos',
                'descricao' => $c->faseRotulo() . ' · ' . $c->evento->titulo . ' · ' . $c->quantidade . ' trabalho(s)',
                'url' => route('certificado.avaliador.download', $c->hash_validacao),
            ]));

        return $itens;
    }
}
