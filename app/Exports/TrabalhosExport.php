<?php

namespace App\Exports;

use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TrabalhosExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private Collection $trabalhos)
    {
    }

    public function collection(): Collection
    {
        return $this->trabalhos;
    }

    public function headings(): array
    {
        return [
            'Protocolo', 'Título', 'Tipo', 'Área temática', 'Modalidade', 'Status', 'Enviado em', 'Autores',
            'Apresentador(es)', 'Correspondente', 'E-mail do correspondente', 'Avaliadores (atribuídos/concluídos)',
            'Autoriza publicação', 'Palavras-chave',
        ];
    }

    /** @param Trabalho $t */
    public function map($t): array
    {
        $corresp = $t->autores->firstWhere('correspondente', true) ?? $t->autores->first();
        $atribuidas = $t->avaliacoes->where('status', '!=', 'recusada');

        return array_map([self::class, 'seguro'], [
            $t->codigo,
            $t->titulo,
            TrabalhoConfig::TIPOS[$t->tipo_trabalho] ?? $t->tipo_trabalho,
            $t->area->nome ?? '',
            Trabalho::MODALIDADES[$t->modalidade_apresentacao] ?? '',
            $t->statusRotulo(),
            $t->submetido_em?->format('d/m/Y H:i'),
            $t->autores->map(fn ($a) => "{$a->nome_completo} ({$a->tipoRotulo()})")->implode('; '),
            $t->autores->where('apresentador', true)->pluck('nome_completo')->implode('; '),
            $corresp?->nome_completo,
            $corresp?->email,
            $atribuidas->count() . '/' . $atribuidas->where('status', 'concluida')->count(),
            $t->autoriza_publicacao ? 'Sim' : 'Não',
            implode(', ', $t->palavras_chave ?? []),
        ]);
    }

    /** Neutraliza injeção de fórmulas em planilhas (valores iniciados por = + - @ ou tab/CR). */
    public static function seguro(mixed $valor): mixed
    {
        if (is_string($valor) && $valor !== '' && preg_match('/^[=+\-@\t\r]/', $valor)) {
            return "'" . $valor;
        }

        return $valor;
    }
}
