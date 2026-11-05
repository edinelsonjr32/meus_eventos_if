<?php

namespace App\Services;

use App\Http\Requests\SalvarTrabalhoRequest;
use App\Mail\TrabalhoSubmetidoMail;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Trabalho;
use App\Models\TrabalhoArquivo;
use App\Models\TrabalhoConfig;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Regras de persistência e de mudança de status dos trabalhos.
 * Toda transição grava histórico.
 */
class TrabalhoWorkflow
{
    /** Cria ou atualiza o trabalho (rascunho ou reenvio), seus autores e o PDF. */
    public function salvar(Evento $evento, User $user, array $dados, ?Trabalho $trabalho = null, ?UploadedFile $arquivo = null): Trabalho
    {
        return DB::transaction(function () use ($evento, $user, $dados, $trabalho, $arquivo) {
            $campos = [
                'titulo' => $dados['titulo'],
                'resumo' => $dados['resumo'] ?? null,
                'abstract' => $dados['abstract'] ?? null,
                'palavras_chave' => SalvarTrabalhoRequest::palavras($dados['palavras_chave'] ?? null),
                'tipo_trabalho' => $dados['tipo_trabalho'] ?? null,
                'area_tematica_id' => $dados['area_tematica_id'] ?? null,
                'modalidade_apresentacao' => $dados['modalidade_apresentacao'] ?? null,
                'agencia_fomento' => $dados['agencia_fomento'] ?? null,
                'projeto_pesquisa' => $dados['projeto_pesquisa'] ?? null,
                'comite_etica' => $dados['comite_etica'] ?? null,
                'autoriza_publicacao' => (bool) ($dados['autoriza_publicacao'] ?? false),
            ];
            $campos['autorizou_em'] = $campos['autoriza_publicacao'] ? ($trabalho?->autorizou_em ?? now()) : null;

            if ($trabalho) {
                $trabalho->update($campos);
            } else {
                $trabalho = Trabalho::create($campos + [
                    'evento_id' => $evento->id,
                    'submetido_por' => $user->id,
                    'status' => Trabalho::RASCUNHO,
                ]);
                $this->registrar($trabalho, $user, null, Trabalho::RASCUNHO, 'Rascunho criado');
            }

            $this->sincronizarAutores($trabalho, $dados['autores'] ?? []);

            if ($arquivo) {
                $this->anexarArquivo($trabalho, $user, $arquivo);
            }

            return $trabalho->fresh(['autores', 'arquivoAtual']);
        });
    }

    /** Valida completude e conclui o envio: gera protocolo, muda status e avisa os autores. */
    public function submeter(Trabalho $trabalho, User $user): Trabalho
    {
        $reenvio = $trabalho->status === Trabalho::AGUARDANDO_CORRECAO;

        DB::transaction(function () use ($trabalho, $user, $reenvio) {
            $de = $trabalho->status;
            $trabalho->refresh();

            if (!$trabalho->codigo) {
                $trabalho->codigo = $this->gerarCodigo($trabalho->evento);
            }

            $trabalho->status = $reenvio ? 'resubmetido' : Trabalho::SUBMETIDO;
            $trabalho->submetido_em = now();
            $trabalho->save();

            $this->registrar($trabalho, $user, $de, $trabalho->status, $reenvio ? 'Versão corrigida enviada' : 'Trabalho submetido');
        });

        $trabalho->load(['autores', 'evento']);
        $destinatarios = $trabalho->autores->pluck('email')->push($user->email)->map(fn ($e) => mb_strtolower($e))->unique();
        foreach ($destinatarios as $email) {
            Mail::to($email)->queue(new TrabalhoSubmetidoMail($trabalho));
        }

        return $trabalho;
    }

    public function retirar(Trabalho $trabalho, User $user, ?string $motivo = null): void
    {
        DB::transaction(function () use ($trabalho, $user, $motivo) {
            $de = $trabalho->status;
            $trabalho->update(['status' => Trabalho::RETIRADO]);
            $this->registrar($trabalho, $user, $de, Trabalho::RETIRADO, $motivo ?: 'Retirado pelo autor');
        });
    }

    public function registrar(Trabalho $trabalho, ?User $user, ?string $de, string $para, ?string $justificativa = null): void
    {
        $trabalho->historico()->create([
            'usuario_id' => $user?->id, 'de' => $de, 'para' => $para, 'justificativa' => $justificativa, 'created_at' => now(),
        ]);
    }

    /** Protocolo único por evento, com contador travado para evitar duplicidade. */
    private function gerarCodigo(Evento $evento): string
    {
        $config = TrabalhoConfig::where('evento_id', $evento->id)->lockForUpdate()->first();
        if (!$config) {
            $config = TrabalhoConfig::create(['evento_id' => $evento->id]);
            $config = TrabalhoConfig::where('id', $config->id)->lockForUpdate()->first();
        }

        $numero = $config->proximo_numero;
        $config->forceFill(['proximo_numero' => $numero + 1])->save();

        return sprintf('TRB-%d-%04d', $evento->id, $numero);
    }

    private function sincronizarAutores(Trabalho $trabalho, array $autores): void
    {
        $trabalho->autores()->delete();

        $principal = collect($autores)->contains(fn ($a) => !empty($a['correspondente']));

        foreach (array_values($autores) as $i => $a) {
            // Apenas vincula (nunca altera) um participante já existente com o mesmo CPF
            $participante = Participante::where('cpf', $a['cpf'])->first();

            $trabalho->autores()->create([
                'ordem' => $i + 1,
                'participante_id' => $participante?->id,
                'nome_completo' => $a['nome_completo'],
                'cpf' => $a['cpf'],
                'email' => $a['email'],
                'telefone' => $a['telefone'] ?? null,
                'instituicao' => $a['instituicao'] ?? null,
                'vinculo' => $a['vinculo'] ?? null,
                'titulacao' => $a['titulacao'] ?? null,
                'lattes' => $a['lattes'] ?? null,
                'orcid' => $a['orcid'] ?? null,
                'tipo_autor' => $a['tipo_autor'],
                'apresentador' => (bool) ($a['apresentador'] ?? false),
                // Sem correspondente definido, o autor principal assume
                'correspondente' => $principal ? (bool) ($a['correspondente'] ?? false) : $a['tipo_autor'] === 'autor_principal',
            ]);
        }
    }

    private function anexarArquivo(Trabalho $trabalho, User $user, UploadedFile $arquivo): TrabalhoArquivo
    {
        $versao = ((int) $trabalho->arquivos()->max('versao')) + 1;
        $tipo = $trabalho->status === Trabalho::AGUARDANDO_CORRECAO ? 'corrigido' : 'original';
        $path = $arquivo->storeAs(
            "{$trabalho->evento_id}/{$trabalho->id}",
            sprintf('v%d-%s.pdf', $versao, Str::random(24)),
            'trabalhos'
        );

        if ($path === false) {
            throw ValidationException::withMessages(['arquivo' => 'Não foi possível gravar o arquivo. Tente novamente.']);
        }

        $trabalho->arquivos()->update(['ativo' => false]);

        return $trabalho->arquivos()->create([
            'versao' => $versao,
            'tipo' => $tipo,
            'path' => $path,
            'nome_original' => Str::limit($arquivo->getClientOriginalName(), 200, ''),
            'mime' => 'application/pdf',
            'tamanho' => $arquivo->getSize(),
            'sha256' => hash_file('sha256', $arquivo->getRealPath()),
            'enviado_por' => $user->id,
            'ativo' => true,
        ]);
    }
}
