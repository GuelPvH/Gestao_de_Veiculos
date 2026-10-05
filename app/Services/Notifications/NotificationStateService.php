<?php

namespace App\Services\Notifications;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PDOException;

class NotificationStateService
{
    public function __construct(private AccessContext $acesso, private ProcedureRunner $procedimentos) {}

    public function canRead(): bool
    {
        return $this->allowed('consultar');
    }

    public function canEdit(): bool
    {
        return $this->canRead() && $this->allowed('editar');
    }

    public function recipientId(): int
    {
        abort_unless($this->canRead(), 403);

        return (int) Auth::id();
    }

    public function markRead(int $evento, int $versao): bool
    {
        return $this->change($evento, $versao, false);
    }

    public function hide(int $evento, int $versao): bool
    {
        return $this->change($evento, $versao, true);
    }

    private function allowed(string $acao): bool
    {
        $vinculo = $this->acesso->link();
        $usuario = Auth::id();

        return $vinculo !== null
            && $usuario !== null
            && (int) $vinculo->usuario_id === (int) $usuario
            && $this->acesso->can('notificacoes', $acao, (int) $usuario, (int) $vinculo->unidade_id);
    }

    private function change(int $evento, int $versao, bool $ocultar): bool
    {
        abort_unless($this->canEdit(), 403);
        $usuario = (int) Auth::id();
        $destinatario = DB::table('vw_notificacoes_usuario')
            ->where('usuario_id', $usuario)
            ->where('id', $evento)
            ->whereNull('oculta_em')
            ->first(['versao', 'lida_em']);
        abort_unless($destinatario !== null, 404);
        if ((int) $destinatario->versao !== $versao) {
            return false;
        }

        // Ocultar preserva o estado anterior de leitura; ler nunca reabre uma notificação oculta.
        $lida = $ocultar ? (int) ($destinatario->lida_em !== null) : 1;
        try {
            $this->procedimentos->call('sp_marcar_notificacao', [$usuario, $evento, $versao, $lida, (int) $ocultar]);
        } catch (PDOException $erro) {
            if (($erro->errorInfo[0] ?? (string) $erro->getCode()) === '45000'
                && str_contains($erro->getMessage(), 'Notificação não encontrada ou versão já alterada.')) {
                return false;
            }

            throw $erro;
        }

        return true;
    }
}
