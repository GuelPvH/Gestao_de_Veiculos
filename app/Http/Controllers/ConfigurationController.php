<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ConfigurationController extends Controller
{
    public function index(AccessContext $acesso): View
    {
        abort_unless($acesso->can('configuracoes'), 403);
        $configuracao = DB::table('configuracao_sistema')->where('id', 1)->first(['fuso_horario', 'sessao_inatividade_minutos', 'limite_sem_comunicacao_minutos', 'moeda']);
        abort_unless($configuracao !== null, 404);

        return view('configuration.index', compact('configuracao'));
    }
}
