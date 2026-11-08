<?php

namespace App\Services\Lgpd;

use App\Models\LgpdRegistro;
use App\Models\Participante;
use App\Models\Trabalho;
use App\Models\TrabalhoAutor;
use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Atendimento aos direitos do titular (LGPD): acesso/portabilidade (exportar) e eliminação/anonimização.
 *
 * Anonimizar substitui os dados pessoais por marcadores e preserva os registros acadêmicos e estatísticos
 * (trabalhos, notas, presenças) sem identificar a pessoa. Certificados emitidos em nome do titular são
 * revogados, porque deixam de identificar alguém.
 */
class TitularService
{
    /** Identificador informado: e-mail ou CPF (com ou sem máscara). */
    public static function normalizar(string $identificador): array
    {
        $id = trim($identificador);

        if (filter_var($id, FILTER_VALIDATE_EMAIL)) {
            return ['email' => mb_strtolower($id), 'cpf' => null];
        }

        $cpf = Cpf::formatar($id);

        return ['email' => null, 'cpf' => Cpf::valido($cpf) ? $cpf : null];
    }

    /**
     * Reúne tudo o que se refere ao titular, ligando e-mail e CPF entre contas, participantes e autorias.
     *
     * @return array{emails: Collection, cpfs: Collection, usuarios: Collection, participantes: Collection, autorias: Collection}
     */
    public function localizar(string $identificador): array
    {
        ['email' => $email, 'cpf' => $cpf] = self::normalizar($identificador);

        $emails = collect($email ? [$email] : []);
        $cpfs = collect($cpf ? [$cpf] : []);

        // Expande: um CPF leva aos e-mails dele e vice-versa (até estabilizar)
        do {
            $antes = $emails->count() + $cpfs->count();

            $participantes = Participante::query()->where(fn ($q) => $q->whereIn('email', $emails)->orWhereIn('cpf', $cpfs))->get();
            $usuarios = User::query()->where(fn ($q) => $q->whereIn('email', $emails)->orWhereIn('id', $participantes->pluck('user_id')->filter()))->get();
            $autorias = TrabalhoAutor::query()->where(fn ($q) => $q->whereIn('email', $emails)->orWhereIn('cpf', $cpfs)->orWhereIn('participante_id', $participantes->pluck('id')))->get();

            $emails = $emails->merge($participantes->pluck('email'))->merge($usuarios->pluck('email'))->merge($autorias->pluck('email'))->filter()->map(fn ($e) => mb_strtolower($e))->unique()->values();
            $cpfs = $cpfs->merge($participantes->pluck('cpf'))->merge($autorias->pluck('cpf'))->filter()->unique()->values();
        } while ($emails->count() + $cpfs->count() !== $antes);

        return compact('emails', 'cpfs', 'usuarios', 'participantes', 'autorias');
    }

    public function encontrou(array $alvo): bool
    {
        return $alvo['usuarios']->isNotEmpty() || $alvo['participantes']->isNotEmpty() || $alvo['autorias']->isNotEmpty();
    }

    /** Dados do titular em formato estruturado (direito de acesso e portabilidade). */
    public function exportar(string $identificador, ?User $executor = null): array
    {
        $alvo = $this->localizar($identificador);

        $dados = [
            'gerado_em' => now()->toIso8601String(),
            'identificadores' => ['emails' => $alvo['emails']->all(), 'cpfs' => $alvo['cpfs']->all()],
            'contas' => $alvo['usuarios']->map(fn (User $u) => $u->only(['id', 'name', 'email', 'role', 'email_verified_at', 'created_at']))->values()->all(),
            'participantes' => $alvo['participantes']->map(fn (Participante $p) => $p->load(['turma.curso', 'inscricoes.evento', 'frequencias.atividade.evento'])->only(['id', 'nome_completo', 'cpf', 'email', 'tipo_vinculo', 'matricula'])
                + [
                    'turma' => $p->turma ? "{$p->turma->curso->nome} - {$p->turma->ano}" : null,
                    'inscricoes' => $p->inscricoes->map(fn ($i) => ['evento' => $i->evento->titulo, 'inscrito_em' => optional($i->data_inscricao)->toIso8601String(), 'checkin' => optional($i->checkin_at)->toIso8601String()])->all(),
                    'presencas' => $p->frequencias->map(fn ($f) => ['evento' => $f->atividade->evento->titulo, 'atividade' => $f->atividade->titulo, 'registrada_em' => optional($f->data_registro)->toIso8601String()])->all(),
                ])->values()->all(),
            'trabalhos_como_autor' => Trabalho::query()->with(['evento', 'autores', 'certificados'])
                ->where(fn ($q) => $q->whereIn('id', $alvo['autorias']->pluck('trabalho_id'))->orWhereIn('submetido_por', $alvo['usuarios']->pluck('id')))
                ->get()->map(fn (Trabalho $t) => [
                    'protocolo' => $t->codigo, 'titulo' => $t->titulo, 'evento' => $t->evento->titulo, 'status' => $t->statusRotulo(),
                    'submetido_em' => optional($t->submetido_em)->toIso8601String(),
                    'seus_dados_na_autoria' => $t->autores->filter(fn ($a) => $alvo['autorias']->contains('id', $a->id))->map(fn ($a) => $a->only(['nome_completo', 'cpf', 'email', 'instituicao', 'tipo_autor', 'categoria_autor']))->values()->all(),
                ])->values()->all(),
            'avaliacoes_como_avaliador' => DB::table('trabalho_avaliacoes')->whereIn('avaliador_id', $alvo['usuarios']->pluck('id'))
                ->get(['trabalho_id', 'rodada', 'status', 'nota_final', 'recomendacao', 'concluida_em'])->map(fn ($r) => (array) $r)->all(),
            'avaliacoes_de_apresentacao_como_avaliador' => DB::table('apresentacao_avaliacoes')->whereIn('avaliador_id', $alvo['usuarios']->pluck('id'))
                ->get(['trabalho_id', 'status', 'nota_final', 'concluida_em'])->map(fn ($r) => (array) $r)->all(),
            'papeis_em_eventos' => DB::table('evento_user')->whereIn('user_id', $alvo['usuarios']->pluck('id'))->get(['evento_id', 'role'])->map(fn ($r) => (array) $r)->all(),
        ];

        $this->registrar('exportacao', $identificador, $executor, ['contas' => count($dados['contas']), 'participantes' => count($dados['participantes']), 'trabalhos' => count($dados['trabalhos_como_autor'])]);

        return $dados;
    }

