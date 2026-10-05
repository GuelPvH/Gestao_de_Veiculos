<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthController::class, 'login'])->name('access.index');
    Route::get('/entrar', [AuthController::class, 'login'])->name('login');
    Route::post('/entrar', [AuthController::class, 'authenticate'])->name('login.submit');
    Route::get('/recuperar-acesso', [PasswordController::class, 'recover'])->name('recovery.index');
    Route::post('/recuperar-acesso', [PasswordController::class, 'sendRecovery'])->name('recovery.send');
    Route::get('/redefinir-senha/{token}', [PasswordController::class, 'reset'])->name('recovery.reset');
    Route::post('/redefinir-senha/{token}', [PasswordController::class, 'consume'])->name('recovery.consume');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/sair', [AuthController::class, 'logout'])->name('logout');
    Route::get('/acesso-restrito', [AuthController::class, 'restricted'])->name('access.restricted');
    Route::middleware('fleet.session')->group(function (): void {
        Route::get('/selecionar-perfil', [AuthController::class, 'profiles'])->name('profiles.index');
        Route::post('/selecionar-perfil', [AuthController::class, 'select'])->name('profiles.select');
        Route::get('/alterar-senha', [PasswordController::class, 'edit'])->name('password.edit');
        Route::post('/alterar-senha', [PasswordController::class, 'update'])->name('password.update');
    });
});
Route::middleware(['auth', 'fleet.session', 'fleet.profile', 'fleet.enabled'])->get('/painel', [DashboardController::class, 'index'])->name('dashboard');

Route::middleware(['auth', 'fleet.session', 'fleet.profile', 'fleet.enabled'])->group(function (): void {
    foreach (['despesas' => 'financeiro/despesas', 'multas' => 'financeiro/multas', 'usuarios' => 'administracao/usuarios', 'perfis' => 'administracao/perfis', 'rotas' => 'administracao/rotas', 'auditoria' => 'administracao/auditoria', 'configuracao' => 'administracao/configuracoes'] as $origem => $destino) {
        Route::redirect('/'.$origem, '/'.$destino);
    }
    Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
    Route::get('/administracao/configuracoes', [ConfigurationController::class, 'index'])->name('configuration.index');
    Route::get('/conta', [AccountController::class, 'index'])->name('account.index');
    Route::get('/notificacoes', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/relatorios', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/apresentacao', [ReviewController::class, 'index'])->name('review.index');
    foreach (config('screens') as $codigo => $tela) {
        Route::get('/'.$tela['url'], [RecordController::class, 'index'])->defaults('tela', $codigo)->name($codigo.'.index');
        if ($tela['create'] ?? false) {
            Route::get('/'.$tela['url'].'/novo', [RecordController::class, 'create'])->defaults('tela', $codigo)->name($codigo.'.create');
        }
        Route::get('/'.$tela['url'].'/{registro}', [RecordController::class, 'show'])->whereNumber('registro')->defaults('tela', $codigo)->name($codigo.'.show');
        Route::get('/'.$tela['url'].'/{registro}/{acao}', [RecordController::class, 'operation'])->whereNumber('registro')->defaults('tela', $codigo)->name($codigo.'.operation');
    }
});
