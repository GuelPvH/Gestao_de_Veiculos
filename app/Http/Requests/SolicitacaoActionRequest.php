<?php

namespace App\Http\Requests;

use App\Services\Requests\RequestReadRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitacaoActionRequest extends FormRequest
{
    public function action(): string
    {
        return match ($this->route()->getActionMethod()) {
            'sendSubmit' => 'send', 'approveSubmit' => 'approve', 'denySubmit' => 'deny',
            'adjustSubmit' => 'adjust', 'revisionSubmit' => 'revision', default => '',
        };
    }

    public function authorize(): bool
    {
        $leituras = app(RequestReadRepository::class);

        return isset($leituras->allowed($leituras->findById((int) $this->route('registro')))[$this->action()]);
    }

    public function rules(): array
    {
        $regras = ['versao' => ['required', 'integer', 'min:1']];
        if ($this->action() !== 'send') {
            $regras['justificativa'] = ['required', 'string', 'max:3000'];
        }
        if ($this->action() === 'approve') {
            $opcoes = app(RequestReadRepository::class)->approvalOptions((int) $this->route('registro'));
            $regras += [
                'veiculo_confirmado_id' => ['required', 'integer', Rule::in(array_keys($opcoes['veiculosDisponiveis']))],
                'motorista_confirmado_id' => ['required', 'integer', Rule::in(array_keys($opcoes['motoristasDisponiveis']))],
            ];
        }

        return $regras;
    }
}
