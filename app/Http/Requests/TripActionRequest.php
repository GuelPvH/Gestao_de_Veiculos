<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TripActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->route('acao'), ['departure', 'return', 'occurrence', 'cancel'], true);
    }

    public function rules(): array
    {
        $regras = ['versao' => ['required', 'integer', 'min:1']];

        return match ($this->route('acao')) {
            'departure', 'return' => $regras + [
                'data_registro' => ['required', 'date_format:Y-m-d\TH:i'],
                'quilometragem' => ['required', 'numeric', 'min:0', 'max:99999999999.9', 'decimal:0,1'],
                'observacoes' => ['nullable', 'string', 'max:3000'],
            ],
            'occurrence' => $regras + [
                'tipo' => ['required', Rule::in(['geral', 'desvio_trajeto', 'avaria', 'acidente', 'atraso'])],
                'ocorrido_em' => ['required', 'date_format:Y-m-d\TH:i'],
                'descricao' => ['required', 'string', 'max:3000'],
            ],
            'cancel' => $regras + [
                'solicitacao_versao' => ['required', 'integer', 'min:1'],
                'justificativa' => ['required', 'string', 'max:3000'],
            ],
            default => $regras,
        };
    }
}
