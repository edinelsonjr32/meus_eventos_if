<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Controllers Administrativos
use App\Http\Controllers\Admin\EventoController;
use App\Http\Controllers\Admin\AtividadeController;
use App\Http\Controllers\Admin\EquipeController;
use App\Http\Controllers\Admin\CredenciamentoController;

// Controllers Públicos
use App\Http\Controllers\Publico\FrequenciaController;
use App\Http\Controllers\Publico\CertificadoController;
use App\Http\Controllers\Publico\EventoPublicoController;

// Controller do Inscrito (Área Logada)
use App\Http\Controllers\Inscrito\AgendaController;
use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| Rotas Públicas (Acesso livre)
|--------------------------------------------------------------------------
*/

// Política de Privacidade (LGPD)
Route::view('/privacidade', 'privacidade')->name('privacidade');

// Portal de Eventos (Landing Page Principal)
Route::get('/', function () {
    // Busca os próximos 9 eventos ativos ou futuros
    $proximosEventos = \App\Models\Evento::where('data_fim', '>=', now())
        // eventos criados por contas fictícias de teste não aparecem no portal público
        ->whereNotIn('criado_por', \App\Models\User::where('email', 'like', '%@teste.com')->select('id'))
        ->orderBy('data_inicio', 'asc')
        ->take(9)
        ->get();
    return view('welcome', compact('proximosEventos'));
})->name('home');

// Anais de Trabalhos (públicos somente após a publicação pela gestão)
Route::prefix('e/{slug}/anais')->name('anais.')->middleware('throttle:anais')->group(function () {
    $c = \App\Http\Controllers\Publico\AnaisController::class;
    Route::get('/', [$c, 'index'])->name('index');
    Route::get('/pdf', [$c, 'pdfAnais'])->name('pdf');
    Route::get('/{codigo}', [$c, 'show'])->name('show');
    Route::get('/{codigo}/pdf', [$c, 'pdfTrabalho'])->name('trabalho.pdf');
    Route::post('/{codigo}/certificado', [$c, 'certificado'])->middleware('throttle:anais-certificado')->name('certificado');
});

// Landing Pages de Eventos Específicos & Inscrição
Route::prefix('e')->group(function () {
    Route::get('/{slug}', [EventoPublicoController::class, 'show'])->name('evento.publico.show');
    // A inscrição exige conta: quem não está logado é levado ao login/cadastro e volta ao evento depois
    Route::post('/{slug}', [EventoPublicoController::class, 'store'])->middleware('auth')->name('evento.publico.store');
    Route::get('/{slug}/inscrever', [EventoPublicoController::class, 'inscrever'])->middleware('auth')->name('evento.publico.inscrever');
    Route::get('/{slug}/acesso/{modo}', [EventoPublicoController::class, 'acesso'])->where('modo', 'entrar|cadastrar')->name('evento.publico.acesso');
    Route::get('/{slug}/confirmado', [EventoPublicoController::class, 'sucesso'])->name('evento.publico.sucesso');
});

// Registro de Frequência (QR Code)
Route::get('/frequencia/sucesso', function () {
    return view('publico.sucesso');
})->name('frequencia.sucesso');

Route::get('/frequencia/{token}', [FrequenciaController::class, 'index'])->name('frequencia.form');
Route::get('/frequencia/{token}/csrf', [FrequenciaController::class, 'csrf'])->name('frequencia.csrf');
Route::get('/frequencia/{token}/acesso/{modo}', [FrequenciaController::class, 'acesso'])->where('modo', 'entrar|cadastrar')->name('frequencia.acesso');
// A presença exige conta: sem login a requisição é barrada (JSON recebe 401)
Route::post('/frequencia/{token}', [FrequenciaController::class, 'store'])->middleware('auth')->name('frequencia.store');

// Certificados de trabalhos (código aleatório; limite de requisições contra enumeração)
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/certificado-trabalho/validar/{hash}', [\App\Http\Controllers\Publico\CertificadoTrabalhoController::class, 'validar'])->name('certificado.trabalho.validar');
    Route::get('/certificado-trabalho/download/{hash}', [\App\Http\Controllers\Publico\CertificadoTrabalhoController::class, 'download'])->name('certificado.trabalho.download');
    Route::get('/certificado-premiacao/validar/{hash}', [\App\Http\Controllers\Publico\CertificadoPremiacaoController::class, 'validar'])->name('certificado.premiacao.validar');
    Route::get('/certificado-equipe/validar/{hash}', [\App\Http\Controllers\Publico\CertificadoEquipeController::class, 'validar'])->name('certificado.equipe.validar');
    Route::get('/certificado-equipe/download/{hash}', [\App\Http\Controllers\Publico\CertificadoEquipeController::class, 'download'])->name('certificado.equipe.download');
    Route::get('/certificado-premiacao/download/{hash}', [\App\Http\Controllers\Publico\CertificadoPremiacaoController::class, 'download'])->name('certificado.premiacao.download');
});

