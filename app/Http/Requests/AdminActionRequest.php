<?php

namespace App\Http\Requests;

use App\Services\Auth\PasswordPolicy;
use App\Services\Authorization\AccessContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $acesso = app(AccessContext::class);
        $permissao = match ($this->route('area').'.'.$this->route('operacao')) {
            'users.store' => ['usuarios', 'criar'],
            'users.update', 'users.revoke' => ['usuarios', 'editar'],
            'users.link' => ['usuarios', 'delegar'],
            'roles.store', 'roles.duplicate' => ['perfis', 'criar'],
            'roles.update' => ['perfis', 'editar'],
            'roles.grant', 'roles.revoke' => ['perfis', 'delegar'],
            'technical-routes.store' => ['rotas', 'criar'],
            'technical-routes.update' => ['rotas', 'editar'],
            default => null,
        };

        return $permissao !== null && $acesso->level($permissao[0], $permissao[1]) > 0;
    }

    public function rules(): array
    {
        $operacao = $this->route('area').'.'.$this->route('operacao');
        $registro = (int) $this->route('registro');
        $unidade = ['required', 'integer', Rule::exists('unidades', 'id')->where('ativa', 1)];
        $identificador = ['required', 'string', 'max:100', Rule::unique('usuarios', 'identificador')->ignore($registro)];
        $email = ['nullable', 'email:rfc', 'max:254', Rule::unique('usuarios', 'email')->ignore($registro)];
        $codigo = ['required', 'regex:/^[a-z][a-z0-9_]*$/', 'max:60', Rule::unique('perfis', 'codigo')];

        return match ($operacao) {
            'users.store' => [
                'unidade_id' => $unidade, 'identificador' => $identificador, 'nome' => ['required', 'string', 'max:150'],
                'email' => $email, 'senha_temporaria' => PasswordPolicy::rules(),
            ],
            'users.update' => [
                'versao' => ['required', 'integer', 'min:1'], 'unidade_id' => $unidade,
                'identificador' => $identificador, 'nome' => ['required', 'string', 'max:150'],
                'email' => $email, 'telefone' => ['nullable', 'string', 'max:30'],
                'ativo' => ['required', 'boolean'], 'justificativa' => ['required', 'string', 'min:5', 'max:1000'],
            ],
            'users.link' => [
                'perfil_id' => ['required', 'integer', Rule::exists('perfis', 'id')->where('ativo', 1)],
                'unidade_id' => $unidade, 'vigente_desde' => ['required', 'date_format:Y-m-d\\TH:i'],
                'vigente_ate' => ['nullable', 'date_format:Y-m-d\\TH:i', 'after:vigente_desde'],
                'justificativa' => ['required', 'string', 'min:5', 'max:1000'],
            ],
            'users.revoke' => [
                'vinculo_id' => ['required', 'integer', 'min:1'], 'motivo' => ['required', 'string', 'min:5', 'max:500'],
                'confirmar_revogacao' => ['required', 'accepted'],
            ],
            'roles.store' => [
                'codigo' => $codigo, 'nome' => ['required', 'string', 'max:100'],
                'descricao' => ['nullable', 'string', 'max:3000'],
            ],
            'roles.update' => [
                'atualizado_em' => ['required', 'string', 'max:32'], 'nome' => ['required', 'string', 'max:100'],
                'descricao' => ['nullable', 'string', 'max:3000'], 'ativo' => ['required', 'boolean'],
                'justificativa' => ['required', 'string', 'min:5', 'max:1000'],
            ],
            'roles.duplicate' => [
                'codigo' => $codigo, 'nome' => ['required', 'string', 'max:100'],
                'justificativa' => ['required', 'string', 'min:5', 'max:1000'],
            ],
            'roles.grant' => [
                'permissao_id' => ['required', 'integer', Rule::exists('permissoes', 'id')],
                'delegavel' => ['required', 'boolean'],
            ],
            'roles.revoke' => [
                'permissao_id' => ['required', 'integer', Rule::exists('permissoes', 'id')],
                'confirmar_revogacao' => ['required', 'accepted'],
            ],
            'technical-routes.store' => [
                'chave' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_.-]+$/', Rule::unique('rotas_sistema', 'chave')],
                'nome' => ['required', 'string', 'max:120'],
                'caminho' => ['required', 'string', 'max:255', 'starts_with:/'],
                'metodo_http' => ['required', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
                'modulo_codigo' => ['required', 'string', Rule::exists('modulos', 'codigo')],
                'descricao' => ['nullable', 'string', 'max:3000'],
                'ativa' => ['required', 'boolean'],
                'visivel_menu' => ['required', 'boolean'],
                'ordem' => ['required', 'integer', 'between:0,65535'],
            ],
            'technical-routes.update' => [
                'nome' => ['required', 'string', 'max:120'], 'descricao' => ['nullable', 'string', 'max:3000'],
                'ativa' => ['required', 'boolean'], 'visivel_menu' => ['required', 'boolean'],
                'ordem' => ['required', 'integer', 'between:0,65535'],
                'justificativa' => ['required', 'string', 'min:5', 'max:1000'],
            ],
            default => [],
        };
    }
}
