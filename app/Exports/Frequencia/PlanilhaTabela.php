<?php

namespace App\Exports\Frequencia;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Aba de tabela pronta para uso: cabeçalho estilizado e congelado, filtro automático em todas as colunas,
 * larguras definidas, linhas zebradas e formatos de data/número reais (filtram e ordenam corretamente no Excel).
 */
class PlanilhaTabela extends DefaultValueBinder implements FromArray, WithTitle, WithColumnWidths, WithEvents, WithCustomValueBinder
{
    /** Textos que parecem número (matrícula 002345) continuam texto: o Excel não pode cortar zeros à esquerda. */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /**
     * @param array<int,string> $cabecalho
     * @param array<int,array<int,mixed>> $linhas
     * @param array<int,int|float> $larguras largura por coluna (na ordem do cabeçalho)
     * @param array<int,string> $formatos formato de número por coluna (índice 0-based), ex.: 'dd/mm/yyyy'
     */
    public function __construct(
        private string $titulo,
        private array $cabecalho,
        private array $linhas,
        private array $larguras = [],
        private array $formatos = [],
        private int $rotacionarCabecalhoAPartir = -1,
    ) {
    }

    public function title(): string
    {
        return $this->titulo;
    }

    public function array(): array
    {
        return array_merge([$this->cabecalho], $this->linhas);
    }

    public function columnWidths(): array
    {
        $out = [];
        foreach ($this->cabecalho as $i => $_) {
            $out[Coordinate::stringFromColumnIndex($i + 1)] = $this->larguras[$i] ?? 18;
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $evento) {
            $planilha = $evento->sheet->getDelegate();
            $colunas = count($this->cabecalho);
            $ultimaColuna = Coordinate::stringFromColumnIndex(max(1, $colunas));
            $ultimaLinha = max(1, count($this->linhas) + 1);
            $todo = "A1:{$ultimaColuna}{$ultimaLinha}";

            $planilha->getStyle($todo)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
            $planilha->getStyle($todo)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            $cab = $planilha->getStyle("A1:{$ultimaColuna}1");
            $cab->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $cab->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('047857');
            $cab->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
            $planilha->getRowDimension(1)->setRowHeight($this->rotacionarCabecalhoAPartir >= 0 ? 150 : 30);

            if ($this->rotacionarCabecalhoAPartir >= 0 && $colunas > $this->rotacionarCabecalhoAPartir) {
                $de = Coordinate::stringFromColumnIndex($this->rotacionarCabecalhoAPartir + 1);
                $planilha->getStyle("{$de}1:{$ultimaColuna}1")->getAlignment()->setTextRotation(90)->setVertical(Alignment::VERTICAL_BOTTOM);
            }

            // Zebra suave nas linhas de dados
            for ($l = 3; $l <= $ultimaLinha; $l += 2) {
                $planilha->getStyle("A{$l}:{$ultimaColuna}{$l}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0FDF4');
            }

            foreach ($this->formatos as $i => $codigo) {
                $col = Coordinate::stringFromColumnIndex($i + 1);
                $planilha->getStyle("{$col}2:{$col}{$ultimaLinha}")->getNumberFormat()->setFormatCode($codigo);
            }

            $planilha->freezePane('A2');
            $planilha->setAutoFilter($todo);
        }];
    }
}
