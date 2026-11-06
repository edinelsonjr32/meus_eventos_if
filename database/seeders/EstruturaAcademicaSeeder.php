<?php

namespace Database\Seeders;

use App\Models\Curso;
use Illuminate\Database\Seeder;

/** Cursos e turmas. Idempotente: pode ser executado novamente sem duplicar registros. */
class EstruturaAcademicaSeeder extends Seeder
{
    private const ESTRUTURA = [
        'Técnico em Informática' => ['2022', '2023', '2024'],
        'Técnico em Meio Ambiente' => ['2024'],
        'Tecnologia em ADS' => ['2021', '2022', '2023', '2024'],
    ];

    public function run(): void
    {
        foreach (self::ESTRUTURA as $nome => $anos) {
            $curso = Curso::firstOrCreate(['nome' => $nome]);

            foreach ($anos as $ano) {
                $curso->turmas()->firstOrCreate(['ano' => $ano]);
            }
        }
    }
}
