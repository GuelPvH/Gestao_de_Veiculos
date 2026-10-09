<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(AccessContext $acesso): View
    {
        abort_unless(config('fleet.review_enabled') && in_array(config('app.env'), ['local', 'testing'], true), 404);
        $conexao = DB::connection();
        $isolado = config('app.env') === 'testing' && $conexao->getDriverName() === 'sqlite';
        $isolado = $isolado || ($conexao->getDriverName() === 'mysql' && in_array($conexao->getDatabaseName(), ['frota_pf_local', 'frota_pf_contract_tests'], true) && in_array($conexao->getConfig('host'), ['localhost', '127.0.0.1', 'mysql-local'], true));
        abort_unless($isolado, 404);
        $links = ['dashboard' => 'Painel'];
        foreach (config('screens') as $codigo => $tela) {
            if ($acesso->level($tela['module']) > 0) {
                $links[($tela['route'] ?? $codigo).'.index'] = $tela['title'];
            }
        }
        foreach (['agenda.index' => ['frota', 'Agenda'], 'account.index' => ['conta', 'Conta'], 'notifications.index' => ['notificacoes', 'Notificações'], 'reports.index' => ['relatorios', 'Relatórios'], 'configuration.index' => ['configuracoes', 'Configuração']] as $rota => $tela) {
            if ($acesso->level($tela[0]) > 0 && Route::has($rota)) {
                $links[$rota] = $tela[1];
            }
        }

        return view('review.index', compact('links'));
    }
}
