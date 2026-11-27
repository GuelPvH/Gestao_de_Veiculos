<?php

namespace App\Http\Requests;

use App\Services\Admin\TechnicalRouteCatalog;
use App\Services\Authorization\AccessContext;
use App\Support\Cpf;
use App\Support\Value;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminActionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $area = $this->route('area');
        $operacao = $this->route('operacao');
        if ($area === 'users' && in_array($operacao, ['store', 'update', 'temporary'], true)) {
            $entrada = trim(Value::string($this->input('identificador')));
            if ($entrada !== '') {
                $cpf = Cpf::normalize($entrada);
                if ($cpf !== null) {
                    $this->merge(['identificador' => $cpf]);
                }
            }
        }
        if ($area === 'users' && $operacao === 'store' && ! empty($this->input('perfil_id')) && trim(Value::string($this->input('vigente_desde'))) === '') {
            $this->merge([
                'vigente_desde' => CarbonImmutable::now(config('fleet.timezone'))->format('Y-m-d\TH:i'),
            ]);
        }
        if ($area === 'technical-routes' && in_array($operacao, ['permission-store', 'permission-destroy'], true) && $this->has('combinacao')) {
            $combinacao = trim(Value::string($this->input('combinacao')));
            if (str_contains($combinacao, '.')) {
                [$modulo, $acao] = explode('.', $combinacao, 2);
                $this->merge([
                    'modulo_codigo' => $modulo,
                    'acao_codigo' => $acao,
                ]);
            }
        }
        if ($operacao !== 'store' || $area !== 'technical-routes' || ! $this->has('endpoint')) {
            return;
        }
        $rota = app(TechnicalRouteCatalog::class)->find(Value::string($this->input('endpoint')));
        $this->merge([
            'caminho' => $rota['caminho'] ?? null,
            'metodo_http' => $rota['metodo'] ?? null,
            'modulo_codigo' => $rota['modulo'] ?? null,
        ]);
    }

    public function authorize(): bool
    {
        $acesso = app(AccessContext::class);
        $permissao = match ($this->route('area').'.'.$this->route('operacao')) {
            'users.store' => ['usuarios', 'criar'],
            'users.update', 'users.revoke' => ['usuarios', 'editar'],
            'users.link' => ['usuarios', 'delegar'],
            'users.temporary' => ['usuarios', 'gerenciar'],
            'roles.store', 'roles.duplicate' => ['perfis', 'criar'],
            'roles.update' => ['perfis', 'editar'],
            'roles.grant', 'roles.revoke' => ['perfis', 'delegar'],
            'technical-routes.store' => ['rotas', 'criar'],
            'technical-routes.update', 'technical-routes.permission-store', 'technical-routes.permission-destroy' => ['rotas', 'editar'],
            default => null,
        };

        return $permissao !== null && $acesso->level($permissao[0], $permissao[1]) > 0;
    }

    public function messages(): array
    {
        return [
            'caminho.required' => 'Selecione um caminho e método disponíveis na aplicação.',
            'caminho.unique' => 'Este caminho já está cadastrado para o método selecionado. Edite a rota existente.',
        ];
    }

    /**
     * @return array<string, list<string|\Stringable|\Closure|\Illuminate\Contracts\Validation\Rule>>
     */
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
                'email' => $email, 'telefone' => ['nullable', 'string', 'max:30'],
                'perfil_id' => ['nullable', 'integer', Rule::exists('perfis', 'id')->where('ativo', 1)],
                'vigente_desde' => ['nullable', 'date_format:Y-m-d\\TH:i'],
                'vigente_ate' => ['nullable', 'date_format:Y-m-d\\TH:i', 'after:vigente_desde'],
                'justificativa' => ['required_with:perfil_id', 'nullable', 'string', 'min:5', 'max:1000'],
            ],
            'users.update' => [
                'versao' => ['required', 'integer', 'min:1'], 'unidade_id' => $unidade,
                'identificador' => $identificador, 'nome' => ['required', 'string', 'max:150'],
                'email' => $email, 'telefone' => ['nullable', 'string', 'max:30'],
                'ativo' => ['required', 'boolean'], 'justificativa' => ['required', 'string', 'min:5', 'max:1000'],
            ],
            'users.temporary' => [
                'identificador' => ['nullable', 'string', 'max:100'],
                'senha_atual' => ['required', 'string', 'max:256'],
                'justificativa' => ['required', 'string', 'min:5', 'max:1000'],
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
                'rota_id' => ['nullable', 'integer', Rule::exists('rotas_sistema', 'id')->where('implementada', 1)],
            ],
            'roles.revoke' => [
                'permissao_id' => ['required', 'integer', Rule::exists('permissoes', 'id')],
                'confirmar_revogacao' => ['required', 'accepted'],
            ],
            'technical-routes.store' => [
                'chave' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_.-]+$/', Rule::unique('rotas_sistema', 'chave')],
                'nome' => ['required', 'string', 'max:120'],
                'caminho' => ['required', 'string', 'max:255', 'starts_with:/', Rule::unique('rotas_sistema', 'caminho')->where('metodo_http', Value::string($this->input('metodo_http')))],
                'metodo_http' => ['required', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
                'modulo_codigo' => ['required', 'string', Rule::exists('modulos', 'codigo')->where('ativo', 1)],
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
            'technical-routes.permission-store' => [
                'modulo_codigo' => ['required', 'string', Rule::exists('modulos', 'codigo')->where('ativo', 1)],
                'acao_codigo' => ['required', 'string', Rule::exists('modulo_acoes', 'acao_codigo')->where('modulo_codigo', Value::string($this->input('modulo_codigo')))],
            ],
            'technical-routes.permission-destroy' => [
                'modulo_codigo' => ['required', 'string', 'max:40'],
                'acao_codigo' => ['required', 'string', 'max:40'],
            ],
            default => [],
        };
    }
}
