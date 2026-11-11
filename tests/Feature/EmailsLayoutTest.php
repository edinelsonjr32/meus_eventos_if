<?php

use App\Mail\ApresentacaoAvaliadorAtribuidoMail;
use App\Mail\AutorizacaoPublicacaoMail;
use App\Mail\AvaliacaoLembreteMail;
use App\Mail\AvaliadorAtribuidoMail;
use App\Mail\CertificadoTrabalhoMail;
use App\Mail\TrabalhoDecisaoMail;
use App\Mail\TrabalhoReenviadoMail;
use App\Mail\TrabalhoStatusMail;
use App\Mail\TrabalhoSubmetidoMail;
use App\Models\ApresentacaoAvaliacao;
use App\Models\CertificadoTrabalho;
use App\Models\TrabalhoAvaliacao;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Mail::fake();
    $this->dono = mkUser('admin');
    $this->evento = mkEvento($this->dono);
    $this->autor = mkUser('user', 'autora@x.com');
    $this->trabalho = mkTrabalho($this->evento, $this->autor, ['titulo' => 'Águas & "Igarapés" <b>do Pará</b>'], 'aprovado');
    $this->avaliador = mkUser('user', 'aval@x.com');
});

/** Todos os e-mails usam a moldura padrão: marca, rodapé e conteúdo escapado. */
function emailsDoSistema($t): array
{
    $aval = TrabalhoAvaliacao::create(['trabalho_id' => $t->id, 'avaliador_id' => $t->submetido_por, 'rodada' => 1, 'status' => 'pendente', 'atribuido_por' => $t->submetido_por, 'prazo' => now()->addDays(3)]);
    $ap = ApresentacaoAvaliacao::create(['trabalho_id' => $t->id, 'avaliador_id' => $t->submetido_por, 'status' => 'pendente', 'atribuido_por' => $t->submetido_por, 'prazo' => now()->addDays(3)]);
    $t->update(['decisao_justificativa' => "Linha 1\nLinha 2 <script>alert(1)</script>", 'prazo_correcao' => now()->addDays(5)]);
    $t->autores()->create(['nome_completo' => 'Autora Teste', 'tipo_autor' => 'autor_principal', 'apresentador' => true, 'ordem' => 1]);
    $cert = CertificadoTrabalho::create(['trabalho_id' => $t->id, 'trabalho_autor_id' => $t->autores()->first()->id, 'hash_validacao' => str_repeat('ab', 16), 'tipo' => 'apresentador', 'apresentador' => true, 'emitido_em' => now()]);
    $t = $t->fresh();

    return [
        'submetido' => new TrabalhoSubmetidoMail($t),
        'status' => new TrabalhoStatusMail($t, 'Ajustes solicitados', "Corrija a metodologia.\nObrigado.", true),
        'decisao-aprovado' => new TrabalhoDecisaoMail($t, 'aprovado'),
        'decisao-ressalvas' => new TrabalhoDecisaoMail($t, 'aprovado_com_ressalvas'),
        'decisao-reprovado' => new TrabalhoDecisaoMail($t, 'reprovado'),
        'reenviado' => new TrabalhoReenviadoMail($t, 'aprovado'),
        'avaliador' => new AvaliadorAtribuidoMail($t, $aval),
        'apresentacao' => new ApresentacaoAvaliadorAtribuidoMail($t, $ap),
        'lembrete' => new AvaliacaoLembreteMail($aval),
        'certificado' => new CertificadoTrabalhoMail($cert),
        'autorizacao' => new AutorizacaoPublicacaoMail($t),
    ];
}

it('todos os e-mails do sistema usam a moldura padrão (marca, rodapé, botão) e escapam o conteúdo', function () {
    foreach (emailsDoSistema($this->trabalho) as $nome => $mailable) {
        $html = $mailable->render();

        expect($html)->toContain('Meus Eventos IF')->toContain('Instituto Federal do Pará')->toContain('mensagem automática')
            ->toContain('Política de privacidade')->toContain('<table')->toContain('max-width: 620px')   // responsivo
            ->not->toContain('<script>alert(1)</script>')->not->toContain('<b>do Pará</b>');   // texto de usuário nunca vira HTML
    }
});

it('os e-mails mostram os dados importantes e um botão de ação', function () {
    $e = emailsDoSistema($this->trabalho);

    expect($e['submetido']->render())->toContain('Trabalho recebido com sucesso')->toContain($this->trabalho->codigo)->toContain('Acompanhar meu trabalho');
    expect($e['decisao-aprovado']->render())->toContain('Parabéns')->toContain('Ver resultado completo');
    expect($e['decisao-ressalvas']->render())->toContain('aprovado com ressalvas')->toContain('versão final corrigida')->toContain(now()->addDays(5)->format('d/m/Y'));
    expect($e['decisao-reprovado']->render())->toContain('não foi aprovado');
    expect($e['status']->render())->toContain('Ajustar e reenviar')->toContain('Corrija a metodologia.');
    expect($e['certificado']->render())->toContain('Baixar certificado (PDF)')->toContain(str_repeat('ab', 16));
    expect($e['lembrete']->render())->toContain('Abrir avaliação')->toContain(now()->addDays(3)->format('d/m/Y'));
    expect($e['autorizacao']->render())->toContain('Abrir meu trabalho e autorizar');
    expect($e['reenviado']->render())->toContain('Abrir na gestão de trabalhos');
});

it('o e-mail de redefinição de senha é em português e usa a mesma moldura', function () {
    Notification::fake();
    $this->post(route('password.email'), ['email' => $this->autor->email]);

    Notification::assertSentTo($this->autor, \Illuminate\Auth\Notifications\ResetPassword::class, function ($n) {
        $msg = $n->toMail($this->autor);
        $html = view($msg->view, $msg->viewData)->render();

        return $msg->subject === 'Redefinição de senha — ' . config('app.name')
            && str_contains($html, 'Criar nova senha') && str_contains($html, 'expira em') && str_contains($html, 'Meus Eventos IF')
            && str_contains($html, '/reset-password/');
    });
});
