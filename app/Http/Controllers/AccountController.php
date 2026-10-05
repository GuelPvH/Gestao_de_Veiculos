<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(AccessContext $acesso, FleetReadRepository $leituras): View
    {
        $usuario = Auth::user();
        abort_unless($acesso->can('conta', 'consultar', (int) $usuario->getAuthIdentifier(), (int) $acesso->link()->unidade_id), 403);
        $sessoes = DB::table('sessoes')->where('usuario_id', $usuario->getAuthIdentifier())->orderByDesc('id')->limit(20)->get(['id', 'criado_em', 'ultima_atividade_em', 'expira_em', 'encerrada_em', 'motivo_encerramento']);

        return view('account.index', compact('usuario', 'sessoes', 'leituras'));
    }
}
