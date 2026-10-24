<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\Value;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class FleetUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier)
    {
        $request = request();
        if ($request->hasSession() && $request->routeIs('session.status')) {
            return app(SessionStatusReader::class)->user($request, Value::integer($identifier));
        }

        return User::query()->whereKey($identifier)->where('ativo', 1)->first();
    }

    public function retrieveByToken($identifier, $token)
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void {}

    /**
     * @param  array<array-key, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return $user instanceof User && $user->ativo &&
            app(PasswordVerifier::class)->verify(Value::string($credentials['password'] ?? ''), $user->getAuthPassword());
    }

    /**
     * @param  array<array-key, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        $verificador = app(PasswordVerifier::class);
        if (! $user instanceof User || (! $force && ! $verificador->needsRehash($user->getAuthPassword()))) {
            return;
        }
        $anterior = $user->getAuthPassword();
        $novo = $verificador->make(Value::string($credentials['password']));
        $alterados = User::query()->whereKey($user->getKey())->where('senha_hash', $anterior)->update(['senha_hash' => $novo]);
        if ($alterados === 1) {
            $user->senha_hash = $novo;
        }
    }
}
