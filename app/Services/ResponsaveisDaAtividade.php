<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\Evento;
use App\Models\MinistranteEvento;
use App\Models\Participante;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Responsáveis por uma atividade: pessoas cadastradas vinculadas mais nomes escritos à mão.
 * O campo "responsavel" da atividade continua sendo o texto exibido (programação, QR Code, site).
 */
class ResponsaveisDaAtividade
{
    private const LIMITE_BUSCA = 15;

    /** Pessoas que a busca encontra, no formato [{id, nome, detalhe}]. */
    public function buscar(User $quem, Evento $evento, string $termo): Collection
    {
        $like = '%' . addcslashes($termo, '%_\\') . '%';

        $participantes = $this->participantes($quem, $evento)
            ->where(fn ($w) => $w->where('nome_completo', 'like', $like)->orWhere('email', 'like', $like)->orWhere('matricula', 'like', $like))
            ->orderBy('nome_completo')->limit(self::LIMITE_BUSCA)->get(['id', 'nome_completo', 'email'])
            ->toBase()->map(fn ($p) => ['id' => "p:{$p->id}", 'nome' => $p->nome_completo, 'detalhe' => (string) $p->email]);

        $contas = $this->contasSemCadastro($quem, $termo)
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('name')->limit(self::LIMITE_BUSCA)->get(['id', 'name', 'email'])
            ->toBase()->map(fn ($u) => ['id' => "u:{$u->id}", 'nome' => $u->name, 'detalhe' => $u->email . ' · conta sem cadastro de participante']);

        return $participantes->merge($contas)->sortBy(fn ($p) => Str::lower(Str::ascii($p['nome'])))->take(self::LIMITE_BUSCA)->values();
    }

    /**
     * Transforma "p:12"/"u:7" em vínculos prontos para gravar. Referências que a pessoa não pode ver são ignoradas
     * (as que a atividade já tinha são mantidas).
     */
    public function resolver(User $quem, Evento $evento, array $referencias, ?Atividade $atividade = null): Collection
    {
        $atuais = $atividade ? $atividade->responsaveis->keyBy->referencia() : collect();

        return collect($referencias)->unique()->map(function (string $ref) use ($quem, $evento, $atuais) {
            if ($atual = $atuais->get($ref)) {
                return ['participante_id' => $atual->participante_id, 'user_id' => $atual->user_id, 'nome' => $atual->nome];
            }
            [$tipo, $id] = explode(':', $ref) + [1 => 0];

            if ($tipo === 'p' && ($p = $this->participantes($quem, $evento)->find((int) $id))) {
                return ['participante_id' => $p->id, 'user_id' => null, 'nome' => $this->grafia($p->nome_completo)];
            }
            if ($tipo === 'u' && ($u = User::where('email', 'not like', '%@anonimizado.invalid')->find((int) $id)) && $this->ehGeral($quem)) {
                return ['participante_id' => null, 'user_id' => $u->id, 'nome' => $this->grafia($u->name)];
            }

            return null;
        })->filter()->values();
    }

    /** Junta listas de nomes sem repetir a mesma pessoa. */
    public static function juntar(iterable ...$listas): string
    {
        return collect($listas)->flatMap(fn ($l) => collect($l))->map(fn ($n) => trim((string) $n))->filter()
            ->unique(fn ($n) => MinistranteEvento::chaveDe($n))->implode(', ');
    }

    /** Parte do texto que não corresponde a nenhuma pessoa vinculada (o campo "outros nomes" da edição). */
    public function outrosNomes(Atividade $atividade): string
    {
        $texto = (string) $atividade->responsavel;
        if ($atividade->responsaveis->isEmpty()) {
            return $texto;
        }
        $vinculados = $atividade->responsaveis->map(fn ($r) => MinistranteEvento::chaveDe($r->nome))->all();

        return collect(preg_split('/[,;]+/', $texto))->map(fn ($n) => trim($n))->filter()
            ->reject(fn ($n) => in_array(MinistranteEvento::chaveDe($n), $vinculados, true))->implode(', ');
    }

    public function gravar(Atividade $atividade, Collection $vinculos): void
    {
        DB::transaction(function () use ($atividade, $vinculos) {
            $atividade->responsaveis()->delete();
            $vinculos->each(fn (array $v) => $atividade->responsaveis()->create($v));
        });
    }

    private function grafia(string $nome): string
    {
        return Str::limit(MinistrantesDoEvento::capitalizar(Str::squish($nome)), 160, '');
    }

    private function participantes(User $quem, Evento $evento)
    {
        return Participante::query()->where('cpf', 'not like', 'ANON-%')
            ->when(!$this->ehGeral($quem), fn ($q) => $q->whereIn('id', DB::table('inscricoes')->where('evento_id', $evento->id)->select('participante_id')));
    }

    private function contasSemCadastro(User $quem, string $termo)
    {
        return User::query()->where('email', 'not like', '%@anonimizado.invalid')
            ->whereNotIn('id', Participante::query()->whereNotNull('user_id')->select('user_id'))
            ->when(!$this->ehGeral($quem), fn ($q) => $q->where('email', $termo));
    }

    /** Contas fictícias de teste não enxergam o cadastro geral (mesma regra da busca de equipe). */
    private function ehGeral(User $quem): bool
    {
        $dominios = array_filter(array_map('trim', explode(',', mb_strtolower((string) config('mail.bloquear_dominios')))));
        $dominio = mb_strtolower(substr(strrchr($quem->email, '@') ?: '', 1));

        return $quem->can('superadmin') || !in_array($dominio, $dominios, true);
    }
}
