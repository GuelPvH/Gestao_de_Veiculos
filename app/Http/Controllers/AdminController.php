<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminActionRequest;
use App\Services\Admin\AdminWorkflow;
use App\Support\CurrentUser;
use App\Support\Value;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;

class AdminController extends Controller
{
    public function storeUser(AdminActionRequest $requisicao, AdminWorkflow $fluxo): RedirectResponse
    {
        $usuario = $fluxo->storeUser($requisicao->validated());

        return $this->showCredential($usuario['id'], $usuario['codigo'], 'Usuário criado. Atribua um perfil para liberar o acesso ao painel.');
    }

    public function issueTemporaryCredential(AdminActionRequest $requisicao, AdminWorkflow $fluxo, int $registro): RedirectResponse
    {
        $chave = 'fleet-admin-credential:'.hash('sha256', Value::string(CurrentUser::get($requisicao)->getAuthIdentifier()));
        abort_if(RateLimiter::tooManyAttempts($chave, 5), 429);
        RateLimiter::hit($chave, 60);
        $codigo = $fluxo->issueTemporaryCredential($registro, $requisicao->validated());
        RateLimiter::clear($chave);

        return $this->showCredential($registro, $codigo, 'Código temporário emitido. O usuário deverá definir uma nova senha.');
    }

    private function showCredential(int $id, string $codigo, string $status): RedirectResponse
    {
        return redirect()->route('users.show', $id)->with('status', $status)
            ->with('fleet.temporary_credential.'.$id, Crypt::encryptString($codigo));
    }

    public function updateUser(AdminActionRequest $requisicao, AdminWorkflow $fluxo, int $registro): RedirectResponse
    {
        $fluxo->updateUser($registro, $requisicao->validated());

        return redirect()->route('users.show', $registro)->with('status', 'Usuário atualizado.');
    }

    public function linkUser(AdminActionRequest $requisicao, AdminWorkflow $fluxo, int $registro): RedirectResponse
    {
        $fluxo->linkUser($registro, $requisicao->validated());

        return redirect()->route('users.show', $registro)->with('status', 'Vínculo de perfil atribuído.');
    }

    public function revokeLink(AdminActionRequest $requisicao, AdminWorkflow $fluxo, int $registro): RedirectResponse
    {
        $fluxo->revokeLink($registro, $requisicao->validated());

        return redirect()->route('users.show', $registro)->with('status', 'Vínculo revogado; sessões associadas foram encerradas.');
    }

    public function storeRole(AdminActionRequest $requisicao, AdminWorkflow $fluxo): RedirectResponse
    {
        $id = $fluxo->storeRole($requisicao->validated());

        return redirect()->route('roles.show', $id)->with('status', 'Perfil criado sem permissões. Conceda apenas as ações delegáveis.');
    }

    public function updateRole(AdminActionRequest $requisicao, AdminWorkflow $fluxo, int $registro): RedirectResponse
    {
        $fluxo->updateRole($registro, $requisicao->validated());

        return redirect()->route('roles.show', $registro)->with('status', 'Perfil atualizado.');
    }

    public function duplicateRole(AdminActionRequest $requisicao, AdminWorkflow $fluxo, int $registro): RedirectResponse
    {
        $id = $fluxo->duplicateRole($registro, $requisicao->validated());

        return redirect()->route('roles.show', $id)->with('status', 'Perfil duplicado com permissões delegáveis.');
    }

    public function grantRole(AdminActionRequest $requisicao, AdminWorkflow $fluxo, int $registro): RedirectResponse
    {
        $fluxo->grantRole($registro, $requisicao->validated());

        return redirect()->route('roles.show', $registro)->with('status', 'Permissão concedida.');
    }

    public function revokeRole(AdminActionRequest $requisicao, AdminWorkflow $fluxo, int $registro): RedirectResponse
    {
        $fluxo->revokeRole($registro, $requisicao->validated());

        return redirect()->route('roles.show', $registro)->with('status', 'Permissão revogada.');
    }

    public function storeRoute(AdminActionRequest $requisicao, AdminWorkflow $fluxo): RedirectResponse
    {
        $id = $fluxo->storeRoute($requisicao->validated());

        return redirect()->route('technical-routes.show', $id)->with('status', 'Rota técnica cadastrada.');
    }

    public function updateRoute(AdminActionRequest $requisicao, AdminWorkflow $fluxo, int $registro): RedirectResponse
    {
        $fluxo->updateRoute($registro, $requisicao->validated());

        return redirect()->route('technical-routes.show', $registro)->with('status', 'Metadados da rota atualizados.');
    }
}