// Validação e Download de Certificados
Route::get('/certificado/validar/{hash}', [CertificadoController::class, 'validar'])->name('certificado.validar');
Route::get('/certificado/download/{hash}', [CertificadoController::class, 'download'])->name('certificado.download');


/*
|--------------------------------------------------------------------------
| Rotas Autenticadas (Inscritos e Admins)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {

    // Redirecionamento Inteligente (Roteador de Dashboard)
    // Decide para onde o usuário vai baseada no papel (role)
    Route::get('/dashboard', function () {
        if (Auth::user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('inscrito.dashboard');
    })->name('dashboard');

    // --- ÁREA DO INSCRITO (Minha Conta) ---
    Route::prefix('minha-area')->name('inscrito.')->group(function () {
        Route::get('/', [AgendaController::class, 'index'])->name('dashboard');
        Route::post('/atividade/{atividade}/toggle', [AgendaController::class, 'toggleAtividade'])->name('atividade.toggle');
    });

    // --- TRABALHOS (autor) ---
    Route::get('/minha-area/trabalhos', [\App\Http\Controllers\TrabalhoController::class, 'index'])->name('trabalhos.index');
    Route::get('/e/{slug}/trabalhos/submeter', [\App\Http\Controllers\TrabalhoController::class, 'create'])->name('trabalhos.create');
    Route::post('/e/{slug}/trabalhos', [\App\Http\Controllers\TrabalhoController::class, 'store'])->name('trabalhos.store');
    Route::get('/trabalhos/{trabalho}', [\App\Http\Controllers\TrabalhoController::class, 'show'])->name('trabalhos.show');
    Route::get('/trabalhos/{trabalho}/editar', [\App\Http\Controllers\TrabalhoController::class, 'edit'])->name('trabalhos.edit');
    Route::put('/trabalhos/{trabalho}', [\App\Http\Controllers\TrabalhoController::class, 'update'])->name('trabalhos.update');
    Route::get('/trabalhos/{trabalho}/arquivo', [\App\Http\Controllers\TrabalhoController::class, 'arquivo'])->name('trabalhos.arquivo');
    Route::post('/trabalhos/{trabalho}/autorizacao-publicacao', [\App\Http\Controllers\TrabalhoController::class, 'autorizarPublicacao'])->name('trabalhos.autorizacao-publicacao');
    Route::post('/trabalhos/{trabalho}/versao-final', [\App\Http\Controllers\TrabalhoController::class, 'enviarVersaoFinal'])->name('trabalhos.versao-final');
    Route::post('/trabalhos/{trabalho}/retirar', [\App\Http\Controllers\TrabalhoController::class, 'retirar'])->name('trabalhos.retirar');

    // --- AVALIADOR (somente as próprias avaliações; autorização por Policy) ---
    Route::prefix('avaliador/avaliacoes')->name('avaliador.avaliacoes.')->group(function () {
        $c = \App\Http\Controllers\Avaliador\AvaliacaoController::class;
        Route::get('/', [$c, 'index'])->name('index');
        Route::get('/{avaliacao}', [$c, 'show'])->name('show');
        Route::get('/{avaliacao}/arquivo', [$c, 'arquivo'])->name('arquivo');
        Route::post('/{avaliacao}/salvar', [$c, 'salvar'])->name('salvar');
        Route::post('/{avaliacao}/concluir', [$c, 'concluir'])->name('concluir');
        Route::post('/{avaliacao}/recusar', [$c, 'recusar'])->name('recusar');
    });

    Route::prefix('avaliador/apresentacoes')->name('avaliador.apresentacoes.')->group(function () {
        $c = \App\Http\Controllers\Avaliador\ApresentacaoAvaliacaoController::class;
        Route::get('/', [$c, 'index'])->name('index');
        Route::get('/{avaliacao}', [$c, 'show'])->name('show');
        Route::get('/{avaliacao}/arquivo', [$c, 'arquivo'])->name('arquivo');
        Route::post('/{avaliacao}/salvar', [$c, 'salvar'])->name('salvar');
        Route::post('/{avaliacao}/concluir', [$c, 'concluir'])->name('concluir');
        Route::post('/{avaliacao}/recusar', [$c, 'recusar'])->name('recusar');
    });

    // --- GESTÃO DE TRABALHOS (gestor de trabalhos, dono e co-administradores; autorização por Policy) ---
    Route::prefix('gestao/trabalhos')->name('gestao.trabalhos.')->group(function () {
        $c = \App\Http\Controllers\Gestao\TrabalhoGestaoController::class;
        Route::get('/', [$c, 'eventos'])->name('eventos');
        Route::get('/evento/{evento}', [$c, 'index'])->name('index');
        Route::get('/evento/{evento}/config', [$c, 'config'])->name('config');
        Route::put('/evento/{evento}/config', [$c, 'updateConfig'])->name('config.update');
        $a = \App\Http\Controllers\Gestao\ApresentacaoCertificadoController::class;
        Route::get('/evento/{evento}/certificados', [$a, 'index'])->name('certificados.index');
        Route::post('/evento/{evento}/certificados/lote', [$a, 'lote'])->name('certificados.lote');
        Route::post('/certificados/{certificado}/revogar', [$a, 'revogar'])->name('certificados.revogar');
        $n = \App\Http\Controllers\Gestao\AnaisGestaoController::class;
        Route::get('/evento/{evento}/anais', [$n, 'edit'])->name('anais.edit');
        Route::put('/evento/{evento}/anais', [$n, 'update'])->name('anais.update');
        Route::post('/evento/{evento}/anais/gerar', [$n, 'gerar'])->name('anais.gerar');
        Route::get('/evento/{evento}/anais/pdf', [$n, 'pdf'])->name('anais.pdf');
        Route::post('/evento/{evento}/anais/publicar', [$n, 'publicar'])->name('anais.publicar');
        Route::post('/evento/{evento}/anais/despublicar', [$n, 'despublicar'])->name('anais.despublicar');
        Route::post('/evento/{evento}/anais/dados-publicacao', [$n, 'dadosPublicacao'])->name('anais.dados-publicacao');
        Route::post('/evento/{evento}/anais/solicitar-autorizacao', [$n, 'solicitarAutorizacao'])->name('anais.solicitar-autorizacao');
        Route::get('/evento/{evento}/exportar', [$c, 'exportar'])->name('exportar');
        Route::post('/evento/{evento}/lote', [$c, 'lote'])->name('lote');
        Route::get('/evento/{evento}/ranking', [$c, 'ranking'])->name('ranking');
        $pr = \App\Http\Controllers\Gestao\PremiacaoGestaoController::class;
        Route::get('/evento/{evento}/premiacao', [$pr, 'index'])->name('premiacao.index');
        Route::put('/evento/{evento}/premiacao', [$pr, 'salvar'])->name('premiacao.salvar');
        Route::post('/evento/{evento}/premiacao/sugerir', [$pr, 'sugerir'])->name('premiacao.sugerir');
        Route::post('/evento/{evento}/premiacao/divulgar', [$pr, 'divulgar'])->name('premiacao.divulgar');
        Route::post('/evento/{evento}/premiacao/ocultar', [$pr, 'ocultar'])->name('premiacao.ocultar');
        Route::put('/evento/{evento}/premiacao/modelo', [$pr, 'modelo'])->name('premiacao.modelo');
        Route::get('/evento/{evento}/premiacao/certificados', [$pr, 'imprimir'])->name('premiacao.imprimir');
        Route::get('/premiacao/certificado/{certificado}', [$pr, 'certificado'])->name('premiacao.certificado');
        Route::get('/evento/{evento}/exportar-notas', [$c, 'exportarNotas'])->name('exportar-notas');
        $ap = \App\Http\Controllers\Gestao\ApresentacaoAvaliacaoGestaoController::class;
        Route::get('/evento/{evento}/apresentacoes', [$ap, 'index'])->name('apresentacoes.index');
        Route::put('/evento/{evento}/apresentacoes/config', [$ap, 'salvarConfig'])->name('apresentacoes.config');
        Route::post('/evento/{evento}/apresentacoes/lote', [$ap, 'lote'])->name('apresentacoes.lote');
        Route::post('/apresentacoes/{trabalho}/avaliadores', [$ap, 'atribuir'])->name('apresentacoes.atribuir');
        Route::delete('/apresentacoes/avaliacao/{avaliacao}', [$ap, 'remover'])->name('apresentacoes.remover');
        Route::get('/evento/{evento}/novo', [$c, 'novo'])->name('novo');
        Route::post('/evento/{evento}/novo', [$c, 'criar'])->name('criar');
        Route::get('/{trabalho}', [$c, 'show'])->name('show');
        Route::get('/{trabalho}/editar', [$c, 'editar'])->name('editar');
        Route::put('/{trabalho}', [$c, 'atualizar'])->name('atualizar');
        Route::post('/{trabalho}/triagem', [$c, 'triagem'])->name('triagem');
        Route::post('/{trabalho}/avaliadores', [$c, 'atribuir'])->name('avaliadores.store');
        Route::post('/{trabalho}/desempate', [$c, 'desempate'])->name('desempate');
        Route::post('/{trabalho}/apresentador', [$c, 'definirApresentador'])->name('apresentador');
        Route::post('/{trabalho}/encerrar-avaliacao', [$c, 'encerrarAvaliacao'])->name('encerrar');
        Route::post('/{trabalho}/decisao', [$c, 'decisao'])->name('decisao');
        Route::post('/{trabalho}/prorrogar-correcao', [$c, 'prorrogarCorrecao'])->name('prorrogar');
        Route::post('/{trabalho}/reavaliar', [$c, 'reavaliar'])->name('reavaliar');
        Route::post('/{trabalho}/arquivo-final', [$n, 'arquivoFinal'])->name('arquivo-final');
        Route::get('/{trabalho}/arquivo-final', [$n, 'baixarFinal'])->name('arquivo-final.baixar');
        Route::post('/{trabalho}/apresentacao', [$a, 'apresentacao'])->name('apresentacao');
        Route::post('/{trabalho}/certificados', [$a, 'emitir'])->name('certificados.emitir');
        Route::delete('/{trabalho}/avaliadores/{avaliacao}', [$c, 'removerAvaliador'])->name('avaliadores.destroy');
    });

    // --- PERFIL DO USUÁRIO ---
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


/*
|--------------------------------------------------------------------------
| Credenciamento / recepção (check-in): administrador do evento OU usuário com o papel "Recepção" no evento.
| Mantém as URLs /admin/eventos/{evento}/credenciamento/* e os nomes admin.*; a autorização é a Policy "credenciar".
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    // --- Credenciamento (Modo Quiosque) ---
    Route::prefix('eventos/{evento}/credenciamento')->name('eventos.credenciamento.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'index'])->name('index');
        Route::get('/search', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'search'])->name('search');
        Route::get('/stats', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'stats'])->name('stats');
        Route::get('/lista', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'lista'])->name('lista');
        Route::get('/lista/exportar', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'exportarLista'])->name('lista.exportar');
        Route::post('/store', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'store'])->name('store');

        // Ações
        Route::post('/checkin/{participante}', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'checkin'])->name('checkin');
        Route::get('/etiqueta/{participante}', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'etiqueta'])->name('etiqueta');

        // NOVIDADES (Substitui o Modal)
        Route::get('/cadastrar', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'create'])->name('create');
        Route::post('/cadastrar', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'storeManual'])->name('storeManual');
    });
});

Route::middleware(['auth', 'verified'])->get('/recepcao', [\App\Http\Controllers\RecepcaoController::class, 'index'])->name('recepcao.index');

/*
|--------------------------------------------------------------------------
| Rotas Administrativas (Apenas Role 'admin')
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    // Lista/edita todos os usuários do sistema: somente superadmin (SUPERADMIN_EMAILS)
    Route::resource('users', \App\Http\Controllers\Admin\UserController::class)
        ->middleware('can:superadmin')
        ->names('users')
        ->except(['show']);

    Route::get('relatorios/frequencia', [\App\Http\Controllers\Admin\RelatorioController::class, 'index'])
        ->name('relatorios.frequencia.index');

    Route::post('relatorios/frequencia/exportar', [\App\Http\Controllers\Admin\RelatorioController::class, 'exportar'])
        ->name('relatorios.frequencia.exportar');

    // Dashboard Geral
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    // Gestão de Eventos (CRUD Completo)
    Route::resource('eventos', EventoController::class);

    // NOVIDADE: Rota dedicada para Certificado
    Route::get('eventos/{evento}/certificado', [App\Http\Controllers\Admin\EventoController::class, 'editCertificado'])->name('eventos.certificado.edit');
    Route::put('eventos/{evento}/certificado', [App\Http\Controllers\Admin\EventoController::class, 'updateCertificado'])->name('eventos.certificado.update');

    // Gestão de Atividades (Aninhada em Eventos)
    // Ex: admin.eventos.atividades.store
    Route::get('eventos/{evento}/atividades/qrcodes/pdf', [AtividadeController::class, 'qrcodesPdf'])->name('eventos.atividades.qrcodes');
    // Só os métodos que existem; quem digitar o endereço de listagem/criação/detalhe volta ao painel do evento (antes dava erro 500)
    Route::resource('eventos.atividades', AtividadeController::class)->shallow()->only(['store', 'edit', 'update', 'destroy']);
    Route::get('eventos/{evento}/atividades', fn (\App\Models\Evento $evento) => redirect()->route('admin.eventos.show', $evento));
    Route::get('eventos/{evento}/atividades/create', fn (\App\Models\Evento $evento) => redirect()->route('admin.eventos.show', $evento));
    Route::get('atividades/{atividade}', fn (\App\Models\Atividade $atividade) => redirect()->route('admin.eventos.show', $atividade->evento_id));

    // --- Gestão de Participantes (Lista de Presença) ---
    Route::prefix('atividades/{atividade}')->name('atividades.')->group(function () {
        // Visualizar Lista
        Route::get('/participantes', [AtividadeController::class, 'participantes'])->name('participantes');
        // Remover da atividade quem se inscreveu e ainda não registrou presença
        Route::delete('/inscritos/{participante}', [AtividadeController::class, 'destroyInscrito'])->name('inscritos.destroy');
        // Cadastro Manual (POST)
        Route::post('/participantes', [AtividadeController::class, 'storeManual'])->name('participantes.store');
        // Tela de Cadastro Manual (GET)
        Route::get('/manual', [AtividadeController::class, 'createManual'])->name('manual');
        // Exportar Excel/CSV
        Route::get('/exportar', [AtividadeController::class, 'exportar'])->name('exportar');
    });

    // Check-in Rápido (Converte inscrição em presença)
    Route::post('atividades/{atividade}/checkin/{participante}', [\App\Http\Controllers\Admin\AtividadeController::class, 'storeCheckin'])
        ->name('atividades.checkin');

    // Ações em Frequências Específicas
    Route::delete('frequencias/{id}', [AtividadeController::class, 'destroyFrequencia'])->name('frequencias.destroy');
    Route::patch('frequencias/{id}/role', [AtividadeController::class, 'updateRole'])->name('frequencias.role');

    // Busca de pessoas em tempo real (Select2) para equipe e papéis
    Route::get('eventos/{evento}/buscar-pessoas', \App\Http\Controllers\Admin\BuscaPessoaController::class)->middleware('throttle:60,1')->name('eventos.buscar-pessoas');

    // --- Gestão de Equipe / Staff ---
    Route::get('eventos/{evento}/equipe', [EquipeController::class, 'index'])->name('eventos.equipe.index');
    Route::post('eventos/{evento}/equipe', [EquipeController::class, 'store'])->name('eventos.equipe.store');
    Route::put('equipe/{equipe}', [EquipeController::class, 'update'])->name('equipe.update');
    Route::delete('equipe/{equipe}', [EquipeController::class, 'destroy'])->name('equipe.destroy');
    Route::post('equipe/{equipe}/certificado', [EquipeController::class, 'emitir'])->name('equipe.emitir');
    Route::post('equipe/{equipe}/certificado/revogar', [EquipeController::class, 'revogar'])->name('equipe.revogar');
    Route::get('equipe/{equipe}/certificado', [EquipeController::class, 'certificado'])->name('equipe.certificado');
    Route::post('eventos/{evento}/equipe/certificados', [EquipeController::class, 'emitirTodos'])->name('eventos.equipe.emitir-todos');
    Route::put('eventos/{evento}/equipe/modelo', [EquipeController::class, 'modelo'])->name('eventos.equipe.modelo');

    // --- Papéis por evento (co-administrador, gestor de trabalhos, avaliador) ---
    Route::get('eventos/{evento}/papeis', [\App\Http\Controllers\Admin\EventoPapelController::class, 'index'])->name('eventos.papeis.index');
    Route::post('eventos/{evento}/papeis', [\App\Http\Controllers\Admin\EventoPapelController::class, 'store'])->name('eventos.papeis.store');
    Route::delete('eventos/{evento}/papeis/{user}/{papel}', [\App\Http\Controllers\Admin\EventoPapelController::class, 'destroy'])->name('eventos.papeis.destroy');

    // Listagem de Inscritos Geral
    Route::get('eventos/{evento}/inscritos', [\App\Http\Controllers\Admin\EventoController::class, 'inscritos'])
        ->name('eventos.inscritos');

    Route::get('eventos/{evento}/inscritos/exportar', [\App\Http\Controllers\Admin\EventoController::class, 'exportarInscritos'])
        ->name('eventos.inscritos.exportar');
});

// Rotas de Autenticação do Breeze (Login, Register, Password Reset)
require __DIR__ . '/auth.php';
