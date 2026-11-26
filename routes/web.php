<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\IdentityController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FineController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SolicitacoesController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TrackerController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TyreController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/ativar-acesso', [IdentityController::class, 'activate'])->name('identity.activate');
Route::post('/ativar-acesso', [IdentityController::class, 'consumeActivation'])->name('identity.consumeActivation')->block();
Route::get('/confirmar-email', [IdentityController::class, 'verifyEmail'])->name('identity.verifyEmail');
Route::post('/confirmar-email', [IdentityController::class, 'consumeEmail'])->name('identity.consumeEmail')->block();

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthController::class, 'login'])->name('access.index');
    Route::get('/entrar', [AuthController::class, 'login'])->name('login');
    Route::post('/entrar', [AuthController::class, 'authenticate'])->name('login.submit');
    Route::get('/recuperar-acesso', [PasswordController::class, 'recover'])->name('recovery.index');
    Route::post('/recuperar-acesso', [PasswordController::class, 'recoverWithCode'])->name('recovery.verify')->block();
    Route::get('/redefinir-senha', [PasswordController::class, 'reset'])->name('recovery.reset');
    Route::post('/redefinir-senha', [PasswordController::class, 'consume'])->name('recovery.consume');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/convite/reenviar', [IdentityController::class, 'resend'])->name('identity.resend')->block();
    Route::post('/sair', [AuthController::class, 'logout'])->name('logout');
    Route::get('/acesso-restrito', [AuthController::class, 'restricted'])->name('access.restricted');
    Route::middleware('fleet.session')->group(function (): void {
        Route::post('/conta/email', [IdentityController::class, 'changeEmail'])->name('identity.changeEmail')->block();
        Route::get('/sessao/status', [SessionController::class, 'status'])->name('session.status');
        Route::post('/sessao/reautenticar', [SessionController::class, 'reauthenticate'])->name('session.reauthenticate')->block();
        Route::redirect('/seguranca/mfa', '/painel')->name('mfa.legacy');

        Route::get('/selecionar-perfil', [AuthController::class, 'profiles'])->name('profiles.index');
        Route::post('/selecionar-perfil', [AuthController::class, 'select'])->name('profiles.select');
        Route::get('/alterar-senha', [PasswordController::class, 'edit'])->name('password.edit');
        Route::post('/alterar-senha', [PasswordController::class, 'update'])->name('password.update');
    });
});

