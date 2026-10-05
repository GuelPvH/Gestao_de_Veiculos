<?php

namespace App\Http\Requests;

use App\Services\Authorization\AccessContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FinanceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $screen = (string) $this->route('tela');
        $action = (string) ($this->route('acao') ?? 'create');
        if (! in_array($screen, ['expenses', 'fuel', 'maintenance'], true)) {
            return false;
        }
        $access = app(AccessContext::class);
        $link = $access->link();
        if ($link === null) {
            return false;
        }
        if ($action === 'create') {
            return $access->can('despesas', 'criar', (int) $link->usuario_id, (int) $link->unidade_id);
        }
        $id = (int) $this->route('registro');
        $record = $screen === 'maintenance'
            ? DB::table('manutencoes as m')->join('veiculos as v', 'v.id', '=', 'm.veiculo_id')->where('m.id', $id)->first(['m.criado_por', 'v.unidade_id'])
            : DB::table('despesas')->where('id', $id)->first(['criado_por', 'unidade_id']);
        abort_unless($record !== null, 404);
        $owner = (int) $record->criado_por;
        $unit = (int) $record->unidade_id;
        $permission = match ($action) {
            'edit', 'submit', 'start', 'complete' => 'editar',
            'verify' => 'aprovar',
            'pay' => 'validar_pagamento',
            'cancel' => 'cancelar',
            default => null,
        };

        return $permission !== null
            && $access->can('despesas', 'consultar', $owner, $unit)
            && $access->can('despesas', $permission, $owner, $unit);
    }

    public function rules(): array
    {
        $screen = (string) $this->route('tela');
        $action = (string) ($this->route('acao') ?? 'create');
        $upload = ['documento' => ['nullable', 'file', 'max:10240', 'mimetypes:application/pdf,image/png,image/jpeg']];
        $version = ['versao' => ['required', 'integer', 'min:1']];
        $positiveMoney = ['required', 'regex:/^(?:0|[1-9]\d{0,10})(?:\.\d{1,2})?$/', 'numeric', 'gt:0'];
        if ($screen === 'expenses' && in_array($action, ['create', 'edit'], true)) {
            return $upload + ($action === 'edit' ? $version : []) + [
                'veiculo' => [$action === 'create' ? 'required' : 'prohibited', 'string', 'max:20'],
                'categoria' => [$action === 'create' ? 'required' : 'prohibited', 'integer', Rule::exists('categorias_despesa', 'id')->where('ativa', 1)],
                'data_despesa' => ['required', 'date_format:Y-m-d'],
                'valor' => $positiveMoney,
                'fornecedor' => ['nullable', 'string', 'max:150'],
                'numero_documento' => ['nullable', 'string', 'max:100'],
                'descricao' => ['required', 'string', 'max:3000'],
            ];
        }
        if ($screen === 'fuel' && in_array($action, ['create', 'edit'], true)) {
            return $upload + ($action === 'edit' ? $version : []) + [
                'veiculo' => [$action === 'create' ? 'required' : 'prohibited', 'string', 'max:20'],
                'combustivel' => ['required', Rule::in(['gasolina', 'etanol', 'diesel', 'gnv', 'eletricidade', 'outro'])],
                'unidade_medida' => ['required', Rule::in(['litro', 'm3', 'kwh'])],
                'quantidade' => ['required', 'regex:/^(?:0|[1-9]\d{0,8})(?:\.\d{1,3})?$/', 'numeric', 'gt:0'],
                'preco_unitario' => ['required', 'regex:/^(?:0|[1-9]\d{0,7})(?:\.\d{1,4})?$/', 'numeric', 'gt:0'],
                'quilometragem' => ['required', 'regex:/^(?:0|[1-9]\d{0,10})(?:\.\d)?$/', 'numeric', 'min:0'],
                'data' => ['required', 'date_format:Y-m-d'],
                'tanque_completo' => ['nullable', 'boolean'],
                'fornecedor' => ['nullable', 'string', 'max:150'],
                'numero_documento' => ['nullable', 'string', 'max:100'],
            ];
        }
        if ($screen === 'maintenance' && in_array($action, ['create', 'edit'], true)) {
            return $upload + ($action === 'edit' ? ['versao' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/']] : []) + [
                'veiculo' => [$action === 'create' ? 'required' : 'prohibited', 'string', 'max:20'],
                'tipo' => ['required', Rule::in(['preventiva', 'corretiva', 'vistoria'])],
                'inicio_previsto' => ['required', 'date_format:Y-m-d\TH:i'],
                'fim_previsto' => ['required', 'date_format:Y-m-d\TH:i'],
                'descricao' => ['required', 'string', 'max:3000'],
                'fornecedor' => ['nullable', 'string', 'max:150'],
                'quilometragem' => ['nullable', 'regex:/^(?:0|[1-9]\d{0,10})(?:\.\d)?$/'],
            ];
        }
        if ($screen === 'expenses' && in_array($action, ['submit', 'verify', 'cancel', 'pay'], true)) {
            return $version + match ($action) {
                'verify' => ['resultado' => ['required', Rule::in(['aceito', 'correcao_solicitada'])], 'justificativa' => ['required_if:resultado,correcao_solicitada', 'nullable', 'string', 'max:3000']],
                'cancel' => ['justificativa' => ['required', 'string', 'max:3000'], 'confirmar' => ['accepted']],
                'pay' => ['pago_em' => ['required', 'date_format:Y-m-d\TH:i'], 'comprovante' => ['required', 'file', 'max:10240', 'mimetypes:application/pdf,image/png,image/jpeg'], 'confirmar' => ['accepted']],
                default => [],
            };
        }
        if ($screen === 'maintenance' && in_array($action, ['start', 'complete', 'cancel'], true)) {
            return ['versao' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/']] + match ($action) {
                'start' => ['inicio_real' => ['required', 'date_format:Y-m-d\TH:i']],
                'complete' => ['fim_real' => ['required', 'date_format:Y-m-d\TH:i'], 'quilometragem' => ['nullable', 'regex:/^(?:0|[1-9]\d{0,10})(?:\.\d)?$/'], 'proxima_revisao_km' => ['nullable', 'regex:/^(?:0|[1-9]\d{0,10})(?:\.\d)?$/'], 'proxima_revisao_data' => ['nullable', 'date_format:Y-m-d']],
                default => ['justificativa' => ['required', 'string', 'max:1000'], 'confirmar' => ['accepted']],
            };
        }

        return [];
    }

    protected function getRedirectUrl(): string
    {
        $screen = (string) $this->route('tela');
        $action = (string) ($this->route('acao') ?? 'create');
        if ($action === 'create') {
            return route($screen.'.create');
        }

        return route($screen.'.operation', ['registro' => $this->route('registro'), 'acao' => $action]);
    }
}
