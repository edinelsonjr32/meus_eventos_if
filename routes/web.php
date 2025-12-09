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

// Portal de Eventos (Landing Page Principal)
Route::get('/', function () {
    // Busca os próximos 9 eventos ativos ou futuros
    $proximosEventos = \App\Models\Evento::where('data_fim', '>=', now())
        ->orderBy('data_inicio', 'asc')
        ->take(9)
        ->get();
    return view('welcome', compact('proximosEventos'));
})->name('home');

// Landing Pages de Eventos Específicos & Inscrição
Route::prefix('e')->group(function () {
    Route::get('/{slug}', [EventoPublicoController::class, 'show'])->name('evento.publico.show');
    Route::post('/{slug}', [EventoPublicoController::class, 'store'])->name('evento.publico.store');
    Route::get('/{slug}/confirmado', [EventoPublicoController::class, 'sucesso'])->name('evento.publico.sucesso');
});

// Registro de Frequência (QR Code)
Route::get('/frequencia/sucesso', function () {
    return view('publico.sucesso');
})->name('frequencia.sucesso');

Route::get('/frequencia/{token}', [FrequenciaController::class, 'index'])->name('frequencia.form');
Route::post('/frequencia/{token}', [FrequenciaController::class, 'store'])->name('frequencia.store');

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

    // --- PERFIL DO USUÁRIO ---
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


/*
|--------------------------------------------------------------------------
| Rotas Administrativas (Apenas Role 'admin')
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    Route::resource('users', \App\Http\Controllers\Admin\UserController::class)
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

    // Gestão de Atividades (Aninhada em Eventos)
    // Ex: admin.eventos.atividades.store
    Route::resource('eventos.atividades', AtividadeController::class)->shallow();

    // --- Gestão de Participantes (Lista de Presença) ---
    Route::prefix('atividades/{atividade}')->name('atividades.')->group(function () {
        // Visualizar Lista
        Route::get('/participantes', [AtividadeController::class, 'participantes'])->name('participantes');
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

    // --- Gestão de Equipe / Staff ---
    Route::get('eventos/{evento}/equipe', [EquipeController::class, 'index'])->name('eventos.equipe.index');
    Route::post('eventos/{evento}/equipe', [EquipeController::class, 'store'])->name('eventos.equipe.store');
    Route::delete('equipe/{equipe}', [EquipeController::class, 'destroy'])->name('equipe.destroy');

    // --- Credenciamento (Modo Quiosque) ---
    Route::prefix('eventos/{evento}/credenciamento')->name('eventos.credenciamento.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'index'])->name('index');
        Route::get('/search', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'search'])->name('search');
        Route::post('/store', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'store'])->name('store');

        // Ações
        Route::post('/checkin/{participante}', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'checkin'])->name('checkin');
        Route::get('/etiqueta/{participante}', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'etiqueta'])->name('etiqueta');

        // NOVIDADES (Substitui o Modal)
        Route::get('/cadastrar', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'create'])->name('create');
        Route::post('/cadastrar', [\App\Http\Controllers\Admin\CredenciamentoController::class, 'storeManual'])->name('storeManual');
    });

    // Listagem de Inscritos Geral
    Route::get('eventos/{evento}/inscritos', [\App\Http\Controllers\Admin\EventoController::class, 'inscritos'])
        ->name('eventos.inscritos');

    Route::get('eventos/{evento}/inscritos/exportar', [\App\Http\Controllers\Admin\EventoController::class, 'exportarInscritos'])
        ->name('eventos.inscritos.exportar');
});

// Rotas de Autenticação do Breeze (Login, Register, Password Reset)
require __DIR__ . '/auth.php';
