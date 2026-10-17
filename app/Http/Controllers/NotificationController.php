<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationActionRequest;
use App\Services\Authorization\AccessContext;
use App\Services\Notifications\NotificationStateService;
use App\Services\Read\FleetReadRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(NotificationStateService $estados, AccessContext $acesso, FleetReadRepository $leituras): View
    {
        $usuario = $estados->recipientId();
        $podeEditar = $estados->canEdit();
        $paginacao = DB::table('vw_notificacoes_usuario')->where('usuario_id', $usuario)->whereNull('oculta_em')->orderByDesc('id')->paginate(10, ['id', 'tipo', 'titulo', 'mensagem', 'versao', 'criado_em', 'lida_em', 'solicitacao_id', 'viagem_id', 'multa_id', 'despesa_id', 'chamado_id'])->withQueryString();
        $targets = ['requests' => 'solicitacao_id', 'trips' => 'viagem_id', 'fines' => 'multa_id', 'expenses' => 'despesa_id', 'tickets' => 'chamado_id'];
        $references = [];
        foreach ($targets as $codigo => $coluna) {
            if ($acesso->level($leituras->definition($codigo)['module']) > 0) {
                $references[$codigo] = $paginacao->getCollection()->pluck($coluna)->filter()->map(fn ($id) => (int) $id)->all();
            }
        }
        $authorized = $leituras->authorizedReferences($references);
        $registros = $paginacao->getCollection()->map(function ($evento) use ($targets, $authorized) {
            $evento->link = null;
            foreach ($targets as $codigo => $coluna) {
                if ($evento->{$coluna} && isset($authorized[$codigo][(int) $evento->{$coluna}])) {
                    $evento->link = route((config('screens.'.$codigo.'.route') ?? $codigo).'.show', $evento->{$coluna});
                    break;
                }
            }

            return $evento;
        });

        return view('notifications.index', compact('registros', 'paginacao', 'leituras', 'podeEditar'));
    }

    public function read(NotificationActionRequest $requisicao, NotificationStateService $estados, int $evento): RedirectResponse
    {
        if (! $estados->markRead($evento, (int) $requisicao->validated('versao'))) {
            return redirect()->route('notifications.index')->withErrors(['notificacao' => 'Esta notificação foi alterada. Atualize a página e tente novamente.']);
        }

        return redirect()->route('notifications.index')->with('status', 'Notificação marcada como lida.');
    }

    public function hide(NotificationActionRequest $requisicao, NotificationStateService $estados, int $evento): RedirectResponse
    {
        if (! $estados->hide($evento, (int) $requisicao->validated('versao'))) {
            return redirect()->route('notifications.index')->withErrors(['notificacao' => 'Esta notificação foi alterada. Atualize a página e tente novamente.']);
        }

        return redirect()->route('notifications.index')->with('status', 'Notificação ocultada.');
    }
}
