<?php

namespace App\Http\Requests;

use App\Services\Authorization\AccessContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigurationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AccessContext::class)->can('configuracoes', 'editar');
    }

    public function rules(): array
    {
        return [
            'fuso_horario' => ['required', 'string', 'max:64', Rule::in(timezone_identifiers_list())],
            'sessao_inatividade_minutos' => ['required', 'integer', 'between:5,1440'],
            'limite_sem_comunicacao_minutos' => ['required', 'integer', 'between:1,1440'],
        ];
    }
}
