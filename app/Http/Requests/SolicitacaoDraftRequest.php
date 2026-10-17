<?php

namespace App\Http\Requests;

use App\Services\Requests\RequestReadRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SolicitacaoDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $leituras = app(RequestReadRepository::class);

        return $this->isMethod('POST') ? $leituras->canCreate() : isset($leituras->allowed($leituras->findById((int) $this->route('registro')))['edit']);
    }

    public function rules(): array
    {
        return [
            'finalidade' => ['required', 'string', 'max:3000'],
            'origem' => ['required', 'string', 'max:255'], 'destino' => ['required', 'string', 'max:255'],
            'origem_latitude' => ['required', 'numeric', 'between:-90,90'],
            'origem_longitude' => ['required', 'numeric', 'between:-180,180'],
            'destino_latitude' => ['required', 'numeric', 'between:-90,90'],
            'destino_longitude' => ['required', 'numeric', 'between:-180,180'],
            'saida_prevista' => ['required', 'date_format:Y-m-d\TH:i'],
            'retorno_previsto' => ['required', 'date_format:Y-m-d\TH:i', 'after:saida_prevista'],
            'trajeto_planejado' => ['nullable', 'string', 'max:3000'],
            'quantidade_passageiros' => ['required', 'integer', 'min:1', 'max:100'],
            'passageiros' => ['nullable', 'string', 'max:16000'],
            'necessita_motorista' => ['required', Rule::in(['0', '1'])],
            'motorista_sugerido_id' => ['prohibited'],
            'veiculo_pretendido_id' => ['required', 'integer', Rule::in(array_keys(app(RequestReadRepository::class)->vehicleOptions()))],
            'observacoes' => ['nullable', 'string', 'max:3000'],
            'anexo' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
            'versao' => [$this->isMethod('PATCH') ? 'required' : 'prohibited', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('passageiros') || $validator->errors()->has('quantidade_passageiros')) {
                return;
            }
            $nomes = collect(preg_split('/\r\n|\r|\n/', (string) $this->input('passageiros', '')))->map(fn (string $nome) => trim($nome))->filter(fn (string $nome) => $nome !== '');
            if ($nomes->count() > (int) $this->input('quantidade_passageiros') || $nomes->contains(fn (string $nome) => mb_strlen($nome) > 150)) {
                $validator->errors()->add('passageiros', 'Informe até a quantidade declarada de passageiros, com um nome de até 150 caracteres por linha.');
            }
        }];
    }
}
