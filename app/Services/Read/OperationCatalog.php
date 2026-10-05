<?php

namespace App\Services\Read;

use App\Services\Authorization\AccessContext;
use Illuminate\Support\Facades\DB;
use stdClass;

class OperationCatalog
{
    public function __construct(private AccessContext $acesso) {}

    public function allowed(string $codigo, stdClass $registro): array
    {
        $tela = config('screens.'.$codigo);
        $permitidas = [];
        foreach ($tela['actions'] ?? [] as $acao => $contrato) {
            if (! $this->acesso->can($tela['module'], $contrato[1], $registro->__owner !== null ? (int) $registro->__owner : null, $registro->__unit !== null ? (int) $registro->__unit : null)) {
                continue;
            }
            if (in_array($codigo, ['expenses', 'fuel'], true) && $acao === 'edit'
                && ! $this->acesso->can('despesas', 'ver_valores', (int) $registro->__owner, (int) $registro->__unit)) {
                continue;
            }
            if ($codigo === 'fines' && $acao === 'edit'
                && ! $this->acesso->can('multas', 'ver_valores', $registro->__owner !== null ? (int) $registro->__owner : null, (int) $registro->__unit)) {
                continue;
            }
            $situacao = $registro->situacao ?? '';
            $valida = true;
            if ($codigo === 'requests') {
                $valida = match ($acao) {
                    'approve', 'deny', 'adjust' => $situacao === 'aguardando_analise' && (int) $registro->__owner !== (int) $this->acesso->link()->usuario_id,
                    'revision' => in_array($situacao, ['aguardando_analise', 'ajustes_solicitados', 'aprovada'], true),
                    'edit', 'send' => $situacao === 'rascunho',
                    'cancel' => ! in_array($situacao, ['negada', 'cancelada'], true),
                    default => false,
                };
            } elseif ($codigo === 'trips') {
                $valida = match ($acao) {
                    'departure' => $situacao === 'programada',
                    'cancel' => $situacao === 'programada' && $this->acesso->can('solicitacoes', 'cancelar', $registro->__owner !== null ? (int) $registro->__owner : null, $registro->__unit !== null ? (int) $registro->__unit : null),
                    'return', 'occurrence' => $situacao === 'em_andamento',
                    default => false,
                };
            } elseif ($codigo === 'vehicles') {
                $valida = match ($acao) {
                    'edit' => true,
                    'block' => DB::table('veiculos')->where('id', $registro->id)->where('situacao_cadastro', 'ativo')->exists(),
                    'release' => DB::table('reservas as r')->leftJoin('manutencoes as m', 'm.reserva_id', '=', 'r.id')
                        ->where('r.veiculo_id', $registro->id)->where('r.situacao', 'ativa')
                        ->whereIn('r.tipo', ['indisponibilidade', 'manutencao'])->whereNull('m.id')->exists(),
                    default => false,
                };
            } elseif ($codigo === 'fines') {
                $valida = match ($acao) {
                    'edit' => $situacao === 'sem_responsavel',
                    'proof' => $situacao === 'aguardando_comprovante',
                    'assign' => in_array($situacao, ['sem_responsavel', 'aguardando_comprovante', 'contestada'], true) && $registro->__sender === null,
                    'verify', 'correct', 'settle' => $situacao === 'em_conferencia' && $registro->__sender !== null && (int) $registro->__sender !== (int) $this->acesso->link()->usuario_id && (int) $registro->__owner !== (int) $this->acesso->link()->usuario_id,
                    'dispute' => ! in_array($situacao, ['quitada', 'cancelada', 'em_conferencia', 'contestada'], true),
                    'cancel' => ! in_array($situacao, ['quitada', 'cancelada', 'em_conferencia'], true),
                    default => false,
                };
            } elseif ($codigo === 'expenses') {
                $valida = match ($acao) {
                    'edit', 'submit' => $situacao === 'registrada',
                    'verify' => $situacao === 'em_conferencia' && (int) $registro->__owner !== (int) $this->acesso->link()->usuario_id,
                    'pay' => $situacao === 'aprovada',
                    'cancel' => ! in_array($situacao, ['paga', 'cancelada'], true),
                    default => false,
                };
            } elseif ($codigo === 'fuel') {
                $valida = $acao === 'edit' && $situacao === 'registrada';
            } elseif ($codigo === 'maintenance') {
                $valida = match ($acao) {
                    'edit', 'start' => $situacao === 'planejada',
                    'complete' => $situacao === 'em_execucao',
                    'cancel' => in_array($situacao, ['planejada', 'em_execucao'], true),
                    default => false,
                };
            } elseif ($codigo === 'tickets') {
                $valida = ! in_array($acao, ['respond', 'resolve', 'assign'], true) || $situacao !== 'resolvido';
            }
            if ($valida) {
                $permitidas[$acao] = $contrato[0];
            }
        }

        return $permitidas;
    }
}
