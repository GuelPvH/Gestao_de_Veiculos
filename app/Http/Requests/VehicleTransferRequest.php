<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('acao') === 'transfer';
    }

    /**
     * @return array<string, list<string|\Stringable|\Closure|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'versao' => ['required', 'integer', 'min:1'],
            'unidade_destino_id' => ['required', 'integer', Rule::exists('unidades', 'id')->where('ativa', 1)],
            'justificativa' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
