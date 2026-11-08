<?php

namespace App\Console\Commands;

use App\Services\Lgpd\TitularService;
use Illuminate\Console\Command;

class LgpdAnonimizar extends Command
{
    protected $signature = 'lgpd:anonimizar {identificador : E-mail ou CPF do titular} {--confirmar : Executa sem perguntar}';

    protected $description = 'LGPD: anonimiza os dados de um titular (conta, participante e autorias). Ação IRREVERSÍVEL';

    public function handle(TitularService $servico): int
    {
        $identificador = $this->argument('identificador');

        ['email' => $email, 'cpf' => $cpf] = TitularService::normalizar($identificador);
        if (!$email && !$cpf) {
            $this->error('Informe um e-mail válido ou um CPF válido.');

            return self::FAILURE;
        }

        $alvo = $servico->localizar($identificador);
        if (!$servico->encontrou($alvo)) {
            $this->warn('Nenhum dado encontrado para este titular.');

            return self::FAILURE;
        }

        $this->table(['Contas', 'Participantes', 'Autorias em trabalhos'], [[$alvo['usuarios']->count(), $alvo['participantes']->count(), $alvo['autorias']->count()]]);
        $this->line('Os registros acadêmicos (trabalhos, notas, presenças) são mantidos sem identificar a pessoa; certificados em nome do titular são revogados.');

        if (!$this->option('confirmar') && !$this->confirm('Anonimizar estes dados? Não há como desfazer.')) {
            $this->line('Cancelado.');

            return self::FAILURE;
        }

        $r = $servico->anonimizar($identificador);
        $this->info("Concluído: {$r['contas']} conta(s), {$r['participantes']} participante(s), {$r['autorias']} autoria(s), {$r['certificados_revogados']} certificado(s) revogado(s).");
        $this->line('Lembrete: backups antigos ainda contêm os dados originais até serem rotacionados (veja docs/lgpd.md).');

        return self::SUCCESS;
    }
}
