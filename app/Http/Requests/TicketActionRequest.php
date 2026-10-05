<?php

namespace App\Http\Requests;

use App\Services\Tickets\TicketService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $servico = app(TicketService::class);
        $chamado = $servico->ticket((int) $this->route('registro'));

        return $servico->canAction($chamado, (string) $this->route('acao'));
    }

    public function rules(): array
    {
        return match ((string) $this->route('acao')) {
            'respond', 'note' => ['mensagem' => ['required', 'string', 'max:5000']],
            'assign' => [
                'versao' => ['required', 'integer', 'min:1'],
                'situacao' => ['required', Rule::in(['em_atendimento', 'aguardando_solicitante'])],
                'responsavel' => ['required', 'integer', 'min:1'],
                'motivo' => ['required', 'string', 'max:3000'],
            ],
            'resolve' => [
                'versao' => ['required', 'integer', 'min:1'],
                'motivo' => ['required', 'string', 'max:3000'],
            ],
            'attach' => ['arquivo' => ['required', 'file', 'max:10240', 'mimetypes:application/pdf,image/png,image/jpeg']],
            default => [],
        };
    }

    protected function getRedirectUrl(): string
    {
        return route('tickets.operation', ['registro' => $this->route('registro'), 'acao' => $this->route('acao')]);
    }
}
