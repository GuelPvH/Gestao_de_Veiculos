<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(AccessContext $acesso, FleetReadRepository $leituras): View
    {
        abort_unless($acesso->can('notificacoes', 'consultar', (int) $acesso->link()->usuario_id, (int) $acesso->link()->unidade_id), 403);
        $paginacao = DB::table('vw_notificacoes_usuario')->where('usuario_id', $acesso->link()->usuario_id)->whereNull('oculta_em')->orderByDesc('id')->paginate(10, ['id', 'tipo', 'criado_em', 'lida_em', 'solicitacao_id', 'viagem_id', 'multa_id', 'despesa_id', 'chamado_id'])->withQueryString();
        $registros = $paginacao->getCollection()->map(function ($evento) use ($acesso, $leituras) {
            $evento->link = null;
            foreach (['requests' => 'solicitacao_id', 'trips' => 'viagem_id', 'fines' => 'multa_id', 'expenses' => 'despesa_id', 'tickets' => 'chamado_id'] as $codigo => $coluna) {
                $tela = $leituras->definition($codigo);
                if ($evento->{$coluna} && $acesso->level($tela['module']) > 0 && $leituras->query($codigo)->where('r.id', $evento->{$coluna})->exists()) {
                    $evento->link = route($codigo.'.show', $evento->{$coluna});
                    break;
                }
            }

            return $evento;
        });

        return view('notifications.index', compact('registros', 'paginacao', 'leituras'));
    }
}
