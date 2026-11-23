<?php

namespace App\Http\Requests;

use App\Services\Requests\RequestReadRepository;
use App\Support\Value;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SolicitacaoActionRequest extends FormRequest
{
    /** @var array<string, mixed>|null */
    private ?array $opcoesAprovacao = null;

    public function action(): string
    {
        return match ($this->route()?->getActionMethod()) {
            'sendSubmit' => 'send', 'approveSubmit' => 'approve', 'denySubmit' => 'deny',
            'adjustSubmit' => 'adjust', 'revisionSubmit' => 'revision', 'cancelSubmit' => 'cancel', default => '',
        };
    }

    public function authorize(): bool
    {
        $leituras = app(RequestReadRepository::class);

        return isset($leituras->allowed($leituras->findById((int) $this->route('registro')))[$this->action()]);
    }

    /**
     * @return array<string, list<string|\Stringable|\Closure>>
     */
    public function rules(): array
    {
        $regras = ['versao' => ['required', 'integer', 'min:1']];
        if ($this->action() !== 'send') {
            $regras['justificativa'] = ['required', 'string', 'max:3000'];
        }
        if ($this->action() === 'approve') {
            $this->opcoesAprovacao = app(RequestReadRepository::class)->approvalOptions((int) $this->route('registro'));
            $regras += [
                'veiculo_confirmado_id' => ['required', 'integer', Rule::in(array_keys(Value::array($this->opcoesAprovacao['veiculosDisponiveis'])))],
                'motorista_confirmado_id' => ['required', 'integer', Rule::in(array_keys(Value::array($this->opcoesAprovacao['motoristasDisponiveis'])))],
            ];
        }

        return $regras;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->action() !== 'approve' || $validator->errors()->hasAny(['veiculo_confirmado_id', 'motorista_confirmado_id'])) {
                return;
            }
            $opcoes = $this->opcoesAprovacao ?? app(RequestReadRepository::class)->approvalOptions((int) $this->route('registro'));
            $veiculoId = Value::integer($this->input('veiculo_confirmado_id'));
            $motoristaId = Value::integer($this->input('motorista_confirmado_id'));
            $veiculosCnh = Value::array($opcoes['veiculosCnh'] ?? []);
            $motoristasCnh = Value::array($opcoes['motoristasCnh'] ?? []);
            $categoriaExigida = Value::string($veiculosCnh[$veiculoId] ?? '');
            $categoriasMotorista = Value::string($motoristasCnh[$motoristaId] ?? '');
            if ($categoriaExigida !== '' && ! str_contains($categoriasMotorista, $categoriaExigida)) {
                $validator->errors()->add('motorista_confirmado_id', 'O motorista selecionado não possui habilitação na categoria '.$categoriaExigida.' exigida pelo veículo.');
            }
        }];
    }
}
