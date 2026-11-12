<?php

namespace App\Services\Finance;

use App\Support\Value;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExpenseNotifier
{
    /** @var array<string, array{action: string, author: bool, title: string}> */
    private const EVENTS = [
        'enviada_conferencia' => ['action' => 'aprovar', 'author' => false, 'title' => 'Despesa aguardando conferência'],
        'correcao_solicitada' => ['action' => 'editar', 'author' => true, 'title' => 'Correção de despesa solicitada'],
        'aprovada' => ['action' => 'validar_pagamento', 'author' => false, 'title' => 'Despesa aprovada para pagamento'],
        'pagamento_confirmado' => ['action' => 'consultar', 'author' => true, 'title' => 'Pagamento de despesa confirmado'],
    ];

    public function notify(int $expenseId, string $type, int $actorUserId, ?string $reason = null): void
    {
        $event = self::EVENTS[$type] ?? throw new InvalidArgumentException('Tipo de notificação de despesa inválido.');
        $expense = DB::table('despesas')->where('id', $expenseId)->first(['id', 'protocolo', 'unidade_id', 'criado_por', 'versao']);
        abort_unless($expense !== null, 404);
        $unit = Value::integer($expense->unidade_id);
        $author = Value::integer($expense->criado_por);

        $recipients = DB::table('vw_permissoes_efetivas as agir')
            ->join('vw_permissoes_efetivas as ver', 'ver.vinculo_id', '=', 'agir.vinculo_id')
            ->where('agir.modulo_codigo', 'despesas')->where('agir.acao_codigo', $event['action'])
            ->where('ver.modulo_codigo', 'despesas')->where('ver.acao_codigo', 'consultar')
            ->where(fn (Builder $scope) => $this->reaches($scope, 'agir', $unit, $author))
            ->where(fn (Builder $scope) => $this->reaches($scope, 'ver', $unit, $author))
            ->where('agir.usuario_id', '<>', $actorUserId)
            ->when($event['author'], fn (Builder $query) => $query->where('agir.usuario_id', $author))
            ->distinct()->pluck('agir.usuario_id')
            ->map(fn (mixed $user): int => Value::integer($user))->all();
        if ($recipients === []) {
            return;
        }

        $protocol = Value::string($expense->protocolo);
        $message = match ($type) {
            'enviada_conferencia' => 'A despesa '.$protocol.' foi enviada para conferência.',
            'correcao_solicitada' => trim((string) $reason) !== '' ? mb_substr(trim((string) $reason), 0, 1000) : 'A despesa '.$protocol.' voltou para correção.',
            'aprovada' => 'A despesa '.$protocol.' foi aprovada e aguarda pagamento.',
            default => 'O pagamento da despesa '.$protocol.' foi confirmado.',
        };
        $key = 'despesa:'.$expenseId.':v'.Value::integer($expense->versao).':'.$type;
        $eventId = DB::table('notificacao_eventos')->where('chave_idempotencia', $key)->value('id');
        if ($eventId === null) {
            DB::table('notificacao_eventos')->insertOrIgnore([
                'chave_idempotencia' => $key,
                'tipo' => 'despesa_'.$type,
                'titulo' => $event['title'],
                'mensagem' => $message,
                'caminho_destino' => '/financeiro/despesas/'.$expenseId,
                'despesa_id' => $expenseId,
            ]);
            $eventId = DB::table('notificacao_eventos')->where('chave_idempotencia', $key)->value('id');
        }
        $eventId = Value::integer($eventId);
        $already = DB::table('notificacao_destinatarios')->where('evento_id', $eventId)->pluck('usuario_id')
            ->map(fn (mixed $user): int => Value::integer($user))->all();
        $missing = array_values(array_diff($recipients, $already));
        if ($missing !== []) {
            DB::table('notificacao_destinatarios')->insertOrIgnore(array_map(
                static fn (int $user): array => ['evento_id' => $eventId, 'usuario_id' => $user],
                $missing,
            ));
        }
    }

    private function reaches(Builder $scope, string $alias, int $unit, int $author): void
    {
        $scope->where($alias.'.nivel_alcance', 3)
            ->orWhere(fn (Builder $own) => $own->where($alias.'.nivel_alcance', 2)->where($alias.'.unidade_id', $unit))
            ->orWhere(fn (Builder $own) => $own->where($alias.'.nivel_alcance', 1)->where($alias.'.usuario_id', $author));
    }
}
