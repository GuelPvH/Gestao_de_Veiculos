<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(AccessContext $acesso): View
    {
        abort_unless(config('fleet.review_enabled'), 404);
        $links = ['dashboard' => 'Painel'];
        foreach (config('screens') as $codigo => $tela) {
            if ($acesso->level($tela['module']) > 0) {
                $links[$codigo.'.index'] = $tela['title'];
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
