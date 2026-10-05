<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleInputRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $placa = $this->input('placa');
        $renavam = $this->input('renavam');
        $chassi = $this->input('chassi');
        $this->merge([
            'placa' => is_string($placa) ? strtoupper(preg_replace('/[\s-]+/', '', $placa)) : $placa,
            'renavam' => is_string($renavam) && trim($renavam) !== '' ? trim($renavam) : null,
            'chassi' => is_string($chassi) && trim($chassi) !== '' ? strtoupper(trim($chassi)) : null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $criando = $this->route('registro') === null;

        return [
            'versao' => [$criando ? 'prohibited' : 'required', 'integer', 'min:1'],
            'unidade_id' => $criando ? ['required', 'integer', 'min:1'] : ['prohibited'],
            'categoria_id' => ['required', 'integer', Rule::exists('categorias_veiculo', 'id')->where('ativa', 1)],
            'nome' => ['required', 'string', 'max:120'],
            'placa' => ['required', 'string', 'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/'],
            'renavam' => ['nullable', 'string', 'max:20'],
            'chassi' => ['nullable', 'string', 'max:30'],
            'marca' => ['nullable', 'string', 'max:80'],
            'modelo' => ['nullable', 'string', 'max:80'],
            'ano_fabricacao' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'ano_modelo' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 2)],
            'capacidade' => ['required', 'integer', 'min:1', 'max:100'],
            'quilometragem_atual' => ['required', 'numeric', 'min:0', 'max:99999999999.9', 'decimal:0,1'],
            'situacao_cadastro' => ['required', Rule::in($criando ? ['ativo', 'inativo'] : ['ativo', 'inativo', 'baixado'])],
            'observacoes' => ['nullable', 'string', 'max:10000'],
            'justificativa' => [$criando ? 'prohibited' : 'required', 'string', 'max:1000'],
            'confirmacao_baixa' => [$criando ? 'prohibited' : 'nullable', 'string', 'max:6'],
        ];
    }
}
