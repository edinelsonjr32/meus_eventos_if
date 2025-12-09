<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Curso;

class EstruturaAcademicaSeeder extends Seeder
{
    public function run(): void
    {
        // Curso 1
        $info = Curso::create(['nome' => 'Técnico em Informática']);
        $info->turmas()->createMany([
            ['ano' => '2022'], // 3º Ano
            ['ano' => '2023'], // 2º Ano
            ['ano' => '2024'], // 1º Ano
        ]);

        // Curso 2
        $meio = Curso::create(['nome' => 'Técnico em Meio Ambiente']);
        $meio->turmas()->createMany([
            ['ano' => '2024'],
        ]);


        
        // Curso 3 (Superior)
        $ads = Curso::create(['nome' => 'Tecnologia em ADS']);
        $ads->turmas()->createMany([
            ['ano' => '2021'],
            ['ano' => '2022'],
            ['ano' => '2023'],
            ['ano' => '2024'],
        ]);
    }
}
