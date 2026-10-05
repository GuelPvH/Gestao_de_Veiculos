<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->route('acao'), ['block', 'release'], true);
    }

    public function rules(): array
    {
        $regras = ['versao' => ['required', 'integer', 'min:1']];

        return $this->route('acao') === 'block' ? $regras + [
            'tipo' => ['required', Rule::in(['indisponibilidade', 'manutencao'])],
            'inicio' => ['required', 'date_format:Y-m-d\TH:i'],
            'fim' => ['required', 'date_format:Y-m-d\TH:i', 'after:inicio'],
            'descricao' => ['required', 'string', 'max:500'],
        ] : $regras + [
            'reserva_id' => ['required', 'integer', 'min:1'],
            'confirmacao' => ['required', Rule::in(['liberar'])],
        ];
    }
}
