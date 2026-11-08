<?php

namespace App\Exports;

use App\Models\Trabalho;
use App\Models\TrabalhoAvaliacao;
use App\Services\RelatorioNotas;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Exportação de notas: "resumo" (ranking por trabalho) ou "detalhado" (uma linha por avaliação, com
 * notas por critério e pareceres). Contém dados da avaliação: uso restrito à gestão.
 */
class NotasExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    /** @param Collection $linhas resultado de RelatorioNotas::resumo()/detalhado() */
    public function __construct(private Collection $linhas, private Collection $criterios, private string $tipo)
    {
    }

    public function headings(): array
    {
        $criterios = $this->criterios->pluck('nome')->all();

        return $this->tipo === 'detalhado'
            ? array_merge(['Protocolo', 'Título', 'Rodada', 'Avaliador', 'E-mail do avaliador', 'Situação', 'Nota final', 'Recomendação'], $criterios, ['Parecer ao autor', 'Comentário confidencial / motivo da recusa', 'Prazo', 'Concluída em'])
            : array_merge(['Posição', 'Protocolo', 'Título', 'Categoria', 'Área', 'Modalidade', 'Situação', 'Decisão', 'Nota média', 'Avaliações concluídas', 'Notas individuais', 'Divergência', 'Recomendações'], array_map(fn ($c) => "Média: {$c}", $criterios));
    }

    public function collection(): Collection
    {
        return $this->linhas->map(fn ($l) => array_map([TrabalhosExport::class, 'seguro'], $this->tipo === 'detalhado' ? $this->detalhe($l) : $this->resumo($l)))->values();
    }

    private function resumo(array $l): array
    {
        /** @var Trabalho $t */
        $t = $l['trabalho'];

        return array_merge([
            $l['posicao'],
            $t->codigo,
            $t->titulo,
            RelatorioNotas::categoria($t->tipo_trabalho),
            $t->area->nome ?? '',
            Trabalho::MODALIDADES[$t->modalidade_apresentacao] ?? '',
            $t->statusRotulo(),
            ['aprovado' => 'Aprovado', 'aprovado_com_ressalvas' => 'Aprovado com ressalvas', 'reprovado' => 'Reprovado'][$t->decisao] ?? '',
            $l['nota'],
            "{$l['concluidas']}/{$l['atribuidas']}",
            collect($l['notas'])->map(fn ($n) => number_format($n, 2, ',', ''))->implode('; '),
            $l['divergencia'],
            RelatorioNotas::rotuloRecomendacoes($l['recomendacoes']),
        ], array_values($l['por_criterio']));
    }

    private function detalhe(array $l): array
    {
        /** @var Trabalho $t */
        $t = $l['trabalho'];
        /** @var TrabalhoAvaliacao $a */
        $a = $l['avaliacao'];

        return array_merge([
            $t->codigo,
            $t->titulo,
            $a->rodada,
            $a->avaliador->name,
            $a->avaliador->email,
            $a->statusRotulo(),
            $a->nota_final,
            $a->recomendacaoRotulo(),
        ], array_values($l['por_criterio']), [
            $a->parecer,
            $a->parecer_confidencial,
            $a->prazo?->format('d/m/Y'),
            $a->concluida_em?->format('d/m/Y H:i'),
        ]);
    }
}
