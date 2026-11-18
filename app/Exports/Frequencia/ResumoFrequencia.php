<?php

namespace App\Exports\Frequencia;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** Aba de capa: filtros usados, indicadores e quadro por curso/turma, com orientação de uso das demais abas. */
class ResumoFrequencia implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    /** @param array<int,array{0:string,1:string}> $filtros @param array<int,array{0:string,1:mixed}> $indicadores @param array<int,array<int,mixed>> $turmas */
    public function __construct(private array $filtros, private array $indicadores, private array $turmas, private string $geradoPor)
    {
    }

    public function title(): string
    {
        return 'Resumo';
    }

    public function columnWidths(): array
    {
        return ['A' => 34, 'B' => 38, 'C' => 14, 'D' => 14, 'E' => 18];
    }

    private function linhas(): array
    {
        $l = [['Relatório de frequência'], ['IFPA Campus Óbidos · gerado em ' . now()->format('d/m/Y \à\s H:i') . ' por ' . $this->geradoPor], [''], ['FILTROS APLICADOS']];
        foreach ($this->filtros as [$rot, $val]) {
            $l[] = [$rot, $val];
        }
        $l[] = [''];
        $l[] = ['INDICADORES'];
        foreach ($this->indicadores as [$rot, $val]) {
            $l[] = [$rot, $val];
        }
        $l[] = [''];
        $l[] = ['POR CURSO E TURMA'];
        $l[] = ['Curso', 'Turma', 'Alunos', 'Presenças', 'Horas (h)'];
        foreach ($this->turmas as $t) {
            $l[] = $t;
        }
        $l[] = [''];
        $l[] = ['COMO USAR'];
        $l[] = ['• Frequência: uma linha por presença. Use as setas do cabeçalho para filtrar por atividade, curso, turma ou aluno.'];
        $l[] = ['• Por aluno: total de atividades e horas de cada aluno (para lançar horas complementares).'];
        $l[] = ['• Por atividade: presentes em cada atividade.'];
        $l[] = ['• Matriz: alunos nas linhas, atividades nas colunas ("P" = presente), para conferência rápida.'];
        $l[] = ['CPF não é incluído por privacidade (LGPD); use a matrícula para identificar o aluno.'];

        return $l;
    }

    public function array(): array
    {
        return $this->linhas();
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $evento) {
            $p = $evento->sheet->getDelegate();
            $linhas = $this->linhas();
            $p->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('047857');
            $p->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('64748B');

            foreach ($linhas as $i => $linha) {
                $n = $i + 1;
                $txt = (string) ($linha[0] ?? '');
                if (in_array($txt, ['FILTROS APLICADOS', 'INDICADORES', 'POR CURSO E TURMA', 'COMO USAR'], true)) {
                    $p->getStyle("A{$n}:E{$n}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $p->getStyle("A{$n}:E{$n}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('047857');
                } elseif ($txt === 'Curso' && ($linha[1] ?? '') === 'Turma') {
                    $p->getStyle("A{$n}:E{$n}")->getFont()->setBold(true);
                    $p->getStyle("A{$n}:E{$n}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D1FAE5');
                    $p->getStyle("A{$n}:E{$n}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('A7F3D0');
                } elseif (count($linha) === 2 && $txt !== '') {
                    $p->getStyle("A{$n}")->getFont()->setBold(true);
                    $p->getStyle("B{$n}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                } elseif (count($linha) === 5) {
                    $p->getStyle("A{$n}:E{$n}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
                }
            }
        }];
    }
}
