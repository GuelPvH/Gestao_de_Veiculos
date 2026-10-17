<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Support\CurrentUser;
use App\Support\Value;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(AccessContext $acesso, FleetReadRepository $leituras): View
    {
        $usuario = CurrentUser::get();
        abort_unless($acesso->can('conta', 'consultar', Value::integer($usuario->getAuthIdentifier()), Value::integer($acesso->requireLink()->unidade_id)), 403);
        $sessoes = DB::table('sessoes')->where('usuario_id', $usuario->getAuthIdentifier())->orderByDesc('id')->limit(20)->get(['id', 'criado_em', 'ultima_atividade_em', 'expira_em', 'encerrada_em', 'motivo_encerramento']);

        return view('account.index', compact('usuario', 'sessoes', 'leituras'));
    }
}
