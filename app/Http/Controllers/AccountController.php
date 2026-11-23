<?php

namespace App\Http\Controllers;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Support\CurrentUser;
use App\Support\Value;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(AccessContext $acesso, FleetReadRepository $leituras): View
    {
        $usuario = CurrentUser::get();
        $usuarioId = Value::integer($usuario->getAuthIdentifier());
        $unidadeId = Value::integer($acesso->requireLink()->unidade_id);
        abort_unless($acesso->can('conta', 'consultar', $usuarioId, $unidadeId), 403);
        $sessoes = DB::table('sessoes')->where('usuario_id', $usuarioId)->orderByDesc('id')->limit(20)->get(['id', 'criado_em', 'ultima_atividade_em', 'expira_em', 'encerrada_em', 'motivo_encerramento']);
        $preferencias = DB::table('preferencias_usuario')->where('usuario_id', $usuarioId)->first(['tema', 'itens_por_pagina', 'notificacoes_internas'])
            ?? (object) ['tema' => 'dispositivo', 'itens_por_pagina' => 10, 'notificacoes_internas' => 1];
        $podeEditar = $acesso->can('conta', 'editar', $usuarioId, $unidadeId);

        return view('account.index', compact('usuario', 'sessoes', 'leituras', 'preferencias', 'podeEditar'));
    }

    public function update(Request $requisicao, AccessContext $acesso, ProcedureRunner $procedimentos): RedirectResponse
    {
        $usuario = CurrentUser::get();
        $usuarioId = Value::integer($usuario->getAuthIdentifier());
        $vinculo = $acesso->requireLink();
        $unidadeId = Value::integer($vinculo->unidade_id);
        abort_unless($acesso->can('conta', 'editar', $usuarioId, $unidadeId), 403);
        /** @var array<string, mixed> $dados */
        $dados = $requisicao->validate([
            'telefone' => ['nullable', 'string', 'max:30'],
            'tema' => ['required', Rule::in(['claro', 'escuro', 'dispositivo'])],
            'itens_por_pagina' => ['required', 'integer', Rule::in([10, 20, 50, 100])],
            'notificacoes_internas' => ['required', 'boolean'],
        ]);
        $telefone = trim(Value::string($dados['telefone'] ?? ''));
        $telefone = $telefone === '' ? null : $telefone;
        $vinculoId = Value::integer($vinculo->vinculo_id);

        DB::transaction(function () use ($usuarioId, $unidadeId, $vinculoId, $telefone, $dados, $procedimentos): void {
            $procedimentos->call('sp_exigir_permissao', [$vinculoId, 'conta', 'editar', $usuarioId, $unidadeId]);
            $atual = DB::table('usuarios')->where('id', $usuarioId)->lockForUpdate()->first(['telefone']);
            abort_unless($atual !== null, 404);
            if ($atual->telefone !== $telefone) {
                DB::table('usuarios')->where('id', $usuarioId)->update(['telefone' => $telefone]);
            }
            $agora = now('UTC')->format('Y-m-d H:i:s.u');
            $preferencia = [
                'tema' => Value::string($dados['tema']),
                'itens_por_pagina' => Value::integer($dados['itens_por_pagina']),
                'notificacoes_internas' => Value::integer($dados['notificacoes_internas']),
                'atualizado_em' => $agora,
            ];
            if (DB::table('preferencias_usuario')->where('usuario_id', $usuarioId)->exists()) {
                DB::table('preferencias_usuario')->where('usuario_id', $usuarioId)->update($preferencia);
            } else {
                DB::table('preferencias_usuario')->insert($preferencia + ['usuario_id' => $usuarioId]);
            }
            $procedimentos->call('sp_auditar', [$vinculoId, 'conta_atualizada', 'usuarios', $usuarioId, 'Telefone e preferências da conta atualizados.']);
        }, 3);
        $usuario->telefone = $telefone;

        return redirect()->route('account.index')->with('status', 'Dados da conta e preferências atualizados.');
    }
}
