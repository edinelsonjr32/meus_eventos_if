<?php

namespace App\Services;

use App\Mail\MovimentacaoMail;
use App\Models\DemandaVoluntario;
use App\Models\Proposta;
use App\Models\Trabalho;
use App\Models\Voluntario;
use Illuminate\Support\Facades\Mail;

/** Avisos por e-mail das movimentações de trabalhos, propostas de minicurso/oficina e voluntariado. Sempre indicam onde ver o status no perfil. */
class Notificacoes
{
    /** Quem acompanha o trabalho: autores com e-mail e a pessoa que submeteu. */
    private static function destinatariosDoTrabalho(Trabalho $trabalho): array
    {
        $trabalho->loadMissing(['autores', 'submetente']);

        return $trabalho->autores->pluck('email')->filter()->push($trabalho->submetente?->email)->filter()
            ->map(fn ($e) => mb_strtolower($e))->unique()->values()->all();
    }

    private static function enviar(array|string $emails, array $d): void
    {
        foreach ((array) $emails as $email) {
            if (filled($email)) {
                Mail::to($email)->queue(new MovimentacaoMail($d));
            }
        }
    }

    // ---------- Trabalhos ----------

    public static function trabalho(Trabalho $trabalho, string $titulo, string $mensagem, string $tipo = 'info', ?string $citacao = null, array $detalhes = []): void
    {
        $trabalho->loadMissing('evento');
        self::enviar(self::destinatariosDoTrabalho($trabalho), [
            'assunto' => "{$titulo} — {$trabalho->codigo}", 'titulo' => $titulo, 'evento' => $trabalho->evento->titulo,
            'item_rotulo' => 'Trabalho', 'item' => $trabalho->titulo, 'situacao' => $trabalho->statusRotulo(),
            'detalhes' => ['Protocolo' => $trabalho->codigo] + $detalhes, 'tipo' => $tipo, 'mensagem' => $mensagem, 'citacao' => $citacao,
            'botao' => 'Ver o status do meu trabalho', 'href' => route('trabalhos.show', $trabalho),
            'area' => 'Meus Trabalhos', 'area_href' => route('trabalhos.index'),
        ]);
    }

    // ---------- Avaliadores e gestão de trabalhos ----------

    /** Aviso a quem avalia. Não leva nomes de autores (avaliação às cegas). */
    public static function avaliador(Trabalho $trabalho, ?\App\Models\User $avaliador, string $fase, string $titulo, string $mensagem, string $tipo = 'info'): void
    {
        if (!$avaliador) {
            return;
        }
        $trabalho->loadMissing('evento');
        $lista = route($fase === 'apresentacao' ? 'avaliador.apresentacoes.index' : 'avaliador.avaliacoes.index');
        self::enviar($avaliador->email, [
            'assunto' => "{$titulo} — {$trabalho->codigo}", 'titulo' => $titulo, 'evento' => $trabalho->evento->titulo,
            'item_rotulo' => 'Trabalho', 'item' => $trabalho->titulo, 'situacao' => null,
            'detalhes' => ['Protocolo' => $trabalho->codigo, 'Fase' => self::fase($fase)], 'tipo' => $tipo, 'mensagem' => $mensagem, 'citacao' => null,
            'botao' => 'Ver minhas avaliações', 'href' => $lista, 'area' => 'Minhas avaliações', 'area_href' => $lista,
        ]);
    }

    /** Aviso à gestão dos trabalhos do evento (dono, co-administradores e gestores de trabalhos). */
    public static function gestaoDoTrabalho(Trabalho $trabalho, string $fase, string $titulo, string $mensagem, string $tipo = 'info', ?string $citacao = null, array $detalhes = []): void
    {
        $trabalho->loadMissing('evento');
        self::enviar($trabalho->evento->usuariosDaGestaoDeTrabalhos()->pluck('email')->all(), [
            'assunto' => "{$titulo} — {$trabalho->codigo}", 'titulo' => $titulo, 'evento' => $trabalho->evento->titulo,
            'item_rotulo' => 'Trabalho', 'item' => $trabalho->titulo, 'situacao' => $trabalho->statusRotulo(),
            'detalhes' => ['Protocolo' => $trabalho->codigo, 'Fase' => self::fase($fase)] + $detalhes, 'tipo' => $tipo, 'mensagem' => $mensagem,
            'citacao' => $citacao, 'citacao_titulo' => 'Motivo informado',
            'botao' => 'Abrir o trabalho', 'href' => route('gestao.trabalhos.show', $trabalho),
            'area' => 'Trabalhos', 'area_href' => route('gestao.trabalhos.index', $trabalho->evento),
        ]);
    }

    private static function fase(string $fase): string
    {
        return $fase === 'apresentacao' ? 'Avaliação da apresentação' : 'Avaliação escrita';
    }

    // ---------- Propostas de minicurso/oficina ----------

    public static function proposta(Proposta $proposta, string $titulo, string $mensagem, string $tipo = 'info', ?string $citacao = null, array $detalhes = []): void
    {
        $proposta->loadMissing(['evento', 'proponente']);
        self::enviar($proposta->proponente?->email, [
            'assunto' => "{$titulo} — {$proposta->titulo}", 'titulo' => $titulo, 'evento' => $proposta->evento->titulo,
            'item_rotulo' => $proposta->tipoRotulo(), 'item' => $proposta->titulo, 'situacao' => $proposta->statusRotulo(),
            'detalhes' => $detalhes, 'tipo' => $tipo, 'mensagem' => $mensagem, 'citacao' => $citacao,
            'botao' => 'Ver a situação da minha proposta', 'href' => route('propostas.show', $proposta),
            'area' => 'Propostas de Curso/Oficina', 'area_href' => route('propostas.minhas'),
        ]);
    }

    // ---------- Voluntariado ----------

    public static function voluntario(Voluntario $voluntario, string $titulo, string $mensagem, string $tipo = 'info', array $detalhes = [], ?string $botao = null, ?string $href = null): void
    {
        $voluntario->loadMissing(['evento', 'usuario', 'chamada']);
        self::enviar($voluntario->usuario?->email, [
            'assunto' => "{$titulo} — {$voluntario->evento->titulo}", 'titulo' => $titulo, 'evento' => $voluntario->evento->titulo,
            'item_rotulo' => 'Chamada', 'item' => $voluntario->chamada->titulo, 'situacao' => $voluntario->statusRotulo(),
            'detalhes' => $detalhes, 'tipo' => $tipo, 'mensagem' => $mensagem,
            'botao' => $botao ?? 'Ver meu voluntariado', 'href' => $href ?? route('voluntariado.meu'),
            'area' => 'Voluntariado', 'area_href' => route('voluntariado.meu'),
        ]);
    }

    /** Dados da atividade (demanda) para os avisos de vínculo. */
    public static function detalhesDaDemanda(DemandaVoluntario $demanda): array
    {
        return array_filter([
            'Atividade' => $demanda->titulo,
            'Quando' => $demanda->data_inicio ? $demanda->data_inicio->format('d/m/Y H:i') . ($demanda->data_fim ? ' a ' . $demanda->data_fim->format('H:i') : '') : null,
            'Local' => $demanda->local,
            'Quem procurar' => trim(($demanda->responsavel_nome ?? '') . ($demanda->responsavel_contato ? ' · ' . $demanda->responsavel_contato : '')) ?: null,
            'Carga horária' => rtrim(rtrim(number_format((float) $demanda->carga_horaria, 1, ',', ''), '0'), ',') . ' h',
        ]);
    }
}
