<?php

namespace App\Exports;

use App\Models\Frequencia;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FrequenciaGeralExport implements FromCollection, WithHeadings, WithMapping
{
    protected $filtros;

    public function __construct($filtros)
    {
        $this->filtros = $filtros;
    }

    /**
     * Define a coleção de dados baseada nos filtros e vínculo 'aluno'.
     */
    public function collection()
    {
        // 1. Inicia a Query
        $query = Frequencia::query()
            ->with([
                'participante.turma.curso',
                'atividade.evento'
            ])
            ->whereHas('participante', function ($q) {
                // FILTRO PRINCIPAL: Apenas participantes com vínculo 'aluno'
                $q->where('tipo_vinculo', 'aluno');
            })
            ->orderBy('data_registro', 'asc');

        // 2. Aplica Filtros do Formulário (Evento ou Curso)
        if (isset($this->filtros['evento_id']) && $this->filtros['evento_id']) {
            $query->whereHas('atividade', function ($q) {
                $q->where('evento_id', $this->filtros['evento_id']);
            });
        }

        if (isset($this->filtros['curso_id']) && $this->filtros['curso_id']) {
            $query->whereHas('participante.turma', function ($q) {
                $q->where('curso_id', $this->filtros['curso_id']);
            });
        }

        // 3. Retorna a Coleção Filtrada
        return $query->get();
    }

    /**
     * Mapeia cada item da coleção para as colunas **SIMPLIFICADAS**.
     */
    public function map($frequencia): array
    {
        $participante = $frequencia->participante;
        $atividade = $frequencia->atividade;
        $turma = $participante->turma;
        $curso = $turma ? $turma->curso : null;

        return [
            // 1. NOME
            $participante->nome_completo,

            // 2. CURSO
            $curso ? $curso->nome : 'N/A',

            // 3. TURMA
            $turma ? $turma->nome_completo : 'N/A',

            // 4. ATIVIDADE
            $atividade->titulo ?? 'N/A',

            // 5. HORA/DATA DE REGISTRO
            $frequencia->data_registro->format('d/m/Y H:i'),
        ];
    }

    /**
     * Define o cabeçalho das colunas **SIMPLIFICADAS**.
     */
    public function headings(): array
    {
        return [
            'NOME DO ALUNO',
            'CURSO',
            'TURMA',
            'ATIVIDADE',
            'DATA E HORA DO REGISTRO',
        ];
    }
}
