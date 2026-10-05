<?php

namespace App\Services\Auth;

use Closure;
use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    /** @return array<mixed> */
    public static function rules(): array
    {
        return ['required', 'string', 'confirmed', 'max:256', Password::min(12)->mixedCase()->numbers()->symbols(),
            function (string $atributo, mixed $valor, Closure $falhar): void {
                if (is_string($valor) && (str_contains($valor, "\0") || (config('hashing.driver', 'bcrypt') === 'bcrypt' && strlen($valor) > 72))) {
                    $falhar('Use uma senha sem caracteres nulos e com até 72 bytes.');
                }
            }];
    }
}