Route::middleware(['auth', 'fleet.session', 'fleet.profile', 'fleet.enabled'])->group(function (): void {
    foreach (['despesas' => 'financeiro/despesas', 'multas' => 'financeiro/multas', 'usuarios' => 'administracao/usuarios', 'perfis' => 'administracao/perfis', 'rotas' => 'administracao/rotas', 'auditoria' => 'administracao/auditoria', 'configuracao' => 'administracao/configuracoes'] as $origem => $destino) {
        Route::redirect('/'.$origem, '/'.$destino);
    }
    Route::get('/sistema/logo', [ConfigurationController::class, 'logo'])->name('configuration.logo');
    Route::get('/administracao/configuracoes', [ConfigurationController::class, 'index'])->name('configuration.index');
    Route::post('/administracao/configuracoes/exportar', [ConfigurationController::class, 'export'])->name('configuration.export');
    Route::post('/administracao/configuracoes', [ConfigurationController::class, 'update'])->name('configuration.update');
    Route::get('/administracao/cadastros', [CatalogController::class, 'index'])->name('catalogs.index');
    Route::get('/administracao/cadastros/fornecedores', [CatalogController::class, 'section'])->defaults('tipo', 'fornecedores')->name('catalogs.suppliers');
    Route::get('/administracao/cadastros/{tipo}', [CatalogController::class, 'section'])->where('tipo', 'unidades|categorias-veiculo|categorias-despesa|categorias-chamado|checklist|fornecedores')->name('catalogs.section');
    Route::post('/administracao/cadastros/{tipo}', [CatalogController::class, 'store'])->where('tipo', 'unidades|categorias-veiculo|categorias-despesa|categorias-chamado|checklist|fornecedores')->name('catalogs.store');
    Route::post('/administracao/cadastros/{tipo}/{registro}', [CatalogController::class, 'update'])->where('tipo', 'unidades|categorias-veiculo|categorias-despesa|categorias-chamado|checklist|fornecedores')->whereNumber('registro')->name('catalogs.update');
    Route::post('/administracao/usuarios/novo', [AdminController::class, 'storeUser'])->defaults('tela', 'users')->defaults('area', 'users')->defaults('operacao', 'store')->name('users.store');
    Route::post('/administracao/usuarios/{registro}/edit', [AdminController::class, 'updateUser'])->whereNumber('registro')->defaults('tela', 'users')->defaults('area', 'users')->defaults('operacao', 'update')->name('users.update');
    Route::post('/administracao/usuarios/{registro}/temporary', [AdminController::class, 'issueTemporaryCredential'])->whereNumber('registro')->defaults('tela', 'users')->defaults('area', 'users')->defaults('operacao', 'temporary')->name('users.temporary')->block();
    Route::post('/administracao/usuarios/{registro}/links', [AdminController::class, 'linkUser'])->whereNumber('registro')->defaults('tela', 'users')->defaults('area', 'users')->defaults('operacao', 'link')->name('users.link');
    Route::post('/administracao/usuarios/{registro}/vinculos/revogar', [AdminController::class, 'revokeLink'])->whereNumber('registro')->defaults('tela', 'users')->defaults('area', 'users')->defaults('operacao', 'revoke')->name('users.revoke');
    Route::post('/administracao/perfis/novo', [AdminController::class, 'storeRole'])->defaults('tela', 'roles')->defaults('area', 'roles')->defaults('operacao', 'store')->name('roles.store');
    Route::post('/administracao/perfis/{registro}/edit', [AdminController::class, 'updateRole'])->whereNumber('registro')->defaults('tela', 'roles')->defaults('area', 'roles')->defaults('operacao', 'update')->name('roles.update');
    Route::post('/administracao/perfis/{registro}/duplicate', [AdminController::class, 'duplicateRole'])->whereNumber('registro')->defaults('tela', 'roles')->defaults('area', 'roles')->defaults('operacao', 'duplicate')->name('roles.duplicate');
    Route::post('/administracao/perfis/{registro}/permissoes/conceder', [AdminController::class, 'grantRole'])->whereNumber('registro')->defaults('tela', 'roles')->defaults('area', 'roles')->defaults('operacao', 'grant')->name('roles.grant');
    Route::post('/administracao/perfis/{registro}/permissoes/revogar', [AdminController::class, 'revokeRole'])->whereNumber('registro')->defaults('tela', 'roles')->defaults('area', 'roles')->defaults('operacao', 'revoke')->name('roles.revoke');
    Route::post('/administracao/rotas/novo', [AdminController::class, 'storeRoute'])->defaults('tela', 'technical-routes')->defaults('area', 'technical-routes')->defaults('operacao', 'store')->name('technical-routes.store');
    Route::post('/administracao/rotas/{registro}/edit', [AdminController::class, 'updateRoute'])->whereNumber('registro')->defaults('tela', 'technical-routes')->defaults('area', 'technical-routes')->defaults('operacao', 'update')->name('technical-routes.update');
    Route::get('/apresentacao', [ReviewController::class, 'index'])->name('review.index');
    Route::get('/financeiro/multas', [FineController::class, 'index'])->defaults('tela', 'fines')->name('fines.index');
    Route::get('/financeiro/multas/novo', [FineController::class, 'create'])->defaults('tela', 'fines')->name('fines.create');
    Route::post('/financeiro/multas/novo', [FineController::class, 'store'])->defaults('tela', 'fines')->name('fines.store');
    Route::get('/financeiro/multas/{registro}/comprovantes/{comprovante}', [FineController::class, 'download'])->whereNumber('registro')->whereNumber('comprovante')->defaults('tela', 'fines')->name('fines.download');
    Route::get('/financeiro/multas/{registro}', [FineController::class, 'show'])->whereNumber('registro')->defaults('tela', 'fines')->name('fines.show');
    Route::get('/financeiro/multas/{registro}/{acao}', [FineController::class, 'operation'])->whereNumber('registro')->where('acao', 'edit|assign|proof|verify|correct|settle|dispute|cancel')->defaults('tela', 'fines')->name('fines.operation');
    Route::post('/financeiro/multas/{registro}/{acao}', [FineController::class, 'perform'])->whereNumber('registro')->where('acao', 'edit|assign|proof|verify|correct|settle|dispute|cancel')->defaults('tela', 'fines')->name('fines.perform');
    foreach (['expenses' => 'financeiro/despesas', 'fuel' => 'financeiro/abastecimentos', 'maintenance' => 'financeiro/manutencoes'] as $screen => $path) {
        Route::post('/'.$path.'/novo', [FinanceController::class, 'store'])->defaults('tela', $screen)->name($screen.'.store');
        Route::post('/'.$path.'/{registro}/{acao}', [FinanceController::class, 'perform'])->whereNumber('registro')->where('acao', match ($screen) {
            'expenses' => 'edit|submit|verify|pay|cancel',
            'fuel' => 'edit',
            default => 'edit|start|complete|cancel|item-add|item-edit|item-remove|link-expense',
        })->defaults('tela', $screen)->name($screen.'.perform');
        Route::get('/'.$path.'/{registro}/anexos/{anexo}', [FinanceController::class, 'download'])->whereNumber('registro')->whereNumber('anexo')->defaults('tela', $screen)->name($screen.'.download');
        if ($screen !== 'maintenance') {
            Route::get('/'.$path.'/{registro}/comprovante', [FinanceController::class, 'receipt'])->whereNumber('registro')->defaults('tela', $screen)->name($screen.'.receipt');
        }
    }

    Route::get('/financeiro/resumo', [FinanceController::class, 'summary'])->name('finance.summary');
    Route::get('/financeiro/pneus', [TyreController::class, 'index'])->name('tyres.index');
    Route::get('/financeiro/pneus/novo', [TyreController::class, 'create'])->name('tyres.create');
    Route::post('/financeiro/pneus/novo', [TyreController::class, 'store'])->defaults('acao', 'create')->name('tyres.store');
    Route::get('/financeiro/pneus/{registro}', [TyreController::class, 'show'])->whereNumber('registro')->name('tyres.show');
    Route::post('/financeiro/pneus/{registro}/instalar', [TyreController::class, 'install'])->whereNumber('registro')->defaults('acao', 'install')->name('tyres.install');
    Route::post('/financeiro/pneus/{registro}/instalacoes/{instalacao}/remover', [TyreController::class, 'remove'])->whereNumber('registro')->whereNumber('instalacao')->defaults('acao', 'remove')->name('tyres.remove');
    Route::post('/financeiro/pneus/{registro}/descartar', [TyreController::class, 'discard'])->whereNumber('registro')->defaults('acao', 'discard')->name('tyres.discard');

    foreach (config('screens') as $codigo => $tela) {
        if (in_array($codigo, ['fines', 'tickets', 'vehicles', 'requests', 'trips', 'monitoring'], true)) {
            continue;
        }
        Route::get('/'.$tela['url'], [RecordController::class, 'index'])->defaults('tela', $codigo)->name($codigo.'.index');
        if ($tela['create'] ?? false) {
            Route::get('/'.$tela['url'].'/novo', [RecordController::class, 'create'])->defaults('tela', $codigo)->name($codigo.'.create');
        }
        Route::get('/'.$tela['url'].'/{registro}', [RecordController::class, 'show'])->whereNumber('registro')->defaults('tela', $codigo)->name($codigo.'.show');
        Route::get('/'.$tela['url'].'/{registro}/{acao}', [RecordController::class, 'operation'])->whereNumber('registro')->defaults('tela', $codigo)->name($codigo.'.operation');
    }

    Route::prefix('solicitacoes')->as('solicitacoes.')->controller(SolicitacoesController::class)->group(function (): void {
        Route::get('', 'index')->defaults('tela', 'requests')->name('index');
        Route::get('novo', 'create')->defaults('tela', 'requests')->name('create');
        Route::post('novo', 'store')->defaults('tela', 'requests')->name('store');
        Route::get('{registro}/anexos/{anexo}', 'download')->whereNumber('registro')->whereNumber('anexo')->defaults('tela', 'requests')->name('download');
        Route::get('{registro}', 'show')->whereNumber('registro')->defaults('tela', 'requests')->name('show');
        Route::get('{registro}/edit', 'edit')->whereNumber('registro')->defaults('tela', 'requests')->name('edit');
        Route::patch('{registro}/edit', 'update')->whereNumber('registro')->defaults('tela', 'requests')->name('update');
        Route::post('{registro}/send', 'sendSubmit')->whereNumber('registro')->defaults('tela', 'requests')->name('send.submit');
        Route::post('{registro}/revision', 'revisionSubmit')->whereNumber('registro')->defaults('tela', 'requests')->name('revision.submit');
        Route::post('{registro}/approve', 'approveSubmit')->whereNumber('registro')->defaults('tela', 'requests')->name('approve.submit');
        Route::post('{registro}/deny', 'denySubmit')->whereNumber('registro')->defaults('tela', 'requests')->name('deny.submit');
        Route::post('{registro}/adjust', 'adjustSubmit')->whereNumber('registro')->defaults('tela', 'requests')->name('adjust.submit');
        Route::post('{registro}/cancel', 'cancelSubmit')->whereNumber('registro')->defaults('tela', 'requests')->name('cancel.submit');
    });

    Route::prefix('motoristas')->as('drivers.')->controller(DriverController::class)->group(function (): void {
        Route::get('', 'index')->name('index');
        Route::get('novo', 'create')->name('create');
        Route::post('novo', 'store')->name('store');
        Route::get('{registro}', 'show')->whereNumber('registro')->name('show');
        Route::get('{registro}/edit', 'edit')->whereNumber('registro')->name('edit');
        Route::post('{registro}/edit', 'update')->whereNumber('registro')->name('update');
        Route::get('{registro}/cnh', 'downloadCnh')->whereNumber('registro')->name('cnh');
    });

    Route::prefix('monitoramento/rastreadores')->as('trackers.')->controller(TrackerController::class)->group(function (): void {
        Route::get('', 'index')->name('index');
        Route::post('', 'store')->name('store');
        Route::post('{rastreador}/edit', 'update')->whereNumber('rastreador')->name('update');
        Route::post('{rastreador}/instalar', 'install')->whereNumber('rastreador')->name('install');
        Route::post('{rastreador}/remover', 'remove')->whereNumber('rastreador')->name('remove');
    });
    Route::post('/monitoramento/{registro}/posicoes', [MonitoringController::class, 'storePosition'])->whereNumber('registro')->name('monitoring.positions.store');
    Route::post('/monitoramento/{registro}/posicoes-manuais', [MonitoringController::class, 'storeManualPosition'])->whereNumber('registro')->name('monitoring.manual-positions.store');

    // Rotas Gestor
    // Compartilhadas com outros perfis conforme as permissoes do vinculo ativo.
    Route::get('/painel', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/historico-trajetos', [MonitoringController::class, 'history'])->name('history.index');
    Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
    Route::get('/agenda/reservas/{reserva}', [AgendaController::class, 'show'])->whereNumber('reserva')->name('agenda.show');
    Route::get('/conta', [AccountController::class, 'index'])->name('account.index');
    Route::post('/conta', [AccountController::class, 'update'])->name('account.update');
    Route::get('/notificacoes', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notificacoes/{evento}/ler', [NotificationController::class, 'read'])->whereNumber('evento')->name('notifications.read');
    Route::post('/notificacoes/{evento}/ocultar', [NotificationController::class, 'hide'])->whereNumber('evento')->name('notifications.hide');
    Route::get('/relatorios', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/relatorios/exportar', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/relatorios/{exportacao}/concluido', [ReportController::class, 'ready'])->whereNumber('exportacao')->name('reports.ready');
    Route::get('/relatorios/{exportacao}/arquivo', [ReportController::class, 'download'])->whereNumber('exportacao')->name('reports.download');
    Route::post('/viagens/{registro}/{acao}', [TripController::class, 'perform'])->whereNumber('registro')->where('acao', 'departure|return|occurrence|cancel')->defaults('tela', 'trips')->name('trips.perform');
    Route::get('/chamados', [TicketController::class, 'index'])->defaults('tela', 'tickets')->name('tickets.index');
    Route::get('/chamados/novo', [TicketController::class, 'create'])->defaults('tela', 'tickets')->name('tickets.create');
    Route::post('/chamados/novo', [TicketController::class, 'store'])->defaults('tela', 'tickets')->name('tickets.store');
    Route::get('/chamados/{registro}', [TicketController::class, 'show'])->whereNumber('registro')->defaults('tela', 'tickets')->name('tickets.show');
    Route::get('/chamados/{registro}/anexos/{anexo}', [TicketController::class, 'download'])->whereNumber('registro')->whereNumber('anexo')->defaults('tela', 'tickets')->name('tickets.download');
    Route::get('/chamados/{registro}/{acao}', [TicketController::class, 'operation'])->whereNumber('registro')->where('acao', 'respond|note|assign|resolve|attach')->defaults('tela', 'tickets')->name('tickets.operation');
    Route::post('/chamados/{registro}/{acao}', [TicketController::class, 'perform'])->whereNumber('registro')->where('acao', 'respond|note|assign|resolve|attach')->defaults('tela', 'tickets')->name('tickets.perform');
    Route::get('/frota', [RecordController::class, 'index'])->defaults('tela', 'vehicles')->name('vehicles.index');
    Route::get('/frota/novo', [VehicleController::class, 'create'])->defaults('tela', 'vehicles')->name('vehicles.create');
    Route::post('/frota/novo', [VehicleController::class, 'store'])->defaults('tela', 'vehicles')->name('vehicles.store');
    Route::get('/frota/{registro}', [RecordController::class, 'show'])->whereNumber('registro')->defaults('tela', 'vehicles')->name('vehicles.show');
    Route::post('/frota/{registro}/documentos', [VehicleController::class, 'storeDocument'])->whereNumber('registro')->defaults('tela', 'vehicles')->name('vehicles.documents.store');
    Route::get('/frota/{registro}/documentos/{documento}', [VehicleController::class, 'downloadDocument'])->whereNumber('registro')->whereNumber('documento')->defaults('tela', 'vehicles')->name('vehicles.documents.download');
    Route::get('/frota/{registro}/{acao}', [VehicleController::class, 'operation'])->whereNumber('registro')->where('acao', 'edit|block|release|transfer')->defaults('tela', 'vehicles')->name('vehicles.operation');
    Route::post('/frota/{registro}/{acao}', [VehicleController::class, 'perform'])->whereNumber('registro')->where('acao', 'edit|block|release|transfer')->defaults('tela', 'vehicles')->name('vehicles.perform');

    foreach (config('screens') as $codigo => $tela) {
        if (! in_array($codigo, ['trips', 'monitoring'], true)) {
            continue;
        }
        Route::get('/'.$tela['url'], [RecordController::class, 'index'])->defaults('tela', $codigo)->name($codigo.'.index');
        if ($tela['create'] ?? false) {
            Route::get('/'.$tela['url'].'/novo', [RecordController::class, 'create'])->defaults('tela', $codigo)->name($codigo.'.create');
        }
        Route::get('/'.$tela['url'].'/{registro}', [RecordController::class, 'show'])->whereNumber('registro')->defaults('tela', $codigo)->name($codigo.'.show');
        Route::get('/'.$tela['url'].'/{registro}/{acao}', [RecordController::class, 'operation'])->whereNumber('registro')->defaults('tela', $codigo)->name($codigo.'.operation');
    }
});
