<?php

namespace Database\Seeders;

use App\Models\Atividade;
use App\Models\Equipe;
use App\Models\Evento;
use App\Models\Frequencia;
use App\Models\Inscricao;
use App\Models\Participante;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dados de teste completos de um evento.
 * Uso: php artisan db:seed --class=EventoTesteSeeder
 */
class EventoTesteSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Seeder de dados de teste bloqueado em produção (cria usuários com senhas conhecidas).');

            return;
        }

        $admin = User::where('email', 'admin@teste.com')->first();
        if (!$admin) {
            $admin = User::create(['name' => 'Admin Teste', 'email' => 'admin@teste.com', 'password' => 'Admin@12345']);
            $admin->forceFill(['role' => 'admin', 'email_verified_at' => now()])->save();
        }

        $evento = Evento::updateOrCreate(['slug' => 'semana-de-tecnologia-2026'], [
            'titulo' => 'Semana de Tecnologia e Inovação 2026',
            'descricao' => 'Palestras, minicursos e oficinas sobre tecnologia, inovação e sustentabilidade, '
                . 'promovidos pelo campus Óbidos do IFPA.',
            'data_inicio' => now()->addDays(1)->setTime(8, 0),
            'data_fim' => now()->addDays(3)->setTime(18, 0),
            'local' => 'IFPA Campus Óbidos - Auditório Principal',
            'criado_por' => $admin->id,
            'latitude' => '-1.9183',
            'longitude' => '-55.5186',
            'raio_permitido' => 5000,
            'cert_cabecalho' => "MINISTÉRIO DA EDUCAÇÃO\nINSTITUTO FEDERAL DO PARÁ - CAMPUS ÓBIDOS",
            'cert_corpo' => 'Certificamos que <b>{PARTICIPANTE}</b>, CPF <b>{CPF}</b>, participou da atividade <b>{ATIVIDADE}</b> '
                . 'do evento <b>{EVENTO}</b>, em {DATA_EVENTO}, como <b>{TIPO_PARTICIPACAO}</b>, '
                . 'com carga horária de <b>{CARGA_HORARIA} horas</b>.',
            'cert_rodape' => 'A autenticidade deste certificado pode ser verificada via QR Code.',
        ]);

        $evento->assinaturas()->delete();
        $evento->assinaturas()->createMany([
            ['nome' => 'Prof. Dr. João Silva', 'cargo' => 'Diretor-Geral do Campus Óbidos'],
            ['nome' => 'Profa. Maria Souza', 'cargo' => 'Coordenadora do Evento'],
        ]);

        // Atividades (passadas, em andamento e futuras)
        $defs = [
            ['Abertura e Palestra Magna: IA na Educação', 'palestra', 2, 200, 1, 8],
            ['Minicurso de Laravel do Zero', 'minicurso', 8, 30, 1, 14],
            ['Oficina de Arduino e IoT', 'oficina', 4, 20, 2, 8],
            ['Mesa-Redonda: Carreiras em TI', 'mesa_redonda', 2, 100, 2, 14],
            ['Palestra: Sustentabilidade e Tecnologia', 'palestra', 2, 150, 3, 8],
            ['Encerramento e Premiação', 'outro', 1, 200, 3, 16],
        ];
        $atividades = collect();
        foreach ($defs as [$titulo, $tipo, $ch, $vagas, $dia, $hora]) {
            $inicio = now()->addDays($dia)->setTime($hora, 0);
            $atividades->push(Atividade::updateOrCreate(
                ['evento_id' => $evento->id, 'titulo' => $titulo],
                [
                    'tipo' => $tipo,
                    'carga_horaria' => $ch,
                    'vagas' => $vagas,
                    'data_inicio' => $inicio,
                    'data_fim' => (clone $inicio)->addHours($ch),
                ] + (Atividade::where('evento_id', $evento->id)->where('titulo', $titulo)->exists()
                    ? [] : ['token_frequencia' => Str::random(32)])
            ));
        }

        // Participantes
        $turmaIds = Turma::pluck('id');
        $nomes = [
            'Ana Beatriz Lima', 'Bruno Carvalho', 'Carla Mendes', 'Daniel Ferreira', 'Eduarda Costa',
            'Felipe Santos', 'Gabriela Rocha', 'Henrique Alves', 'Isabela Nogueira', 'João Pedro Ramos',
            'Karina Oliveira', 'Lucas Monteiro', 'Mariana Teixeira', 'Nicolas Barros', 'Olívia Pinheiro',
            'Paulo Ricardo', 'Queila Martins', 'Rafael Duarte', 'Sofia Andrade', 'Thiago Moura',
        ];
        $participantes = collect();
        foreach ($nomes as $i => $nome) {
            $cpf = $this->cpf(100000000 + $i * 7919);
            $vinculo = $i < 14 ? 'aluno' : ($i < 18 ? 'servidor' : 'externo');
            $participantes->push(Participante::updateOrCreate(['cpf' => $cpf], [
                'nome_completo' => $nome,
                'email' => Str::slug($nome, '.') . '@exemplo.com',
                'tipo_vinculo' => $vinculo,
                'matricula' => $vinculo === 'externo' ? null : '2026' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'turma_id' => $vinculo === 'aluno' ? $turmaIds->random() : null,
            ]));
        }
        // Inscrito de teste (login) também participa
        $inscritoTeste = Participante::where('email', 'inscrito@teste.com')->first();
        if ($inscritoTeste) {
            $participantes->push($inscritoTeste);
        }

        foreach ($participantes as $i => $p) {
            Inscricao::firstOrCreate(
                ['evento_id' => $evento->id, 'participante_id' => $p->id],
                ['data_inscricao' => now()->subDays(rand(1, 10)), 'checkin_at' => $i % 3 === 0 ? now()->subHours(rand(1, 5)) : null]
            );

            // Inscrição em 3 atividades
            foreach ($atividades->shuffle()->take(3) as $a) {
                $p->atividadesInscritas()->syncWithoutDetaching([$a->id]);
            }
        }

        // Frequências (presenças) com hash de validação para certificados
        $tipos = ['ouvinte', 'ouvinte', 'ouvinte', 'palestrante', 'monitor'];
        foreach ($atividades->take(3) as $a) {
            foreach ($participantes->take(14) as $j => $p) {
                Frequencia::firstOrCreate(
                    ['participante_id' => $p->id, 'atividade_id' => $a->id],
                    [
                        'data_registro' => now()->subHours(rand(1, 48)),
                        'certificado_emitido' => $j % 2 === 0,
                        'hash_validacao' => strtoupper(Str::random(12)),
                        'tipo_participacao' => $j === 0 ? 'palestrante' : $tipos[$j % count($tipos)],
                    ]
                );
            }
        }

        // Equipe
        foreach ([[15, 'Coordenador', 40], [16, 'Monitor', 20], [17, 'Apoio', 20]] as [$idx, $funcao, $ch]) {
            Equipe::firstOrCreate(
                ['evento_id' => $evento->id, 'participante_id' => $participantes[$idx]->id],
                ['funcao' => $funcao, 'carga_horaria_trabalho' => $ch]
            );
        }

        $this->command?->info("Evento: /e/{$evento->slug} | Tokens de frequência: "
            . $atividades->pluck('token_frequencia')->implode(', '));
    }

    private function cpf(int $base): string
    {
        $d = str_split(str_pad((string) $base, 9, '0', STR_PAD_LEFT));
        for ($t = 9; $t < 11; $t++) {
            $s = 0;
            for ($i = 0; $i < $t; $i++) {
                $s += $d[$i] * (($t + 1) - $i);
            }
            $d[$t] = (($s * 10) % 11) % 10;
        }
        $n = implode('', $d);
        return substr($n, 0, 3) . '.' . substr($n, 3, 3) . '.' . substr($n, 6, 3) . '-' . substr($n, 9, 2);
    }
}
