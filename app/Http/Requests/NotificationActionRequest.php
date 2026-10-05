<?php

namespace App\Http\Requests;

use App\Services\Notifications\NotificationStateService;
use Illuminate\Foundation\Http\FormRequest;

class NotificationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(NotificationStateService::class)->canEdit();
    }

    public function rules(): array
    {
        return ['versao' => ['required', 'integer', 'min:1']];
    }

    protected function getRedirectUrl(): string
    {
        return route('notifications.index');
    }

    public function messages(): array
    {
        return ['versao.required' => 'Atualize a página para continuar.',
            'versao.integer' => 'Atualize a página para continuar.',
            'versao.min' => 'Atualize a página para continuar.'];
    }
}
