<?php

namespace App\Http\Requests;

use App\Services\Tickets\TicketService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketOpenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(TicketService::class)->canCreate();
    }

    public function rules(): array
    {
        return [
            'categoria' => ['required', 'integer', Rule::exists('categorias_chamado', 'id')->where('ativa', 1)],
            'assunto' => ['required', 'string', 'max:200'],
            'descricao' => ['required', 'string', 'max:5000'],
            'pagina_contexto' => ['nullable', 'string', 'max:255', 'regex:/^\/(?!\/)[^?#]*$/'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('tickets.create');
    }
}
