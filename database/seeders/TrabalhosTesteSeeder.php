<?php

namespace Database\Seeders;

use App\Models\AreaTematica;
use App\Models\Evento;
use App\Models\Trabalho;
use App\Models\TrabalhoConfig;
use App\Models\User;
use App\Services\TrabalhoWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Chamada de trabalhos aberta + usuários (gestor, avaliador, autores) + alguns trabalhos de exemplo.
 * Uso: php artisan db:seed --class=TrabalhosTesteSeeder
 */
class TrabalhosTesteSeeder extends Seeder
{
    public function run(): void
    {
        $evento = Evento::where('slug', 'semana-de-tecnologia-2026')->first();
        if (!$evento) {
            $this->command?->error('Rode antes: php artisan db:seed --class=EventoTesteSeeder');
            return;
        }

        TrabalhoConfig::updateOrCreate(['evento_id' => $evento->id], [
            'ativo' => true,
            'submissao_inicio' => now()->subDay(),
            'submissao_fim' => now()->addDays(30),
            'max_autores' => 6,
            'max_arquivo_mb' => 20,
            'resumo_min_palavras' => 20,
            'resumo_max_palavras' => 300,
            'tipos_permitidos' => ['resumo', 'resumo_expandido', 'artigo_completo', 'relato_experiencia'],
            'regras_texto' => "1. Os trabalhos devem ser inéditos.\n2. O arquivo deve estar em PDF.\n3. Informe todos os autores com CPF válido.",
        ]);

        foreach (['Educação e Tecnologia', 'Inovação e Empreendedorismo', 'Sustentabilidade', 'Computação e Sistemas'] as $i => $nome) {
            AreaTematica::firstOrCreate(['evento_id' => $evento->id, 'nome' => $nome], ['ordem' => $i]);
        }

        // Gestor de trabalhos e avaliador (usuários comuns com papel no evento)
        foreach ([
            ['gestor@teste.com', 'Gestor de Trabalhos', 'Gestor@12345', Evento::PAPEL_GESTOR_TRABALHOS],
            ['avaliador@teste.com', 'Avaliador Teste', 'Avaliador@12345', Evento::PAPEL_AVALIADOR],
        ] as [$email, $nome, $senha, $papel]) {
            $u = User::firstOrCreate(['email' => $email], ['name' => $nome, 'password' => $senha]);
            $u->forceFill(['role' => 'user', 'email_verified_at' => now()])->save();
            DB::table('evento_user')->updateOrInsert(
                ['evento_id' => $evento->id, 'user_id' => $u->id, 'role' => $papel],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $autor = User::firstOrCreate(['email' => 'autor@teste.com'], ['name' => 'Autor Teste', 'password' => 'Autor@12345']);
        $autor->forceFill(['role' => 'user', 'email_verified_at' => now()])->save();

        $workflow = app(TrabalhoWorkflow::class);
        $resumo = trim(str_repeat('Este trabalho apresenta uma investigação sobre o uso de tecnologias digitais no ensino técnico, descrevendo método, resultados e conclusões. ', 3));

        $exemplos = [
            ['Uso de IA generativa no ensino técnico', 'artigo_completo', true],
            ['Relato de experiência: robótica na escola', 'relato_experiencia', true],
            ['Sistema de controle de frequência com QR Code', 'resumo_expandido', false],
        ];

        foreach ($exemplos as $i => [$titulo, $tipo, $submeter]) {
            if (Trabalho::where('evento_id', $evento->id)->where('titulo', $titulo)->exists()) {
                continue;
            }

            $trabalho = $workflow->salvar($evento, $autor, [
                'titulo' => $titulo,
                'resumo' => $resumo,
                'palavras_chave' => 'tecnologia, educação, inovação',
                'tipo_trabalho' => $tipo,
                'area_tematica_id' => AreaTematica::where('evento_id', $evento->id)->first()->id,
                'modalidade_apresentacao' => $i % 2 ? 'poster' : 'oral',
                'autoriza_publicacao' => true,
                'autores' => [
                    ['nome_completo' => 'Autor Teste', 'cpf' => '111.444.777-35', 'email' => 'autor@teste.com', 'tipo_autor' => 'autor_principal', 'apresentador' => true, 'correspondente' => true, 'vinculo' => 'aluno', 'instituicao' => 'IFPA Campus Óbidos'],
                    ['nome_completo' => 'Maria Orientadora', 'cpf' => '529.982.247-25', 'email' => 'maria@exemplo.com', 'tipo_autor' => 'orientador', 'apresentador' => false, 'correspondente' => false, 'vinculo' => 'servidor', 'instituicao' => 'IFPA', 'titulacao' => 'Doutora'],
                ],
            ]);

            // PDF mínimo válido para teste
            $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
            $path = "{$evento->id}/{$trabalho->id}/v1-seed.pdf";
            Storage::disk('trabalhos')->put($path, $pdf);
            $trabalho->arquivos()->create([
                'versao' => 1, 'tipo' => 'original', 'path' => $path, 'nome_original' => 'trabalho-exemplo.pdf',
                'mime' => 'application/pdf', 'tamanho' => strlen($pdf), 'sha256' => hash('sha256', $pdf),
                'enviado_por' => $autor->id, 'ativo' => true,
            ]);

            if ($submeter) {
                $workflow->submeter($trabalho, $autor);
            }
        }

        $this->command?->info('Logins: gestor@teste.com / Gestor@12345 · avaliador@teste.com / Avaliador@12345 · autor@teste.com / Autor@12345');
    }
}
