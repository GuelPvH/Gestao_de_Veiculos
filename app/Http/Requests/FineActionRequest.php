<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FineActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('acao') === null || in_array($this->route('acao'), ['edit', 'assign', 'proof', 'verify', 'correct', 'settle', 'dispute', 'cancel'], true);
    }

    public function rules(): array
    {
        $valor = ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999999.99'];
        $motivo = ['required', 'string', 'max:3000'];
        $versao = ['versao' => ['required', 'integer', 'min:1']];

        return match ($this->route('acao')) {
            null => [
                'veiculo_id' => ['required', 'integer', 'min:1'],
                'numero_auto' => ['nullable', 'string', 'max:100'],
                'orgao_autuador' => ['nullable', 'string', 'max:150'],
                'ocorrido_em' => ['required', 'date_format:Y-m-d\TH:i'],
                'precisao_ocorrencia' => ['required', Rule::in(['instante', 'dia'])],
                'data_vencimento' => ['nullable', 'date_format:Y-m-d'],
                'valor' => $valor,
                'descricao' => ['required', 'string', 'max:3000'],
            ],
            'edit' => $versao + [
                'numero_auto' => ['nullable', 'string', 'max:100'],
                'orgao_autuador' => ['nullable', 'string', 'max:150'],
                'data_vencimento' => ['nullable', 'date_format:Y-m-d'],
                'valor' => $valor,
                'descricao' => ['required', 'string', 'max:3000'],
                'justificativa' => $motivo,
            ],
            'assign' => $versao + [
                'viagem_id' => ['required', 'integer', 'min:1'],
                'responsavel_id' => ['required', 'integer', 'min:1'],
                'justificativa' => $motivo,
            ],
            'proof' => $versao + [
                'comprovante' => ['required', 'file', 'max:10240', 'mimetypes:application/pdf,image/png,image/jpeg'],
                'valor_declarado' => $valor,
                'pagamento_em' => ['required', 'date_format:Y-m-d\TH:i'],
                'observacao' => ['nullable', 'string', 'max:2000'],
            ],
            'verify' => $versao + [
                'resultado' => ['required', Rule::in(['aceito', 'correcao_solicitada'])],
                'motivo' => ['nullable', 'string', 'max:3000'],
                'valor_confirmado' => ['required_if:resultado,aceito', 'nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999999.99'],
                'pagamento_confirmado_em' => ['required_if:resultado,aceito', 'nullable', 'date_format:Y-m-d\TH:i'],
            ],
            'correct' => $versao + ['motivo' => $motivo],
            'settle' => $versao + [
                'motivo' => ['nullable', 'string', 'max:3000'],
                'valor_confirmado' => $valor,
                'pagamento_confirmado_em' => ['required', 'date_format:Y-m-d\TH:i'],
            ],
            'dispute', 'cancel' => $versao + ['justificativa' => $motivo],
            default => [],
        };
    }

    protected function getRedirectUrl(): string
    {
        return $this->route('acao') === null
            ? route('fines.create')
            : route('fines.operation', ['registro' => $this->route('registro'), 'acao' => $this->route('acao')]);
    }
}
