<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Hash;

class PasswordVerifier
{
    public function verify(string $senha, string $hash): bool
    {
        if (str_contains($senha, "\0") || strlen($senha) > 1024) {
            return false;
        }
        $bcrypt = preg_match('/^\$2[yb]\$\d{2}\$/', $hash) === 1;
        if ((! $bcrypt && ! str_starts_with($hash, '$argon2id$')) || ($bcrypt && strlen($senha) > 72)) {
            return false;
        }

        return password_verify($senha, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        // Preservar Argon2id ao escolher bcrypt para novas senhas.
        if (str_starts_with($hash, '$argon2id$') && config('hashing.driver') === 'bcrypt') {
            return false;
        }

        return Hash::needsRehash($hash);
    }

    public function make(string $senha): string
    {
        return Hash::make($senha);
    }
}
