<?php

namespace App\Http\Requests;

use App\Services\Authorization\AccessContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class TyreActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $access = app(AccessContext::class);
        $link = $access->link();
        if ($link === null) {
            return false;
        }
        $action = (string) $this->route('acao');
        if ($action === 'create') {
            return $access->can('despesas', 'criar', (int) $link->usuario_id, (int) $link->unidade_id);
        }
        $record = DB::table('pneus as p')->join('despesas as d', 'd.id', '=', 'p.despesa_aquisicao_id')
            ->where('p.id', (int) $this->route('registro'))->first(['d.criado_por', 'd.unidade_id']);
        abort_unless($record !== null, 404);
        if ($action === 'remove') {
            $matches = DB::table('pneu_instalacoes')->where('id', (int) $this->route('instalacao'))
                ->where('pneu_id', (int) $this->route('registro'))->exists();
            abort_unless($matches, 404);
        }
        $permission = $action === 'discard' ? 'cancelar' : 'editar';

        return in_array($action, ['install', 'remove', 'discard'], true)
            && $access->can('despesas', 'consultar', (int) $record->criado_por, (int) $record->unidade_id)
            && $access->can('despesas', $permission, (int) $record->criado_por, (int) $record->unidade_id);
    }

    public function rules(): array
    {
        return match ((string) $this->route('acao')) {
            'create' => [
                'despesa_aquisicao_id' => ['required', 'integer', 'min:1'],
                'codigo' => ['required', 'string', 'max:60'],
                'numero_serie' => ['nullable', 'string', 'max:100'],
                'marca' => ['nullable', 'string', 'max:80'],
                'modelo' => ['nullable', 'string', 'max:80'],
                'medida' => ['required', 'string', 'max:40'],
                'adquirido_em' => ['nullable', 'date_format:Y-m-d'],
            ],
            'install' => [
                'veiculo' => ['required', 'string', 'max:20'],
                'posicao' => ['required', 'string', 'max:40'],
                'instalado_em' => ['required', 'date_format:Y-m-d\TH:i'],
                'quilometragem_instalacao' => ['required', 'regex:/^(?:0|[1-9]\d{0,10})(?:\.\d)?$/'],
                'manutencao_id' => ['nullable', 'integer', 'min:1'],
            ],
            'remove' => [
                'removido_em' => ['required', 'date_format:Y-m-d\TH:i'],
                'quilometragem_remocao' => ['required', 'regex:/^(?:0|[1-9]\d{0,10})(?:\.\d)?$/'],
                'motivo_remocao' => ['required', 'string', 'max:500'],
            ],
            'discard' => [
                'justificativa' => ['required', 'string', 'max:500'],
                'confirmar' => ['accepted'],
            ],
            default => [],
        };
    }

    protected function getRedirectUrl(): string
    {
        return $this->route('acao') === 'create'
            ? route('tyres.create')
            : route('tyres.show', (int) $this->route('registro'));
    }
}