    /**
     * Anonimiza o titular (contas, participantes e autorias). Retorna as contagens do que foi afetado.
     *
     * @return array<string, int>
     */
    public function anonimizar(string $identificador, ?User $executor = null, string $acao = 'anonimizacao'): array
    {
        $alvo = $this->localizar($identificador);

        $resumo = DB::transaction(function () use ($alvo) {
            $resumo = ['contas' => 0, 'participantes' => 0, 'autorias' => 0, 'certificados_revogados' => 0];

            foreach ($alvo['autorias'] as $autor) {
                $resumo['certificados_revogados'] += $autor->certificado()->whereNull('revogado_em')
                    ->update(['revogado_em' => now(), 'revogado_motivo' => 'Dados do titular anonimizados (LGPD)']);

                $autor->update([
                    'nome_completo' => 'Titular anonimizado', 'cpf' => null, 'email' => null, 'telefone' => null,
                    'lattes' => null, 'orcid' => null, 'participante_id' => null,
                ]);
                $resumo['autorias']++;
            }

            foreach ($alvo['participantes'] as $p) {
                // CPF é único e obrigatório: recebe um marcador exclusivo (14 caracteres)
                $p->update([
                    'nome_completo' => 'Titular anonimizado', 'email' => "anonimo-{$p->id}@anonimizado.invalid",
                    'cpf' => 'ANON-' . str_pad((string) $p->id, 9, '0', STR_PAD_LEFT), 'matricula' => null, 'user_id' => null,
                ]);
                $resumo['participantes']++;
            }

            foreach ($alvo['usuarios'] as $u) {
                $this->anonimizarConta($u);
                $resumo['contas']++;
            }

            return $resumo;
        });

        $this->registrar($acao, $identificador, $executor, $resumo);

        return $resumo;
    }

    /** A conta tem registros que impedem a exclusão física (trabalhos, avaliações, eventos, arquivos...)? */
    public function temVinculos(User $u): bool
    {
        return DB::table('trabalhos')->where('submetido_por', $u->id)->exists()
            || DB::table('trabalho_avaliacoes')->where('avaliador_id', $u->id)->exists()
            || DB::table('apresentacao_avaliacoes')->where('avaliador_id', $u->id)->exists()
            || DB::table('eventos')->where('criado_por', $u->id)->exists()
            || DB::table('trabalho_arquivos')->where('enviado_por', $u->id)->exists();
    }

    /**
     * Exclusão de conta pedida pelo próprio titular: apaga a conta quando nada depende dela;
     * caso contrário anonimiza (preserva trabalhos, notas e eventos sem identificar a pessoa).
     * Em ambos os casos os dados de participante e as autorias ligados à conta são anonimizados.
     */
    public function excluirConta(User $usuario): string
    {
        $identificador = $usuario->email;
        $manter = $this->temVinculos($usuario);

        $this->anonimizar($identificador, $usuario, 'exclusao_conta');

        if (!$manter) {
            // Sem vínculos: remove de fato a conta (já anonimizada)
            User::whereKey($usuario->id)->delete();

            return 'excluida';
        }

        return 'anonimizada';
    }

    private function anonimizarConta(User $u): void
    {
        $u->forceFill([
            'name' => 'Usuário anonimizado', 'email' => "anonimo-{$u->id}@anonimizado.invalid", 'password' => Str::random(40),
            'remember_token' => null, 'email_verified_at' => null, 'role' => 'user',
        ])->save();

        DB::table('evento_user')->where('user_id', $u->id)->delete();   // perde papéis (avaliador/gestor)
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $u->id)->delete();   // encerra sessões abertas
        }
    }

    /** Apaga rascunhos de trabalhos abandonados há mais de N dias (e seus arquivos). Retorna quantos. */
    public function limparRascunhos(int $dias): int
    {
        $rascunhos = Trabalho::withTrashed()->where('status', Trabalho::RASCUNHO)->where('updated_at', '<', now()->subDays($dias))->with('arquivos')->get();

        foreach ($rascunhos as $t) {
            foreach ($t->arquivos as $arquivo) {
                Storage::disk('trabalhos')->delete($arquivo->path);
            }
            $t->forceDelete();   // autores, arquivos e histórico caem em cascata
        }

        $this->registrar('limpeza_rascunhos', null, null, ['rascunhos_apagados' => $rascunhos->count(), 'dias' => $dias]);

        return $rascunhos->count();
    }

    private function registrar(string $acao, ?string $identificador, ?User $executor, array $resumo): void
    {
        LgpdRegistro::create([
            'acao' => $acao,
            // Só o hash do identificador: a trilha de auditoria não vira nova cópia do dado pessoal
            'titular_hash' => $identificador ? hash('sha256', mb_strtolower(trim($identificador))) : null,
            'executado_por' => $executor?->id,
            'resumo' => $resumo,
            'created_at' => now(),
        ]);
    }
}
