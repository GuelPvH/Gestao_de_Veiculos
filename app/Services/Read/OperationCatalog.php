<?php

namespace App\Services\Read;

use App\Services\Authorization\AccessContext;
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
            $situacao = $registro->situacao ?? '';
            $valida = true;
            if ($codigo === 'requests') {
                $valida = match ($acao) {
                    'approve', 'deny', 'adjust' => $situacao === 'aguardando_analise' && (int) $registro->__owner !== (int) $this->acesso->link()->usuario_id,
                    'revision' => $situacao === 'aprovada',
                    'edit', 'send' => in_array($situacao, ['rascunho', 'ajustes_solicitados'], true),
                    'cancel' => ! in_array($situacao, ['negada', 'cancelada'], true),
                    default => false,
                };
            } elseif ($codigo === 'trips') {
                $valida = match ($acao) {
                    'departure', 'cancel' => $situacao === 'programada',
                    'return', 'occurrence' => $situacao === 'em_andamento',
                    default => false,
                };
            } elseif ($codigo === 'fines') {
                $valida = match ($acao) {
                    'proof' => in_array($situacao, ['aguardando_comprovante', 'contestada'], true),
                    'assign' => $situacao === 'sem_responsavel',
                    'verify', 'correct', 'settle' => $situacao === 'em_conferencia' && $registro->__sender !== null && (int) $registro->__sender !== (int) $this->acesso->link()->usuario_id && (int) $registro->__owner !== (int) $this->acesso->link()->usuario_id,
                    'dispute', 'cancel' => ! in_array($situacao, ['quitada', 'cancelada', 'em_conferencia'], true),
                    default => false,
                };
            } elseif ($codigo === 'expenses') {
                $valida = $acao === 'verify' ? $situacao === 'em_conferencia' : ! in_array($situacao, ['paga', 'cancelada'], true);
            } elseif ($codigo === 'maintenance') {
                $valida = ! in_array($situacao, ['concluida', 'cancelada'], true);
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
