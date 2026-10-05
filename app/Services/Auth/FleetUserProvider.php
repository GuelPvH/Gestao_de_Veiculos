<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class FleetUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier)
    {
        return User::query()->whereKey($identifier)->where('ativo', 1)->first();
    }

    public function retrieveByToken($identifier, $token)
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void {}

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return $user instanceof User && $user->ativo &&
            app(PasswordVerifier::class)->verify((string) ($credentials['password'] ?? ''), $user->getAuthPassword());
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        $verificador = app(PasswordVerifier::class);
        if (! $user instanceof User || (! $force && ! $verificador->needsRehash($user->getAuthPassword()))) {
            return;
        }
        $anterior = $user->getAuthPassword();
        $novo = $verificador->make((string) $credentials['password']);
        $alterados = User::query()->whereKey($user->getKey())->where('senha_hash', $anterior)->update(['senha_hash' => $novo]);
        if ($alterados === 1) {
            $user->senha_hash = $novo;
        }
    }
}
