<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|\Stringable|\Closure|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', 'max:60'],
            'numero' => ['nullable', 'string', 'max:80'],
            'exercicio' => ['nullable', 'integer', 'between:1990,'.(date('Y') + 2)],
            'emitido_em' => ['nullable', 'date_format:Y-m-d'],
            'vence_em' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:emitido_em'],
            'substitui_documento_id' => ['nullable', 'integer', Rule::exists('documentos_veiculo', 'id')->where('veiculo_id', (int) $this->route('registro'))],
            'arquivo' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
        ];
    }
}
