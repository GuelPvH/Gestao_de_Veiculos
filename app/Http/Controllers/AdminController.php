<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminActionRequest;
use App\Services\Admin\AdminWorkflow;
use Illuminate\Http\RedirectResponse;

class AdminController extends Controller
{
    public function storeUser(AdminActionRequest $requisicao, AdminWorkflow $fluxo): RedirectResponse
    {
        $id = $fluxo->storeUser($requisicao->validated());

        return redirect()->route('users.show', $id)->with('status', 'Usuário criado. A senha temporária deve ser entregue por canal privado; o primeiro acesso exigirá troca.');
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

    public function updateRoute(AdminActionRequest $requisicao, AdminWorkflow $fluxo, int $registro): RedirectResponse
    {
        $fluxo->updateRoute($registro, $requisicao->validated());

        return redirect()->route('technical-routes.show', $registro)->with('status', 'Metadados da rota atualizados.');
    }
}
